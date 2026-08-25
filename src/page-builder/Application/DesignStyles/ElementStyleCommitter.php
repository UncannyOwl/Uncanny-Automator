<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\DesignStyles\DesignStyleProperty;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;
use UncannyPageBuilder\Domain\DesignStyles\DesignWriteScope;
use UncannyPageBuilder\Domain\DesignStyles\StableSelector;
use UncannyPageBuilder\Domain\DesignStyles\StableSelectorResult;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Commits element-specific style changes as section-owned structured rules.
 *
 * Resolves (or promotes) a stable element id, upserts structured declarations,
 * and saves through the existing section persistence path so compilation and
 * history all run. Never persists arbitrary selector CSS for
 * user customization.
 */
final class ElementStyleCommitter implements ElementStyleCommitterInterface
{
    /** @var string[] */
    private const VALID_VIEWPORTS = ['desktop', 'tablet', 'mobile'];

    /** @var string[] */
    private const VALID_STATES = ['normal', 'hover', 'focus', 'active'];

    public function __construct(
        private readonly SectionSourceWriter $sections,
        private readonly SectionRepositoryInterface $repository,
        private readonly InlineTypographyMigrator $inlineTypography,
    ) {}

    public function commit(DesignStyleCommitRequest $request): DesignStyleCommitResult
    {
        $plan = $this->prepare($request);

        return $plan instanceof ElementStyleCommitPlan
            ? $this->apply($plan)
            : $plan;
    }

    public function prepare(DesignStyleCommitRequest $request): ElementStyleCommitPlan|DesignStyleCommitResult
    {
        $baseError = $this->validateBaseRequest($request->pageId(), $request->canEdit());
        if ($baseError instanceof DesignStyleCommitResult) {
            return $baseError;
        }

        $sectionId = $request->sectionId();
        $targetError = $this->validateSectionTarget($sectionId);
        if ($targetError instanceof DesignStyleCommitResult) {
            return $targetError;
        }

        $loaded = $this->loadSectionForPage($request->pageId(), $sectionId);
        if ($loaded instanceof DesignStyleCommitResult) {
            return $loaded;
        }

        [$sections, $section] = $loaded;

        $prepared = $this->prepareSectionUpdate($section, $sectionId, $request->changes());
        if ($prepared instanceof DesignStyleCommitResult) {
            return $prepared;
        }

        return new ElementStyleCommitPlan(
            pageId: $request->pageId(),
            sections: $sections,
            sectionUpdates: [$prepared['update']],
            applied: $prepared['applied'],
            targets: $prepared['targets'],
            promoted: $prepared['promoted'],
        );
    }

    /**
     * @param DesignStyleBatchChange[] $changes
     * @param array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool} $capabilities
     */
    public function prepareBatch(int $pageId, array $changes, array $capabilities): ElementStyleCommitPlan|DesignStyleCommitResult
    {
        $baseError = $this->validateBaseRequest($pageId, (bool) ($capabilities['can_edit'] ?? false));
        if ($baseError instanceof DesignStyleCommitResult) {
            return $baseError;
        }

        $bySection = [];
        foreach ($changes as $batchChange) {
            if (!$batchChange instanceof DesignStyleBatchChange) {
                return DesignStyleCommitResult::error(
                    DesignWriteScope::Element,
                    'Element style batches must contain valid design changes.',
                    [['property' => '', 'reason' => 'invalid_batch_change']],
                );
            }

            $sectionId = $batchChange->sectionId();
            $targetError = $this->validateSectionTarget($sectionId);
            if ($targetError instanceof DesignStyleCommitResult) {
                return $targetError;
            }

            if (!isset($bySection[$sectionId])) {
                $bySection[$sectionId] = [
                    'changes'  => [],
                ];
            }

            $bySection[$sectionId]['changes'][] = $batchChange->change();
        }

        if ($bySection === []) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'No valid element style changes to save.',
            );
        }

        try {
            $sections = $this->repository->findByPageId($pageId);
        } catch (SectionNotFoundException) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'This section no longer exists. Refresh the Manual editor and try again.',
                [['property' => '', 'reason' => 'section_not_found']],
            );
        }

        $updates = [];
        $applied = [];
        $targets = [];
        $promoted = false;

        foreach ($bySection as $sectionId => $group) {
            try {
                $section = $sections->getById((int) $sectionId);
            } catch (SectionNotFoundException) {
                return DesignStyleCommitResult::error(
                    DesignWriteScope::Element,
                    'This section no longer exists. Refresh the Manual editor and try again.',
                    [['property' => '', 'reason' => 'section_not_found']],
                );
            }

            if ($section->pageId() !== $pageId) {
                return DesignStyleCommitResult::error(
                    DesignWriteScope::Element,
                    'This section does not belong to the current page.',
                    [['property' => '', 'reason' => 'section_not_found']],
                );
            }

            $prepared = $this->prepareSectionUpdate($section, (int) $sectionId, $group['changes']);
            if ($prepared instanceof DesignStyleCommitResult) {
                return $prepared;
            }

            $updates[] = $prepared['update'];
            $applied = array_merge($applied, $prepared['applied']);
            $targets = array_merge($targets, $prepared['targets']);
            $promoted = $promoted || $prepared['promoted'];
        }

        return new ElementStyleCommitPlan(
            pageId: $pageId,
            sections: $sections,
            sectionUpdates: $updates,
            applied: $applied,
            targets: $targets,
            promoted: $promoted,
        );
    }

    public function apply(ElementStyleCommitPlan $plan): DesignStyleCommitResult
    {
        $saved = $this->sections->replaceLoadedSectionSources(
            pageId: $plan->pageId(),
            sections: $plan->sections(),
            updates: $plan->sectionUpdates(),
        );

        $firstSectionId = $plan->sectionId();

        return DesignStyleCommitResult::success(
            DesignWriteScope::Element,
            'Element styles saved.',
            $plan->applied(),
            [
                'section' => [
                    'section_id'   => $firstSectionId,
                    'page_id'      => $plan->pageId(),
                    'compiled_css' => (string) ($saved['compiled_css'] ?? ''),
                    'element_id'   => $plan->targets()[0]['element_id'] ?? '',
                    'targets'      => $plan->targets(),
                    'promoted'     => $plan->promoted(),
                ],
            ],
        );
    }

    private function validateBaseRequest(int $pageId, bool $canEdit): ?DesignStyleCommitResult
    {
        if ($pageId <= 0) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                sprintf('Unable to find page id: %d', $pageId),
            );
        }

        if (!$canEdit) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'You do not have permission to edit this page.',
            );
        }

        return null;
    }

    private function validateSectionTarget(int $sectionId): ?DesignStyleCommitResult
    {
        if ($sectionId <= 0) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'A section is required to save an element style.',
                [['property' => '', 'reason' => 'missing_section_id']],
            );
        }

        return null;
    }

    /**
     * @return array{0: \UncannyPageBuilder\Domain\Section\SectionCollection, 1: \UncannyPageBuilder\Domain\Section\Section}|DesignStyleCommitResult
     */
    private function loadSectionForPage(int $pageId, int $sectionId): array|DesignStyleCommitResult
    {
        try {
            $sections = $this->repository->findByPageId($pageId);
            $section = $sections->getById($sectionId);
        } catch (SectionNotFoundException) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'This section no longer exists. Refresh the Manual editor and try again.',
                [['property' => '', 'reason' => 'section_not_found']],
            );
        }

        if ($section->pageId() !== $pageId) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'This section does not belong to the current page.',
                [['property' => '', 'reason' => 'section_not_found']],
            );
        }

        return [$sections, $section];
    }

    /**
     * @param DesignStyleChange[] $changes
     * @return array{
     *     update: array{
     *         section_id: int,
     *         section_name: string,
     *         content: array{html: string, css: string, element_styles: array<string, mixed>}
     *     },
     *     applied: array<int, array<string, mixed>>,
     *     targets: array<int, array<string, mixed>>,
     *     promoted: bool
     * }|DesignStyleCommitResult
     */
    private function prepareSectionUpdate(
        \UncannyPageBuilder\Domain\Section\Section $section,
        int $sectionId,
        array $changes,
    ): array|DesignStyleCommitResult {
        // Validate declarations before mutating anything. Element commits are
        // partitioned by concrete selected target; one section can contain many
        // selected elements, and each must resolve to its own selector.
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

        // Element commits are all-or-nothing. A mixed save would persist only
        // the valid declarations while the client clears the entire pending
        // group, making rejected design changes disappear from the editor.
        if ($rejected !== []) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'Some element styles could not be saved.',
                $rejected,
            );
        }

        if ($targetGroups === []) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Element,
                'No valid element style changes to save.',
                $rejected,
            );
        }

        // Resolve each target against the latest in-memory HTML. Promotions from
        // earlier targets are preserved before later targets are resolved.
        $html = $section->content()->html();
        $elementStyles = $section->content()->elementStyles();
        $targets = [];
        $promoted = false;

        foreach ($targetGroups as $targetKey => $group) {
            /** @var DesignStyleChange $target */
            $target = $group['target'];
            /** @var array<string, string> $declarations */
            $declarations = $group['declarations'];

            $shortcodeTarget = ShortcodeStyleTargetMaterializer::materialize($html, $target->selector(), $sectionId);
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
                    seed: $sectionId . '|' . $targetKey,
                    expectedTag: $target->tag(),
                );
            }

            if (!$selectorResult->isResolved() || $selectorResult->selector() === null) {
                return DesignStyleCommitResult::error(
                    DesignWriteScope::Element,
                    'Could not resolve a stable target for this element.',
                    [['property' => '', 'reason' => 'selector_unresolved']],
                );
            }

            $elementId = $this->elementIdFromSelector($selectorResult->selector());
            if ($elementId === null) {
                return DesignStyleCommitResult::error(
                    DesignWriteScope::Element,
                    'Could not resolve a stable target for this element.',
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
                'properties' => array_keys($declarations),
                'viewport'   => $target->viewport(),
                'state'      => $target->state(),
                'promoted'   => $selectorResult->wasPromoted(),
            ];
            $promoted = $promoted || $selectorResult->wasPromoted();
        }

        return [
            'update' => [
                'section_id'   => $sectionId,
                'section_name' => $section->name(),
                'content'      => [
                    'html'           => $html,
                    'css'            => $section->content()->css(),
                    'element_styles' => $elementStyles->toArray(),
                ],
            ],
            'applied'  => $applied,
            'targets'  => $targets,
            'promoted' => $promoted,
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
