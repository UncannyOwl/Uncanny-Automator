<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\GlobalPartSource;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiff;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiffer;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;

/**
 * Handles full replacement and surgical source edits for global parts.
 *
 * Explicit reusable IDs and assigned header/footer defaults remain distinct
 * resolution lanes. Dynamic-region masking, exact patching, CSS integrity,
 * and ambiguous persistence guidance stay inside the same write boundary.
 */
final class GlobalPartSourceController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly GlobalPartService $globalPartService,
        private readonly GlobalPartSourceResolver $parts,
        private readonly GlobalPartSourcePatcher $patcher,
        private readonly CompactSourceDiffer $sourceDiffer,
    ) {}

    public function update(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $partTypeValue = $this->parts->assignedTypeValue($request);
        $requestedPartType = $this->parts->parseAssignedType($partTypeValue);
        if ($requestedPartType === false) {
            return $this->invalidAssignedGlobalPartTypeError('edit_part', 'part_type', $partTypeValue);
        }

        $partType = $requestedPartType instanceof GlobalPartType ? $requestedPartType->value : '';
        $html = $request->get_param('html');
        $css = $request->get_param('css');
        $name = sanitize_text_field($request->get_param('name') ?? '');
        if (!is_string($html) || $html === '') {
            return $this->textToolError('edit_part', 400, 'missing_html', [
                'PART_TYPE: ' . $partType,
                'NEXT STEP',
                'Retry with full replacement HTML from read_part kind=global_part include=source.',
            ]);
        }
        if (!is_string($css)) {
            $css = '';
        }

        $globalPartId = $this->parts->requestId($request, null, $partType === '');
        try {
            $existing = $this->parts->resolve($request, $partType, $globalPartId);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('edit_part', $exception);
        }
        if ($existing === null) {
            return $this->textToolError('edit_part', 404, 'no_active_global_part', [
                'PART_TYPE: ' . $partType,
                'NEXT STEP',
                'Create or assign an active ' . $partType . ' global part before rewriting it.',
            ]);
        }

        $partType = $this->parts->resolvedType($existing, $partType);
        $gpType = GlobalPartType::fromString($partType);

        $postId = (int) ($existing['post_id'] ?? 0);
        if (!$this->permissions->canEditPost($postId)) {
            return $this->textToolError('edit_part', 403, 'global_part_edit_forbidden', [
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'NEXT STEP',
                'Use an account that can edit this global part.',
            ]);
        }

        $section = $this->parts->sourceSection($existing);
        if (!$section instanceof Section) {
            return $this->textToolError('edit_part', 404, 'no_global_part_source', [
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'NEXT STEP',
                'Create source content for this global part before rewriting it.',
            ]);
        }

        $title = $name !== '' ? $name : ($existing['title'] ?? $partType);
        $oldHtml = $section->content()->html();
        $oldCss = $section->content()->css();

        try {
            $result = $this->globalPartService->replaceLoadedSource(
                $postId,
                $existing,
                $title,
                ['name' => $title, 'content' => ['html' => $html, 'css' => $css]],
                $gpType,
            );
        } catch (SectionValidationException $exception) {
            return $this->textToolError('edit_part', 422, 'global_part_validation_failed', [
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Fix the replacement source and retry.',
            ]);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('edit_part', $exception);
        } catch (\RuntimeException $exception) {
            return $this->globalPartWriteError('edit_part', $partType, $postId, $exception);
        }

        $lines = [
            'TOOL: edit_part',
            'RESULT: success',
            'OPERATION: source_replace',
            'KIND: global_part',
            'PART_TYPE: ' . $partType,
            'POST_ID: ' . $postId,
            'TITLE: ' . (string) ($result['title'] ?? $title),
            '',
            'WARNING',
            'This is a full global part rewrite. Prefer edit_part kind=global_part mode=source_patch for surgical changes.',
            '',
        ];
        $this->appendWarningLines($lines, $result['warnings'] ?? []);
        $this->appendDiffLines($lines, 'HTML DIFF', $this->sourceDiffer->diff('HTML DIFF', $oldHtml, (string) ($result['html'] ?? $html)));
        $this->appendDiffLines($lines, 'CSS DIFF', $this->sourceDiffer->diff('CSS DIFF', $oldCss, (string) ($result['css'] ?? $css)));
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use read_part kind=global_part include=source to verify if needed.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    public function patchSource(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->patchRequest($request);
    }

    private function patchRequest(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $partTypeValue = $this->parts->assignedTypeValue($request);
        $requestedPartType = $this->parts->parseAssignedType($partTypeValue);
        if ($requestedPartType === false) {
            return $this->invalidAssignedGlobalPartTypeError('edit_part', 'part_type', $partTypeValue);
        }

        $partType = $requestedPartType instanceof GlobalPartType ? $requestedPartType->value : '';
        $globalPartId = $this->parts->requestId($request, null, $partType === '');
        try {
            $resolved = $this->parts->resolve($request, $partType, $globalPartId);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('edit_part', $exception);
        }
        if ($resolved === null) {
            return $this->textToolError('edit_part', 404, 'no_active_global_part', [
                'PART_TYPE: ' . $partType,
                'NEXT STEP',
                'Create or assign an active ' . $partType . ' global part before patching source.',
            ]);
        }

        $partType = $this->parts->resolvedType($resolved, $partType);
        $type = GlobalPartType::fromString($partType);

        $postId = (int) ($resolved['post_id'] ?? 0);
        if (!$this->permissions->canEditPost($postId)) {
            return $this->textToolError('edit_part', 403, 'global_part_edit_forbidden', [
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'NEXT STEP',
                'Use an account that can edit this global part.',
            ]);
        }

        $section = $this->parts->sourceSection($resolved);
        if (!$section instanceof Section) {
            return $this->textToolError('edit_part', 404, 'no_global_part_source', [
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'NEXT STEP',
                'Create source content for this global part before patching it.',
            ]);
        }

        $htmlPatches = (array) ($request->get_param('html_patches') ?? []);
        $cssPatches = (array) ($request->get_param('css_patches') ?? []);
        $cssRules = (array) ($request->get_param('css_rules') ?? []);
        [$cssPatches, $cssRules] = $this->patcher->normalizePayload($cssPatches, $cssRules);

        if ($htmlPatches === [] && $cssPatches === [] && $cssRules === []) {
            return $this->textToolError('edit_part', 400, 'missing_patches', [
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'NEXT STEP',
                'Retry with html_patches, css_patches, or css_rules.',
            ]);
        }

        [$cssRules, $cssRuleError] = $this->patcher->normalizeRules('edit_part', $cssRules, [
            'PART_TYPE: ' . $partType,
            'POST_ID: ' . $postId,
        ]);
        if ($cssRuleError instanceof \WP_REST_Response) {
            return $cssRuleError;
        }

        $oldHtml = $section->content()->html();
        $oldCss = $section->content()->css();

        [$newHtml, $error] = $this->patcher->applyHtml($oldHtml, $htmlPatches);
        if ($error !== null) {
            return $this->textToolError('edit_part', 422, 'source_patch_failed', [
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'PATCH_AREA: html',
                'DETAIL: ' . $error,
                'NEXT STEP',
                'Call read_part kind=global_part include=source and retry with html_patches search set to a non-empty exact current HTML substring that appears once. Never use search:""; use mode=source_replace for whole-source replacement.',
            ]);
        }

        [$newCss, $error] = $this->patcher->applyCss($oldCss, $cssPatches);
        if ($error !== null) {
            return $this->textToolError('edit_part', 422, 'source_patch_failed', [
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'PATCH_AREA: css',
                'DETAIL: ' . $error,
                'NEXT STEP',
                'Call read_part kind=global_part include=source and retry with css_patches search set to a non-empty exact current CSS substring that appears once. For selector changes, use css_rules or send search:"" only with a complete selector block; use mode=source_replace for whole-source replacement.',
            ]);
        }

        if ($cssRules !== []) {
            $patchedCss = $this->patcher->applyRules('edit_part', [
                'KIND: global_part',
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
            ], $newCss, $cssRules);
            if ($patchedCss instanceof \WP_REST_Response) {
                return $patchedCss;
            }
            $newCss = $patchedCss;
        }

        $title = (string) ($resolved['title'] ?? $partType);
        try {
            $result = $this->globalPartService->replaceLoadedSource(
                $postId,
                $resolved,
                $title,
                ['name' => $section->name(), 'content' => ['html' => $newHtml, 'css' => $newCss]],
                $type,
                requireExactCss: $cssPatches !== [] || $cssRules !== [],
            );
        } catch (SectionValidationException $exception) {
            return $this->textToolError('edit_part', 422, 'global_part_validation_failed', [
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Fix the replacement source and retry.',
            ]);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('edit_part', $exception);
        } catch (CssRuleIntegrityException $exception) {
            return $this->patcher->integrityError('edit_part', [
                'KIND: global_part',
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
            ], $exception);
        } catch (\RuntimeException $exception) {
            return $this->globalPartWriteError('edit_part', $partType, $postId, $exception);
        }

        $lines = [
            'TOOL: edit_part',
            'RESULT: success',
            'OPERATION: source_patch',
            'KIND: global_part',
            'PART_TYPE: ' . $partType,
            'POST_ID: ' . $postId,
            '',
            'APPLIED',
            'HTML_PATCHES: ' . count($htmlPatches),
            'CSS_PATCHES: ' . count($cssPatches),
            'CSS_RULES: ' . count($cssRules),
            '',
            'WARNING',
            'This writes normal global part source CSS. Do not use it to fight durable element styles.',
            '',
        ];
        $this->appendWarningLines($lines, $result['warnings'] ?? []);
        $this->appendDiffLines($lines, 'HTML DIFF', $this->sourceDiffer->diff(
            'HTML DIFF',
            $this->patcher->mask($oldHtml),
            $this->patcher->mask((string) ($result['html'] ?? $newHtml)),
        ));
        $this->appendDiffLines($lines, 'CSS DIFF', $this->sourceDiffer->diff(
            'CSS DIFF',
            $oldCss,
            (string) ($result['css'] ?? $newCss),
        ));
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use read_part kind=global_part include=source to verify if needed.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    // ---------------------------------------------------------------------
    // Global-part resolution
    // ---------------------------------------------------------------------

    private function invalidAssignedGlobalPartTypeError(
        string $toolName,
        string $fieldName,
        string $typeValue,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 400, 'invalid_part_type', [
            'KIND: global_part',
            'PART_TYPE: ' . ($typeValue !== '' ? $typeValue : 'missing'),
            'NEXT STEP',
            'Retry with global_part_id from the current reusable canvas, or ' . $fieldName . '=header or footer for assigned site defaults.',
        ]);
    }

    // ---------------------------------------------------------------------
    // Persistence and response boundaries
    // ---------------------------------------------------------------------

    private function globalPartWriteError(
        string $toolName,
        string $partType,
        int $postId,
        \RuntimeException $exception,
    ): \WP_REST_Response {
        $transactionError = $this->sourceTransactionUnavailableError($toolName, [
            'KIND: global_part',
            'PART_TYPE: ' . $partType,
            'POST_ID: ' . $postId,
        ], $exception);
        if ($transactionError instanceof \WP_REST_Response) {
            return $transactionError;
        }

        return $this->textToolError($toolName, 500, 'global_part_write_failed', [
            'KIND: global_part',
            'PART_TYPE: ' . $partType,
            'POST_ID: ' . $postId,
            'DETAIL: The global part write did not complete cleanly: ' . $exception->getMessage(),
            'RETRY_SAFETY: The source may already have been saved. Do not retry blindly.',
            'NEXT STEP',
            'Call read_part kind=global_part include=source first. If the requested change is present, do not retry. If it is absent, resolve the server error before retrying.',
        ]);
    }

    /**
     * @param list<string> $contextLines
     */
    private function sourceTransactionUnavailableError(
        string $toolName,
        array $contextLines,
        \RuntimeException $exception,
    ): ?\WP_REST_Response {
        if (!$exception instanceof SourceTransactionsUnavailableException) {
            return null;
        }

        return $this->textToolError($toolName, 500, 'source_transactions_unavailable', [
            ...$contextLines,
            'DETAIL: ' . $exception->getMessage(),
            'RETRY_SAFETY: Nothing was saved by this operation.',
            'NEXT STEP',
            'Convert the named database table to InnoDB. Then call read_part include=source again and retry against the current source.',
        ]);
    }

    /**
     * @param list<string> $lines
     */
    private function appendDiffLines(array &$lines, string $heading, CompactSourceDiff $diff): void
    {
        $lines[] = $heading;
        foreach (explode("\n", $diff->body()) as $line) {
            $lines[] = $line;
        }
        $lines[] = '';
    }

    /**
     * @param list<string> $lines
     * @param string[] $warnings
     */
    private function appendWarningLines(array &$lines, array $warnings): void
    {
        $warnings = array_values(array_unique(array_filter(
            array_map(static fn (mixed $warning): string => trim((string) $warning), $warnings),
        )));
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

    private function staleSourceToolError(
        string $toolName,
        StaleSourceGenerationException $exception,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 409, 'stale_source_generation', [
            'SCOPE: ' . $exception->scope(),
            'DETAIL: Page Builder source changed while this write was running.',
            'NEXT STEP',
            'Call read_page_context or read_part again, then reapply the change to the current source.',
        ]);
    }
}
