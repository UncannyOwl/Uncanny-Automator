<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiff;

/**
 * Owns the stable line-oriented response contract for section source patches.
 */
final class PatchResponseFormatter
{
    /** @param list<string> $lines */
    public function error(string $toolName, int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(\implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }

    /**
     * @param array<string, mixed> $saved
     * @param list<mixed> $htmlPatches
     * @param list<mixed> $cssPatches
     * @param list<mixed> $cssRules
     * @param array<string, mixed> $cssDeclarationReport
     */
    public function writeSuccess(
        int $pageId,
        array $saved,
        array $htmlPatches,
        array $cssPatches,
        array $cssRules,
        array $cssDeclarationReport = [],
    ): \WP_REST_Response {
        $lines = [
            'TOOL: edit_part',
            'RESULT: success',
            'OPERATION: source_patch',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . (string) ($saved['section_id'] ?? ''),
            '',
            'APPLIED',
            'HTML_PATCHES: ' . \count($htmlPatches),
            'CSS_PATCHES: ' . \count($cssPatches),
            'CSS_RULES: ' . \count($cssRules),
            '',
            'WARNING',
            'This writes normal source CSS. If the visual change does not appear, call read_part include=design_targets to inspect durable element styles.',
            '',
        ];
        $this->appendCssDeclarationReport($lines, $cssDeclarationReport);
        $this->appendWarnings($lines, $saved['warnings'] ?? []);
        $this->appendDiff($lines, 'HTML DIFF', $saved['html_diff']);
        $this->appendDiff($lines, 'CSS DIFF', $saved['css_diff']);
        $lines[] = 'NEXT STEP';

        return AgentTextResponse::ok(\implode("\n", $lines));
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     */
    public function appendCssDeclarationReport(array &$lines, array $report): void
    {
        $requested = (int) ($report['requested'] ?? 0);
        if ($requested === 0) {
            return;
        }

        $rejected = \array_values(\array_filter(
            (array) ($report['rejected'] ?? []),
            'is_array',
        ));
        $lines[] = 'CSS DECLARATIONS';
        $lines[] = 'CSS_DECLARATIONS_REQUESTED: ' . $requested;
        $lines[] = 'CSS_DECLARATIONS_APPLIED: ' . (int) ($report['applied'] ?? 0);
        $lines[] = 'CSS_DECLARATIONS_REJECTED: ' . \count($rejected);
        $lines[] = '';

        if ($rejected === []) {
            return;
        }

        $lines[] = 'REJECTED CSS DECLARATIONS';
        foreach ($rejected as $item) {
            $lines[] = '- RULE_INDEX: ' . (string) ($item['rule_index'] ?? '');
            $lines[] = '  SELECTOR: ' . (string) ($item['selector'] ?? '');
            $lines[] = '  PROPERTY: ' . (string) ($item['property'] ?? '');
            $lines[] = '  VALUE: ' . (string) ($item['value'] ?? '');
            $lines[] = '  REASON: ' . (string) ($item['reason'] ?? '');
        }
        $lines[] = '';
    }

    /**
     * @param list<string> $lines
     * @param mixed $warnings
     */
    public function appendWarnings(array &$lines, mixed $warnings): void
    {
        $warnings = \array_values(\array_unique(\array_filter(\array_map(
            static fn (mixed $warning): string => \trim((string) $warning),
            \is_array($warnings) ? $warnings : [],
        ))));
        if ($warnings === []) {
            return;
        }

        $lines[] = 'WARNING';
        foreach ($warnings as $warning) {
            $lines[] = $warning;
        }
        $lines[] = '';
    }

    /**
     * @param list<string> $lines
     */
    public function appendDiff(array &$lines, string $heading, CompactSourceDiff $diff): void
    {
        $lines[] = $heading;
        foreach (\explode("\n", $diff->body()) as $line) {
            $lines[] = $line;
        }
        $lines[] = '';
    }
}
