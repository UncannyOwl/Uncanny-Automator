<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleProperty;
use UncannyPageBuilder\Infrastructure\Section\CssRulePatcher;

/**
 * Validates model-authored css_rules for section and reusable edits.
 *
 * Both edit paths share one rule contract: the same accepted properties,
 * duplicate handling, rejection reasons, and invalid_css_rule response.
 */
final class CssRuleSetNormalizer
{
    public function __construct(
        private readonly CssRulePatcher $cssRulePatcher,
    ) {}

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
        $report = ['requested' => 0, 'applied' => 0, 'rejected' => []];
        if ($rules === []) {
            return [[], null, $report];
        }

        $normalized = [];
        foreach ($rules as $index => $rule) {
            if (!\is_array($rule)) {
                return [[], $this->invalidCssRuleResponse(
                    $toolName,
                    $contextLines,
                    $index,
                    'Each css_rules item must be an object.',
                ), $report];
            }

            $selector = \trim((string) ($rule['selector'] ?? ''));
            $rawSet = $rule['set'] ?? ($rule['declarations'] ?? null);
            if ($selector === '' || !\is_array($rawSet)) {
                return [[], $this->invalidCssRuleResponse(
                    $toolName,
                    $contextLines,
                    $index,
                    'Provide selector and set/declarations properties.',
                ), $report];
            }
            if (!$this->cssRulePatcher->isSafeSelector($selector)) {
                return [[], $this->invalidCssRuleResponse(
                    $toolName,
                    $contextLines,
                    $index,
                    'Selector contains unsupported or structural CSS syntax.',
                ), $report];
            }

            $set = [];
            $seenProperties = [];
            foreach ($rawSet as $property => $value) {
                $report['requested']++;
                $reportedProperty = \is_string($property) ? \trim($property) : (string) $property;
                $reportedValue = \is_scalar($value) ? (string) $value : \get_debug_type($value);
                if (!\is_string($property)) {
                    $report['rejected'][] = $this->rejectedDeclaration(
                        $index,
                        $selector,
                        $reportedProperty,
                        $reportedValue,
                        'invalid_property_name',
                    );
                    continue;
                }
                if (!\is_string($value) && !\is_numeric($value)) {
                    $report['rejected'][] = $this->rejectedDeclaration(
                        $index,
                        $selector,
                        $reportedProperty,
                        $reportedValue,
                        'value_must_be_string_or_number',
                    );
                    continue;
                }

                $property = \strtolower(\trim($property));
                $value = \trim((string) $value);
                if (isset($seenProperties[$property])) {
                    $report['rejected'][] = $this->rejectedDeclaration(
                        $index,
                        $selector,
                        $property,
                        $value,
                        'duplicate_property',
                    );
                    continue;
                }
                $seenProperties[$property] = true;
                if (!DesignStyleProperty::isAllowed($property)) {
                    $report['rejected'][] = $this->rejectedDeclaration(
                        $index,
                        $selector,
                        $property,
                        $value,
                        'unsupported_property',
                    );
                    continue;
                }
                if (!$this->cssRulePatcher->isSafeDeclarationValue($value)) {
                    $report['rejected'][] = $this->rejectedDeclaration(
                        $index,
                        $selector,
                        $property,
                        $value,
                        'unsafe_value',
                    );
                    continue;
                }

                $set[$property] = $value;
                $report['applied']++;
            }

            if ($set === []) {
                return [[], $this->invalidCssRuleResponse(
                    $toolName,
                    $contextLines,
                    $index,
                    'No supported CSS declarations remained after validation.',
                ), $report];
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
                    ), $report];
                }
                $normalizedRule['media'] = $media;
            }

            $normalized[] = $normalizedRule;
        }

        return [$normalized, null, $report];
    }

    /**
     * @return array{rule_index: int|string, selector: string, property: string, value: string, reason: string}
     */
    private function rejectedDeclaration(
        int|string $ruleIndex,
        string $selector,
        string $property,
        string $value,
        string $reason,
    ): array {
        return [
            'rule_index' => $ruleIndex,
            'selector' => $selector,
            'property' => $property,
            'value' => $value,
            'reason' => $reason,
        ];
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
