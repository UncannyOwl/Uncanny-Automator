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
     */
    public function writeSuccess(
        int $pageId,
        array $saved,
        array $htmlPatches,
        array $cssPatches,
        array $cssRules,
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
        $this->appendDiff($lines, 'HTML DIFF', $saved['html_diff']);
        $this->appendDiff($lines, 'CSS DIFF', $saved['css_diff']);
        $lines[] = 'NEXT STEP';

        return AgentTextResponse::ok(\implode("\n", $lines));
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
