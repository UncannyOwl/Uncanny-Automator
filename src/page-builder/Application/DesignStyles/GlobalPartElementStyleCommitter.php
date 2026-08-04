<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleProperty;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;
use UncannyPageBuilder\Domain\DesignStyles\DesignWriteScope;
use UncannyPageBuilder\Domain\DesignStyles\StableSelector;
use UncannyPageBuilder\Domain\DesignStyles\StableSelectorResult;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Commits element-specific style changes as global-part-owned structured rules.
 *
 * Header and footer DOM is rendered inside the page canvas, but its persisted
 * source belongs to a global part. This committer mirrors section element saves
 * while writing through GlobalPartService so source writes, compilation, and
 * artifact invalidation remain the source of truth.
 */
final class GlobalPartElementStyleCommitter implements GlobalPartElementStyleCommitterInterface
{
    /** @var string[] */
    private const VALID_VIEWPORTS = ['desktop', 'tablet', 'mobile'];

    /** @var string[] */
    private const VALID_STATES = ['normal', 'hover', 'focus', 'active'];

    public function __construct(
        private readonly GlobalPartService $globalParts,
        private readonly InlineTypographyMigrator $inlineTypography,
    ) {}

    public function commit(DesignStyleCommitRequest $request): DesignStyleCommitResult
    {
        $plan = $this->prepare($request);

        return $plan instanceof GlobalPartElementStyleCommitPlan
            ? $this->apply($plan)
            : $plan;
    }

    public function prepare(DesignStyleCommitRequest $request): GlobalPartElementStyleCommitPlan|DesignStyleCommitResult
    {
        if (!$request->canEdit()) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'You do not have permission to edit this global part.',
            );
        }

        $owner = $request->owner();
        if (!$owner instanceof DesignStyleSourceOwner || !$owner->isGlobalPart()) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'A global part is required to save this element style.',
                [['property' => '', 'reason' => 'missing_global_part_owner']],
            );
        }

        $type = $owner->globalPartType();
        if (!$type instanceof GlobalPartType) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'This global part type is not supported.',
                [['property' => '', 'reason' => 'invalid_global_part_type']],
            );
        }

        $part = $this->globalParts->findById($owner->id());
        if ($part === null || (string) ($part['type'] ?? '') !== $type->value) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'This global part no longer exists. Refresh the Manual editor and try again.',
                [['property' => '', 'reason' => 'global_part_not_found']],
            );
        }

        $section = $this->globalParts->sourceSectionFromSnapshot($owner->id(), $part);
        if (!$section instanceof Section) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'This global part has no editable source. Refresh the Manual editor and try again.',
                [['property' => '', 'reason' => 'global_part_source_missing']],
            );
        }

        $validated = $this->validateChanges($request->changes());
        if ($validated['rejected'] !== []) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'Some global part element styles could not be saved.',
                $validated['rejected'],
            );
        }
        if ($validated['target_groups'] === []) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'No valid global part element style changes to save.',
            );
        }

        $html = $section->content()->html();
        $elementStyles = $section->content()->elementStyles();
        $targets = [];
        $promoted = false;

        foreach ($validated['target_groups'] as $targetKey => $group) {
            /** @var DesignStyleChange $target */
            $target = $group['target'];
            /** @var array<string, string> $declarations */
            $declarations = $group['declarations'];

            $sourceSectionId = $section->id() ?? 0;
            $shortcodeTarget = ShortcodeStyleTargetMaterializer::materialize($html, $target->selector(), $sourceSectionId);
            if ($shortcodeTarget !== null) {
                $html = $shortcodeTarget['html'];
                $selectorResult = new StableSelectorResult(
                    '#' . $shortcodeTarget['element_id'],
                    $html,
                    $shortcodeTarget['promoted'],
                );
            } else {
                $selectorResult = StableSelector::resolve(
                    html: $html,
                    selector: $target->stableSelector(),
                    identity: $target->identity(),
                    sourcePath: $target->sourcePath(),
                    seed: 'global-part-' . $owner->id() . '|' . $targetKey,
                    expectedTag: $target->tag(),
                );
            }

            if (!$selectorResult->isResolved() || $selectorResult->selector() === null) {
                return DesignStyleCommitResult::error(
                    DesignWriteScope::Element,
                    'Could not resolve a stable target for this global part element.',
                    [['property' => '', 'reason' => 'selector_unresolved']],
                );
            }

            $selector = $selectorResult->selector();
            $elementId = $this->elementIdFromSelector($selector);
            if ($elementId === null) {
                return DesignStyleCommitResult::error(
                    DesignWriteScope::Element,
                    'Could not resolve a stable target for this global part element.',
                    [['property' => '', 'reason' => 'selector_unresolved']],
                );
            }

            $html = $selectorResult->html();
            $migration = $this->inlineTypography->migrate(
                $html,
                $elementStyles,
                $elementId,
                $target->kind(),
                array_keys($declarations),
            );
            if (!$migration->isSafe()) {
                return DesignStyleCommitResult::error(
                    DesignWriteScope::Element,
                    'Legacy inline typography could not be migrated safely.',
                    [['property' => '', 'reason' => $migration->reason() ?: 'inline_style_migration_failed']],
                );
            }

            $html = $migration->html();
            $elementStyles = $migration->elementStyles();
            $elementStyles = $elementStyles->withRule(
                $elementId,
                $target->kind(),
                $target->viewport(),
                $target->state(),
                $declarations,
            );
            $targets[] = [
                'element_id' => $elementId,
                'kind'       => $target->kind(),
                'promoted'   => $selectorResult->wasPromoted(),
                'properties' => array_keys($declarations),
                'viewport'   => $target->viewport(),
                'state'      => $target->state(),
            ];
            $promoted = $promoted || $selectorResult->wasPromoted();
        }

        return new GlobalPartElementStyleCommitPlan(
            partId: $owner->id(),
            partSnapshot: $part,
            title: (string) ($part['title'] ?? ''),
            sectionName: $section->name(),
            content: [
                'html'           => $html,
                'css'            => $section->content()->css(),
                'element_styles' => $elementStyles->toArray(),
            ],
            type: $type,
            applied: $validated['applied'],
            targets: $targets,
            promoted: $promoted,
        );
    }

    public function apply(GlobalPartElementStyleCommitPlan $plan): DesignStyleCommitResult
    {
        $saved = $this->globalParts->replaceLoadedSource(
            globalPartId: $plan->partId(),
            existing: $plan->partSnapshot(),
            title: $plan->title(),
            sectionData: [
                'name'    => $plan->sectionName(),
                'content' => $plan->content(),
            ],
            type: $plan->type(),
        );

        return DesignStyleCommitResult::success(
            DesignWriteScope::Element,
            'Global part element styles saved.',
            $plan->applied(),
            [
                'global_part' => [
                    'part_id'    => $plan->partId(),
                    'part_type'  => $plan->type()->value,
                    'section_id' => (int) ($saved['section_id'] ?? 0),
                    'element_id' => $plan->targets()[0]['element_id'] ?? '',
                    'targets'    => $plan->targets(),
                    'promoted'   => $plan->promoted(),
                ],
            ],
        );
    }

    /**
     * @param DesignStyleChange[] $changes
     * @return array{
     *     target_groups: array<string, array{target: DesignStyleChange, declarations: array<string, string>}>,
     *     applied: array<int, array{property: string, value: string, viewport: string, state: string}>,
     *     rejected: array<int, array{property: string, reason: string}>
     * }
     */
    private function validateChanges(array $changes): array
    {
        $targetGroups = [];
        $applied = [];
        $rejected = [];

        foreach ($changes as $change) {
            $property = strtolower(trim($change->property()));
            $value = $change->value();

            if (!DesignStyleProperty::isAllowed($property)) {
                $rejected[] = ['property' => $property, 'reason' => 'property_not_allowed'];
                continue;
            }
            if (!DesignStyleValue::isSafeValue($value)) {
                $rejected[] = ['property' => $property, 'reason' => 'unsafe_value'];
                continue;
            }
            if (!$this->isValidViewport($change->viewport())) {
                $rejected[] = ['property' => $property, 'reason' => 'invalid_viewport'];
                continue;
            }
            if (!$this->isValidState($change->state())) {
                $rejected[] = ['property' => $property, 'reason' => 'invalid_state'];
                continue;
            }

            $targetKey = $this->targetKey($change);
            if (!isset($targetGroups[$targetKey])) {
                $targetGroups[$targetKey] = [
                    'target'       => $change,
                    'declarations' => [],
                ];
            }

            $targetGroups[$targetKey]['declarations'][$property] = $value;
            $applied[] = [
                'property' => $property,
                'value'    => $value,
                'viewport' => $change->viewport(),
                'state'    => $change->state(),
            ];
        }

        return [
            'target_groups' => $targetGroups,
            'applied'       => $applied,
            'rejected'      => $rejected,
        ];
    }

    private function targetKey(DesignStyleChange $change): string
    {
        return implode('|', [
            $change->selector() ?? '',
            $change->elementId() ?? '',
            $change->identity() ?? '',
            $change->sourcePath() ?? '',
            $change->tag() ?? '',
            $change->kind(),
            $change->viewport(),
            $change->state(),
        ]);
    }

    private function isValidViewport(string $viewport): bool
    {
        return in_array($viewport, self::VALID_VIEWPORTS, true);
    }

    private function isValidState(string $state): bool
    {
        return in_array($state, self::VALID_STATES, true);
    }

    private function elementIdFromSelector(string $selector): ?string
    {
        return preg_match('/^#([A-Za-z][A-Za-z0-9_-]*)$/', trim($selector), $matches) === 1
            ? $matches[1]
            : null;
    }
}
