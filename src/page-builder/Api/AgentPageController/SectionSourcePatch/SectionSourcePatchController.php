<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch;

use UncannyPageBuilder\Api\AgentTextResponse;

/**
 * Orchestrates exact section source patch writes and previews.
 */
final class SectionSourcePatchController
{
    public function __construct(
        private readonly PatchPayloadPreparer $patches,
        private readonly SectionSourceWriter $source,
        private readonly PreviewStyleWarning $previewStyleWarning,
        private readonly PatchResponseFormatter $responses,
    ) {}

    public function patchSource(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->writePatch($request);
    }

    public function previewChange(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $mode = \trim((string) ($request->get_param('mode') ?? 'source_patch'));
        if ($mode !== 'source_patch') {
            return $this->responses->error('preview_change', 400, 'unsupported_mode', [
                'MODE: ' . $mode,
                'NEXT STEP',
                'Retry with mode source_patch.',
            ]);
        }

        try {
            return $this->previewSourcePatch($request, 'preview_change');
        } catch (\Throwable $failure) {
            error_log(sprintf('[Uncanny Page Builder] preview_change failed (%s).', $failure::class));

            return $this->responses->error('preview_change', 500, 'preview_failed', [
                'NEXT STEP',
                'A preview saves nothing. Retry preview_change. If the error continues, review the WordPress error log.',
            ]);
        }
    }

    private function writePatch(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$section, $pageId, $error] = $this->source->resolve($request);
        if ($error) {
            return $error;
        }

        $htmlPatches = (array) ($request->get_param('html_patches') ?? []);
        $cssPatches = (array) ($request->get_param('css_patches') ?? []);
        $cssRules = (array) ($request->get_param('css_rules') ?? []);
        [$cssPatches, $cssRules] = $this->patches->normalizePayload($cssPatches, $cssRules);

        if ($htmlPatches === [] && $cssPatches === [] && $cssRules === []) {
            return $this->responses->error('edit_part', 400, 'missing_patches', [
                'SECTION_ID: ' . (string) $section->id(),
                'NEXT STEP',
                'Retry with html_patches, css_patches, css_rules, or use a target-specific tool instead.',
            ]);
        }

        [$cssRules, $cssRuleError] = $this->patches->normalizeRules('edit_part', $cssRules, [
            'SECTION_ID: ' . (string) $section->id(),
        ]);
        if ($cssRuleError instanceof \WP_REST_Response) {
            return $cssRuleError;
        }

        $prepared = $this->patches->prepare(
            toolName: 'edit_part',
            section: $section,
            htmlPatches: $htmlPatches,
            cssPatches: $cssPatches,
            cssRules: $cssRules,
            cssContextLines: [
                'PAGE_ID: ' . $pageId,
                'SECTION_ID: ' . (string) $section->id(),
            ],
            rulesAreNormalized: true,
        );
        if ($prepared instanceof \WP_REST_Response) {
            return $prepared;
        }

        $saved = $this->source->save(
            toolName: 'edit_part',
            pageId: $pageId,
            section: $section,
            html: $prepared['html'],
            css: $prepared['css'],
            requireExactCss: $cssPatches !== [] || $cssRules !== [],
        );
        if ($saved instanceof \WP_REST_Response || $saved instanceof \WP_Error) {
            return $saved;
        }

        return $this->responses->writeSuccess(
            $pageId,
            $saved,
            $htmlPatches,
            $cssPatches,
            $cssRules,
        );
    }

    private function previewSourcePatch(
        \WP_REST_Request $request,
        string $toolName,
    ): \WP_REST_Response|\WP_Error {
        [$section, $pageId, $error] = $this->source->resolve($request);
        if ($error) {
            return $error;
        }

        $htmlPatches = (array) ($request->get_param('html_patches') ?? []);
        $cssPatches = (array) ($request->get_param('css_patches') ?? []);
        $cssRules = (array) ($request->get_param('css_rules') ?? []);
        [$cssPatches, $cssRules] = $this->patches->normalizePayload($cssPatches, $cssRules);

        if ($htmlPatches === [] && $cssPatches === [] && $cssRules === []) {
            return $this->responses->error($toolName, 400, 'missing_patches', [
                'SECTION_ID: ' . (string) $section->id(),
                'NEXT STEP',
                'Retry with html_patches, css_patches, css_rules, or use a target-specific tool instead.',
            ]);
        }

        $prepared = $this->patches->prepare(
            toolName: $toolName,
            section: $section,
            htmlPatches: $htmlPatches,
            cssPatches: $cssPatches,
            cssRules: $cssRules,
            cssContextLines: ['SECTION_ID: ' . (string) $section->id()],
        );
        if ($prepared instanceof \WP_REST_Response) {
            return $prepared;
        }

        $preview = $this->source->preview(
            toolName: $toolName,
            pageId: $pageId,
            section: $section,
            html: $prepared['html'],
            css: $prepared['css'],
            requireExactCss: $cssPatches !== [] || $cssRules !== [],
        );
        if ($preview instanceof \WP_REST_Response || $preview instanceof \WP_Error) {
            return $preview;
        }

        $lines = [
            'TOOL: ' . $toolName,
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . (string) $section->id(),
            '',
            'VALIDATION',
            'passed',
            '',
            'APPLIED',
            'HTML_PATCHES: ' . \count($htmlPatches),
            'CSS_PATCHES: ' . \count($cssPatches),
            'CSS_RULES: ' . \count($cssRules),
            '',
        ];
        $this->responses->appendDiff($lines, 'HTML DIFF', $preview['html_diff']);
        $this->responses->appendDiff($lines, 'CSS DIFF', $preview['css_diff']);
        $this->previewStyleWarning->append($lines, $section, $cssRules, $toolName);
        $lines[] = 'NEXT STEP';
        $lines[] = 'If this diff matches the user request, call edit_part mode=source_patch with the same payload.';

        return AgentTextResponse::ok(\implode("\n", $lines));
    }
}
