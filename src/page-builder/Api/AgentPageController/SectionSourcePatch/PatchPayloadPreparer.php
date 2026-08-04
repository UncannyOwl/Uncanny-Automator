<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleProperty;
use UncannyPageBuilder\Domain\Editing\ExactSourcePatcher;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Infrastructure\Section\CssRulePatcher;
use UncannyPageBuilder\Infrastructure\Section\DynamicRegionToken;

/**
 * Validates and applies model-authored HTML/CSS patch payloads in memory.
 *
 * Dynamic regions remain atomic because HTML edits run against the same masked
 * representation returned by Agent read tools.
 */
final class PatchPayloadPreparer
{
    /** @var list<string>|null */
    private ?array $maskableBindingIds = null;

    public function __construct(
        private readonly ExactSourcePatcher $sourcePatcher,
        private readonly CssRulePatcher $cssRulePatcher,
        private readonly ?BindingRegistry $bindingRegistry = null,
    ) {}

    /**
     * @param array<int, mixed> $cssPatches
     * @param array<int, mixed> $cssRules
     * @return array{0: list<mixed>, 1: list<mixed>}
     */
    public function normalizePayload(array $cssPatches, array $cssRules): array
    {
        $remainingPatches = [];
        $normalizedRules = \array_values($cssRules);

        foreach ($cssPatches as $patch) {
            if (!\is_array($patch) || \trim($this->patchSearchText($patch)) !== '') {
                $remainingPatches[] = $patch;
                continue;
            }

            $promotedRules = $this->cssRulesFromPatchReplacement($patch);
            if ($promotedRules === [] && $normalizedRules === []) {
                $remainingPatches[] = $patch;
                continue;
            }

            \array_push($normalizedRules, ...$promotedRules);
        }

        return [$remainingPatches, $normalizedRules];
    }

    /**
     * @param array<int, mixed> $rules
     * @param list<string> $contextLines
     * @return array{0: list<array<string, mixed>>, 1: \WP_REST_Response|null}
     */
    public function normalizeRules(string $toolName, array $rules, array $contextLines = []): array
    {
        if ($rules === []) {
            return [[], null];
        }

        $normalized = [];
        foreach ($rules as $index => $rule) {
            if (!\is_array($rule)) {
                return [[], $this->invalidCssRuleResponse(
                    $toolName,
                    $contextLines,
                    $index,
                    'Each css_rules item must be an object.',
                )];
            }

            $selector = \trim((string) ($rule['selector'] ?? ''));
            $rawSet = $rule['set'] ?? ($rule['declarations'] ?? null);
            if ($selector === '' || !\is_array($rawSet)) {
                return [[], $this->invalidCssRuleResponse(
                    $toolName,
                    $contextLines,
                    $index,
                    'Provide selector and set/declarations properties.',
                )];
            }
            if (!$this->cssRulePatcher->isSafeSelector($selector)) {
                return [[], $this->invalidCssRuleResponse(
                    $toolName,
                    $contextLines,
                    $index,
                    'Selector contains unsupported or structural CSS syntax.',
                )];
            }

            $set = [];
            foreach ($rawSet as $property => $value) {
                if (!\is_string($property) || (!\is_string($value) && !\is_numeric($value))) {
                    continue;
                }

                $property = \strtolower(\trim($property));
                $value = \trim((string) $value);
                if (
                    !DesignStyleProperty::isAllowed($property)
                    || !$this->cssRulePatcher->isSafeDeclarationValue($value)
                ) {
                    continue;
                }

                $set[$property] = $value;
            }

            if ($set === []) {
                return [[], $this->invalidCssRuleResponse(
                    $toolName,
                    $contextLines,
                    $index,
                    'No supported CSS declarations remained after validation.',
                )];
            }

            $normalizedRule = [
                'selector' => $selector,
                'set' => $set,
            ];

            $media = isset($rule['media']) && \is_string($rule['media']) ? \trim($rule['media']) : '';
            if ($media !== '') {
                if (!$this->cssRulePatcher->isSafeMediaPrelude($media)) {
                    return [[], $this->invalidCssRuleResponse(
                        $toolName,
                        $contextLines,
                        $index,
                        'Media must be one safe @media prelude without a rule body.',
                    )];
                }
                $normalizedRule['media'] = $media;
            }

            $normalized[] = $normalizedRule;
        }

        return [$normalized, null];
    }

    /**
     * @param list<array<string, mixed>> $htmlPatches
     * @param list<array<string, mixed>> $cssPatches
     * @param list<array<string, mixed>> $cssRules
     * @param list<string> $cssContextLines
     * @return array{html: string, css: string}|\WP_REST_Response
     */
    public function prepare(
        string $toolName,
        Section $section,
        array $htmlPatches,
        array $cssPatches,
        array $cssRules,
        array $cssContextLines,
        bool $rulesAreNormalized = false,
    ): array|\WP_REST_Response {
        [$newHtml, $error] = $this->patchInMaskSpace(
            $section->content()->html(),
            fn (string $maskedHtml): array => $this->sourcePatcher->apply($maskedHtml, $htmlPatches, 'html'),
        );
        if ($error !== null) {
            return $this->textToolError($toolName, 422, 'source_patch_failed', [
                'SECTION_ID: ' . (string) $section->id(),
                'PATCH_AREA: html',
                'DETAIL: ' . $error,
                'NEXT STEP',
                'Call read_part include=source and retry with html_patches search set to a non-empty exact current HTML substring that appears once. Never use search:""; use mode=source_replace for whole-source replacement.',
            ]);
        }

        [$newCss, $error] = $this->sourcePatcher->apply(
            $section->content()->css(),
            $cssPatches,
            'css',
        );
        if ($error !== null) {
            return $this->textToolError($toolName, 422, 'source_patch_failed', [
                'SECTION_ID: ' . (string) $section->id(),
                'PATCH_AREA: css',
                'DETAIL: ' . $error,
                'NEXT STEP',
                'Call read_part include=source and retry with css_patches search set to a non-empty exact current CSS substring that appears once. For selector changes, use css_rules or send search:"" only with a complete selector block; use mode=source_replace for whole-source replacement.',
            ]);
        }

        if ($cssRules !== []) {
            if (!$rulesAreNormalized) {
                [$cssRules, $cssRuleError] = $this->normalizeRules(
                    $toolName,
                    $cssRules,
                    ['SECTION_ID: ' . (string) $section->id()],
                );
                if ($cssRuleError instanceof \WP_REST_Response) {
                    return $cssRuleError;
                }
            }

            try {
                $newCss = $this->cssRulePatcher->apply($newCss, $cssRules);
            } catch (CssRuleIntegrityException $exception) {
                return $this->cssRuleIntegrityError($toolName, $cssContextLines, $exception);
            }
        }

        return [
            'html' => $newHtml,
            'css' => $newCss,
        ];
    }

    /** @param array<string, mixed> $patch */
    private function patchSearchText(array $patch): string
    {
        foreach (['search', 'old', 'old_string'] as $key) {
            if (\array_key_exists($key, $patch)) {
                return \str_replace(['\n', '\t'], ["\n", "\t"], (string) $patch[$key]);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $patch
     * @return list<array{selector: string, set: array<string, string>}>
     */
    private function cssRulesFromPatchReplacement(array $patch): array
    {
        $css = '';
        foreach (['replace', 'new', 'new_string', 'content'] as $key) {
            if (\array_key_exists($key, $patch)) {
                $css = \str_replace(['\n', '\t'], ["\n", "\t"], (string) $patch[$key]);
                break;
            }
        }

        $css = \trim($css);
        if ($css === '') {
            return [];
        }

        return $this->cssRulesFromCssBlocks($css);
    }

    /**
     * @return list<array{selector: string, set: array<string, string>}>
     */
    private function cssRulesFromCssBlocks(string $css): array
    {
        $matchCount = \preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_OFFSET_CAPTURE);
        if ($matchCount === false || $matchCount < 1) {
            return [];
        }

        $rules = [];
        $cursor = 0;
        foreach ($matches[0] as $index => $match) {
            $start = (int) $match[1];
            if (\trim(\substr($css, $cursor, $start - $cursor)) !== '') {
                return [];
            }

            $selector = \trim((string) $matches[1][$index][0]);
            $set = $this->cssDeclarationSet((string) $matches[2][$index][0]);
            if ($selector === '' || \str_starts_with($selector, '@') || $set === []) {
                return [];
            }

            $rules[] = [
                'selector' => $selector,
                'set' => $set,
            ];
            $cursor = $start + \strlen((string) $match[0]);
        }

        if (\trim(\substr($css, $cursor)) !== '') {
            return [];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function cssDeclarationSet(string $block): array
    {
        $set = [];
        foreach (\array_filter(\array_map('trim', \explode(';', $block))) as $declaration) {
            $colon = \strpos($declaration, ':');
            if ($colon === false) {
                return [];
            }

            $property = \trim(\substr($declaration, 0, $colon));
            $value = \trim(\substr($declaration, $colon + 1));
            if ($property === '' || $value === '') {
                return [];
            }

            $set[$property] = $value;
        }

        return $set;
    }

    /**
     * @param callable(string): array{0: string, 1: mixed} $patchOperation
     * @return array{0: string, 1: mixed}
     */
    private function patchInMaskSpace(string $storedHtml, callable $patchOperation): array
    {
        $masked = $this->maskForAgent($storedHtml);
        [$patched, $error] = $patchOperation($masked);

        if ($error === null) {
            $violation = DynamicRegionToken::findAtomicityViolation($masked, $patched);
            if ($violation !== null) {
                return [$storedHtml, $violation];
            }
        }

        return [DynamicRegionToken::decode($patched), $error];
    }

    private function maskForAgent(string $html): string
    {
        $this->maskableBindingIds ??= $this->bindingRegistry?->fullyProjectedBindingIds() ?? [];

        return DynamicRegionToken::encodeForCodeEditor(
            $html,
            $this->maskableBindingIds,
            payloadMasks: false,
        );
    }

    /** @param list<string> $contextLines */
    private function cssRuleIntegrityError(
        string $toolName,
        array $contextLines,
        CssRuleIntegrityException $exception,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 422, 'css_rule_integrity_failed', [
            ...$contextLines,
            'DETAIL: ' . $exception->getMessage(),
            'NEXT STEP',
            match ($exception->reason()) {
                CssRuleIntegrityException::MALFORMED_SOURCE => 'Call read_part include=source, repair the unbalanced CSS with mode=source_replace, then retry the css_rule edit.',
                CssRuleIntegrityException::AMBIGUOUS_COMMENT => 'Call read_part include=source and use mode=source_patch with an exact current substring so the comment and intended replacement are both explicit.',
                CssRuleIntegrityException::AMBIGUOUS_DECLARATION_BOUNDARY => 'Call read_part include=source and repair the declaration boundary with mode=source_patch or mode=source_replace before retrying css_rule.',
                CssRuleIntegrityException::MULTIPLE_GLOBAL_PART_SOURCE_ROWS => 'Call read_part kind=global_part include=source. Migrate or explicitly consolidate every stored source row before retrying the write.',
                CssRuleIntegrityException::UNPRESERVABLE_GLOBAL_PART_SOURCE_ROWS => 'Ask an administrator to repair or explicitly consolidate the stored legacy global-part rows before retrying the write.',
                default => 'Call read_part include=source again. Preserve or explicitly repair the rejected CSS with mode=source_replace before retrying the requested edit.',
            },
        ]);
    }

    /**
     * @param list<string> $contextLines
     */
    private function invalidCssRuleResponse(
        string $toolName,
        array $contextLines,
        int|string $index,
        string $detail,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 422, 'invalid_css_rule', [
            ...$contextLines,
            'RULE_INDEX: ' . (string) $index,
            'DETAIL: ' . $detail,
            'NEXT STEP',
            'Retry css_rules with selector and set/declarations, for example {"selector":".card","set":{"color":"#111"}}.',
        ]);
    }

    /** @param list<string> $lines */
    private function textToolError(
        string $toolName,
        int $status,
        string $code,
        array $lines,
    ): \WP_REST_Response {
        return AgentTextResponse::withStatus(\implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }
}
