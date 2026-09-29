<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\GlobalPartSource;

use UncannyPageBuilder\Api\AgentPageController\CssRuleSetNormalizer;
use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Editing\ExactSourcePatcher;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Infrastructure\Section\CssRulePatcher;
use UncannyPageBuilder\Infrastructure\Section\DynamicRegionToken;

/**
 * Applies exact Agent-authored source patches without weakening mask or CSS
 * integrity boundaries.
 */
final class GlobalPartSourcePatcher
{
    /** @var list<string>|null */
    private ?array $maskableBindingIds = null;

    private readonly CssRuleSetNormalizer $ruleNormalizer;

    public function __construct(
        private readonly CssRulePatcher $cssRulePatcher,
        private readonly ExactSourcePatcher $sourcePatcher,
        private readonly ?BindingRegistry $bindingRegistry = null,
    ) {
        $this->ruleNormalizer = new CssRuleSetNormalizer($cssRulePatcher);
    }

    public function mask(string $html): string
    {
        $this->maskableBindingIds ??= $this->bindingRegistry?->fullyProjectedBindingIds() ?? [];

        return DynamicRegionToken::encodeForCodeEditor(
            $html,
            $this->maskableBindingIds,
            payloadMasks: false,
        );
    }

    /**
     * @param array<int, mixed> $patches
     * @return array{0: string, 1: mixed}
     */
    public function applyHtml(string $storedHtml, array $patches): array
    {
        $masked = $this->mask($storedHtml);
        [$patched, $error] = $this->sourcePatcher->apply($masked, $patches, 'html');

        if ($error === null) {
            $violation = DynamicRegionToken::findAtomicityViolation($masked, $patched);
            if ($violation !== null) {
                return [$storedHtml, $violation];
            }
        }

        return [DynamicRegionToken::decode($patched), $error];
    }

    /**
     * @param array<int, mixed> $patches
     * @return array{0: string, 1: mixed}
     */
    public function applyCss(string $storedCss, array $patches): array
    {
        return $this->sourcePatcher->apply($storedCss, $patches, 'css');
    }

    /**
     * @param array<int, mixed> $cssPatches
     * @param array<int, mixed> $cssRules
     * @return array{0: list<mixed>, 1: list<mixed>}
     */
    public function normalizePayload(array $cssPatches, array $cssRules): array
    {
        $remainingPatches = [];
        $normalizedRules = array_values($cssRules);

        foreach ($cssPatches as $patch) {
            if (!is_array($patch) || trim($this->patchSearchText($patch)) !== '') {
                $remainingPatches[] = $patch;
                continue;
            }

            $promotedRules = $this->cssRulesFromPatchReplacement($patch);
            if ($promotedRules === [] && $normalizedRules === []) {
                $remainingPatches[] = $patch;
                continue;
            }

            array_push($normalizedRules, ...$promotedRules);
        }

        return [$remainingPatches, $normalizedRules];
    }

    /**
     * @param array<int, mixed> $rules
     * @param list<string> $contextLines
     * @return array{
     *     0: list<array<string, mixed>>,
     *     1: \WP_REST_Response|null,
     *     2: array{
     *         requested: int,
     *         applied: int,
     *         rejected: list<array{rule_index: int|string, selector: string, property: string, value: string, reason: string}>
     *     }
     * }
     */
    public function normalizeRules(string $toolName, array $rules, array $contextLines = []): array
    {
        return $this->ruleNormalizer->normalizeRules($toolName, $rules, $contextLines);
    }

    /**
     * @param list<string> $contextLines
     * @param list<array<string, mixed>> $rules
     */
    public function applyRules(
        string $toolName,
        array $contextLines,
        string $css,
        array $rules,
    ): string|\WP_REST_Response {
        try {
            return $this->cssRulePatcher->apply($css, $rules);
        } catch (CssRuleIntegrityException $exception) {
            return $this->integrityError($toolName, $contextLines, $exception);
        }
    }

    /**
     * @param list<string> $contextLines
     */
    public function integrityError(
        string $toolName,
        array $contextLines,
        ?CssRuleIntegrityException $exception = null,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 422, 'css_rule_integrity_failed', [
            ...$contextLines,
            'DETAIL: ' . ($exception?->getMessage() ?? 'WordPress would rewrite CSS outside the requested declaration change. Nothing was saved.'),
            'NEXT STEP',
            $this->integrityNextStep($exception),
        ]);
    }

    /** @param array<string, mixed> $patch */
    private function patchSearchText(array $patch): string
    {
        foreach (['search', 'old', 'old_string'] as $key) {
            if (array_key_exists($key, $patch)) {
                $value = (string) $patch[$key];

                return $key === 'search'
                    ? $value
                    : str_replace(['\n', '\t'], ["\n", "\t"], $value);
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
            if (array_key_exists($key, $patch)) {
                $css = (string) $patch[$key];
                if (in_array($key, ['new', 'new_string'], true)) {
                    $css = str_replace(['\n', '\t'], ["\n", "\t"], $css);
                }
                break;
            }
        }

        $css = trim($css);

        return $css === '' ? [] : $this->cssRulesFromCssBlocks($css);
    }

    /**
     * @return list<array{selector: string, set: array<string, string>}>
     */
    private function cssRulesFromCssBlocks(string $css): array
    {
        $matchCount = preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_OFFSET_CAPTURE);
        if ($matchCount === false || $matchCount < 1) {
            return [];
        }

        $rules = [];
        $cursor = 0;
        foreach ($matches[0] as $index => $match) {
            $start = (int) $match[1];
            if (trim(substr($css, $cursor, $start - $cursor)) !== '') {
                return [];
            }

            $selector = trim((string) $matches[1][$index][0]);
            $set = $this->cssDeclarationSet((string) $matches[2][$index][0]);
            if ($selector === '' || str_starts_with($selector, '@') || $set === []) {
                return [];
            }

            $rules[] = ['selector' => $selector, 'set' => $set];
            $cursor = $start + strlen((string) $match[0]);
        }

        return trim(substr($css, $cursor)) === '' ? $rules : [];
    }

    /** @return array<string, string> */
    private function cssDeclarationSet(string $block): array
    {
        $set = [];
        foreach (array_filter(array_map('trim', explode(';', $block))) as $declaration) {
            $colon = strpos($declaration, ':');
            if ($colon === false) {
                return [];
            }

            $property = trim(substr($declaration, 0, $colon));
            $value = trim(substr($declaration, $colon + 1));
            if ($property === '' || $value === '') {
                return [];
            }

            $set[$property] = $value;
        }

        return $set;
    }

    private function integrityNextStep(?CssRuleIntegrityException $exception): string
    {
        return match ($exception?->reason()) {
            CssRuleIntegrityException::MALFORMED_SOURCE => 'Call read_part include=source, repair the unbalanced CSS with mode=source_replace, then retry the css_rule edit.',
            CssRuleIntegrityException::AMBIGUOUS_COMMENT => 'Call read_part include=source and use mode=source_patch with an exact current substring so the comment and intended replacement are both explicit.',
            CssRuleIntegrityException::AMBIGUOUS_DECLARATION_BOUNDARY => 'Call read_part include=source and repair the declaration boundary with mode=source_patch or mode=source_replace before retrying css_rule.',
            CssRuleIntegrityException::MULTIPLE_GLOBAL_PART_SOURCE_ROWS => 'Call read_part kind=global_part include=source. Migrate or explicitly consolidate every stored source row before retrying the write.',
            CssRuleIntegrityException::UNPRESERVABLE_GLOBAL_PART_SOURCE_ROWS => 'Ask an administrator to repair or explicitly consolidate the stored legacy global-part rows before retrying the write.',
            default => 'Call read_part include=source again. Preserve or explicitly repair the rejected CSS with mode=source_replace before retrying the requested edit.',
        };
    }

    /**
     * @param list<string> $lines
     */
    private function textToolError(
        string $toolName,
        int $status,
        string $code,
        array $lines,
    ): \WP_REST_Response {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }
}
