<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiff;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiffer;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Handles the Agent-facing custom JavaScript runtime tools.
 *
 * The root AgentPageController keeps the stable WordPress REST callbacks. This
 * collaborator owns runtime request validation, authorization, persistence,
 * previewing, and the line-oriented Agent response contract.
 */
final class RuntimeController
{
    public function __construct(
        private readonly SectionRepositoryInterface $sections,
        private readonly PermissionChecker $permissions,
        private readonly GlobalPartService $globalParts,
        private readonly CompactSourceDiffer $sourceDiffer,
        private readonly ?PageJavaScriptRuntimeService $javaScript = null,
        private readonly ?ToolSettingsAccess $settings = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return $this->readRequest($request);
        } catch (\Throwable $failure) {
            $this->recordBoundaryFailure('read', $failure);
            return $this->textToolError('read_runtime', 500, 'runtime_read_failed', [
                'NEXT STEP',
                'Retry read_runtime. If the error continues, review the WordPress error log.',
            ]);
        }
    }

    private function readRequest(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!$this->javaScript instanceof PageJavaScriptRuntimeService) {
            return $this->textToolError('read_runtime', 501, 'runtime_lane_unavailable', [
                'NEXT STEP',
                'The custom JavaScript lane is not available on this site yet.',
            ]);
        }

        $scope = $this->runtimeScope($request);
        if ($scope === null) {
            return $this->textToolError('read_runtime', 400, 'invalid_runtime_scope', [
                'NEXT STEP',
                'Retry with scope=page or scope=global_part.',
            ]);
        }

        if ($scope === 'page') {
            $pageId = $this->requestPageId($request);
            if ($pageId <= 0) {
                return $this->textToolError('read_runtime', 400, 'missing_page_id', [
                    'RUNTIME SCOPE: page',
                    'NEXT STEP',
                    'Retry with a valid page_id.',
                ]);
            }
            if (!$this->permissions->canEditPage($pageId)) {
                return $this->textToolError('read_runtime', 403, 'page_edit_forbidden', [
                    'RUNTIME SCOPE: page',
                    'PAGE_ID: ' . $pageId,
                    'NEXT STEP',
                    'Use an account that can edit this page.',
                ]);
            }
            if (!$this->sections->isOwnedPage($pageId)) {
                return $this->textToolError('read_runtime', 404, 'page_not_owned', [
                    'RUNTIME SCOPE: page',
                    'PAGE_ID: ' . $pageId,
                    'NEXT STEP',
                    'Retry with an Uncanny Page Builder-owned page.',
                ]);
            }

            return AgentTextResponse::ok(implode("\n", $this->runtimeReadLines(
                'page',
                $pageId,
                $this->javaScript->readForPage($pageId),
            )));
        }

        $globalPartId = $this->requestGlobalPartId($request);
        if ($globalPartId <= 0) {
            return $this->textToolError('read_runtime', 400, 'missing_global_part_id', [
                'RUNTIME SCOPE: global_part',
                'NEXT STEP',
                'Retry with a valid global_part_id.',
            ]);
        }

        $resolved = $this->globalParts->findById($globalPartId);
        if ($resolved === null) {
            return $this->textToolError('read_runtime', 404, 'global_part_not_found', [
                'RUNTIME SCOPE: global_part',
                'GLOBAL_PART_ID: ' . $globalPartId,
                'NEXT STEP',
                'Retry with an existing reusable global part.',
            ]);
        }
        if (!$this->permissions->canEditPost($globalPartId)) {
            return $this->textToolError('read_runtime', 403, 'global_part_edit_forbidden', [
                'RUNTIME SCOPE: global_part',
                'GLOBAL_PART_ID: ' . $globalPartId,
                'NEXT STEP',
                'Use an account that can edit this global part.',
            ]);
        }

        return AgentTextResponse::ok(implode("\n", $this->runtimeReadLines(
            'global_part',
            $globalPartId,
            $this->javaScript->readForGlobalPart($globalPartId),
        )));
    }

    public function edit(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!$this->javaScript instanceof PageJavaScriptRuntimeService) {
            return $this->textToolError('edit_runtime', 501, 'runtime_lane_unavailable', [
                'NEXT STEP',
                'The custom JavaScript lane is not available on this site yet.',
            ]);
        }

        $scope = $this->runtimeScope($request);
        if ($scope === null) {
            return $this->textToolError('edit_runtime', 400, 'invalid_runtime_scope', [
                'NEXT STEP',
                'Retry with scope=page or scope=global_part.',
            ]);
        }

        $operation = trim((string) ($request->get_param('operation') ?? ''));
        if (!in_array($operation, ['replace', 'clear', 'source_patch'], true)) {
            return $this->textToolError('edit_runtime', 400, 'invalid_runtime_operation', [
                'RUNTIME SCOPE: ' . $scope,
                'NEXT STEP',
                'Retry with operation=replace, operation=source_patch, or operation=clear.',
            ]);
        }

        $resolved = $this->resolveWriteTarget('edit_runtime', $request, $scope);
        if ($resolved instanceof \WP_REST_Response) {
            return $resolved;
        }

        $ownerId = $resolved['owner_id'];
        $warnings = [];
        if ($operation === 'clear') {
            try {
                if ($scope === 'page') {
                    $this->javaScript->clearForPage($ownerId, $this->currentUserId());
                } else {
                    $warnings = $this->javaScript->clearForGlobalPartWithWarnings($ownerId)['warnings'];
                }
            } catch (StaleSourceGenerationException $exception) {
                return $this->staleSourceToolError('edit_runtime', $exception);
            } catch (\Throwable $failure) {
                $this->recordUnexpectedWriteFailure($scope, $ownerId, $failure);
                return $this->unexpectedWriteToolError('edit_runtime', $scope, $ownerId);
            }

            return AgentTextResponse::ok(implode("\n", $this->runtimeWriteLines(
                $scope,
                $ownerId,
                'clear',
                false,
                $warnings,
            )));
        }

        if ($operation === 'replace') {
            $javascript = $this->requestedJavaScript($request);
            if ($javascript === null) {
                return $this->missingJavaScriptError($scope, $ownerId);
            }
            if (strlen($javascript) > PageJavaScriptRuntimeService::MAX_SOURCE_BYTES) {
                return $this->sourceTooLargeError($scope, $ownerId);
            }

            try {
                if ($scope === 'page') {
                    $this->javaScript->replaceForPage(
                        $ownerId,
                        $javascript,
                        $this->currentUserId(),
                    );
                } else {
                    $warnings = $this->javaScript->replaceForGlobalPartWithWarnings($ownerId, $javascript)['warnings'];
                }
            } catch (StaleSourceGenerationException $exception) {
                return $this->staleSourceToolError('edit_runtime', $exception);
            } catch (\Throwable $failure) {
                $this->recordUnexpectedWriteFailure($scope, $ownerId, $failure);
                return $this->unexpectedWriteToolError('edit_runtime', $scope, $ownerId);
            }

            return AgentTextResponse::ok(implode("\n", $this->runtimeWriteLines(
                $scope,
                $ownerId,
                'replace',
                true,
                $warnings,
            )));
        }

        $patches = $this->requestedPatches($request);
        if ($patches === []) {
            return $this->missingPatchesError('edit_runtime', $scope, $ownerId);
        }

        try {
            $result = $scope === 'page'
                ? $this->javaScript->applySourcePatchForPage($ownerId, $patches, $this->currentUserId())
                : $this->javaScript->applySourcePatchForGlobalPart($ownerId, $patches);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('edit_runtime', $exception);
        } catch (\Throwable $failure) {
            $this->recordUnexpectedWriteFailure($scope, $ownerId, $failure);
            return $this->unexpectedWriteToolError('edit_runtime', $scope, $ownerId);
        }

        if ($result['error'] !== null) {
            return $this->sourcePatchFailedError('edit_runtime', $scope, $ownerId, $result['error']);
        }
        if ($result['too_large']) {
            return $this->sourceTooLargeError($scope, $ownerId);
        }

        return AgentTextResponse::ok(implode("\n", $this->runtimeSourcePatchWriteLines(
            $scope,
            $ownerId,
            $patches,
            $result['before'],
            $result['after'],
            $result['warnings'] ?? [],
        )));
    }

    private function recordUnexpectedWriteFailure(string $scope, int $ownerId, \Throwable $failure): void
    {
        try {
            $this->failureReporter?->report($scope . ' runtime source', $ownerId, 'write.uncertain', $failure);
        } catch (\Throwable) {
            // A report failure cannot change the controlled error response.
        }
    }

    public function preview(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return $this->previewRequest($request);
        } catch (\Throwable $failure) {
            $this->recordBoundaryFailure('preview', $failure);
            return $this->textToolError('preview_runtime_change', 500, 'runtime_preview_failed', [
                'RETRY_SAFETY: No runtime source was changed.',
                'NEXT STEP',
                'Retry preview_runtime_change. If the error continues, review the WordPress error log.',
            ]);
        }
    }

    private function previewRequest(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!$this->javaScript instanceof PageJavaScriptRuntimeService) {
            return $this->textToolError('preview_runtime_change', 501, 'runtime_lane_unavailable', [
                'NEXT STEP',
                'The custom JavaScript lane is not available on this site yet.',
            ]);
        }

        $scope = $this->runtimeScope($request);
        if ($scope === null) {
            return $this->textToolError('preview_runtime_change', 400, 'invalid_runtime_scope', [
                'NEXT STEP',
                'Retry with scope=page or scope=global_part.',
            ]);
        }

        $resolved = $this->resolveWriteTarget('preview_runtime_change', $request, $scope);
        if ($resolved instanceof \WP_REST_Response) {
            return $resolved;
        }

        $ownerId = $resolved['owner_id'];
        $patches = $this->requestedPatches($request);
        if ($patches === []) {
            return $this->missingPatchesError('preview_runtime_change', $scope, $ownerId);
        }

        $preview = $scope === 'page'
            ? $this->javaScript->previewSourcePatchForPage($ownerId, $patches)
            : $this->javaScript->previewSourcePatchForGlobalPart($ownerId, $patches);

        if ($preview['error'] !== null) {
            return $this->sourcePatchFailedError('preview_runtime_change', $scope, $ownerId, $preview['error']);
        }
        if ($preview['too_large']) {
            return $this->sourceTooLargeError($scope, $ownerId, 'preview_runtime_change');
        }

        return AgentTextResponse::ok(implode("\n", $this->runtimePreviewLines(
            $scope,
            $ownerId,
            $patches,
            $preview['before'],
            $preview['after'],
        )));
    }

    private function recordBoundaryFailure(string $step, \Throwable $failure): void
    {
        try {
            $this->failureReporter?->report('runtime source', 0, $step, $failure);
        } catch (\Throwable) {
            // A report failure cannot change the controlled Agent response.
        }
    }

    // ── Runtime target resolution ────────────────────────────

    private function runtimeScope(\WP_REST_Request $request): ?string
    {
        $scope = trim((string) ($request->get_param('scope') ?? ''));

        return in_array($scope, ['page', 'global_part'], true) ? $scope : null;
    }

    private function runtimeScopeEnabled(string $scope): bool
    {
        if (!$this->settings instanceof ToolSettingsAccess) {
            return true;
        }

        return $scope === 'page'
            ? $this->settings->pageCustomJavaScriptEnabled()
            : $this->settings->globalPartCustomJavaScriptEnabled();
    }

    /**
     * @return array{scope: string, owner_id: int}|\WP_REST_Response
     */
    private function resolveWriteTarget(
        string $toolName,
        \WP_REST_Request $request,
        string $scope,
    ): array|\WP_REST_Response {
        if (!$this->runtimeScopeEnabled($scope)) {
            return $this->textToolError($toolName, 403, 'runtime_lane_disabled', [
                'RUNTIME SCOPE: ' . $scope,
                'NEXT STEP',
                'Enable custom JavaScript for this runtime scope in Page Builder JavaScript settings, then retry.',
            ]);
        }
        if (!$this->permissions->canCapability('unfiltered_html')) {
            return $this->textToolError($toolName, 403, 'javascript_capability_required', [
                'RUNTIME SCOPE: ' . $scope,
                'NEXT STEP',
                'Use an account with the unfiltered_html capability before editing custom JavaScript.',
            ]);
        }

        if ($scope === 'page') {
            $pageId = $this->requestPageId($request);
            if ($pageId <= 0) {
                return $this->textToolError($toolName, 400, 'missing_page_id', [
                    'RUNTIME SCOPE: page',
                    'NEXT STEP',
                    'Retry with a valid page_id.',
                ]);
            }
            if (!$this->permissions->canEditPage($pageId)) {
                return $this->textToolError($toolName, 403, 'page_edit_forbidden', [
                    'RUNTIME SCOPE: page',
                    'PAGE_ID: ' . $pageId,
                    'NEXT STEP',
                    'Use an account that can edit this page.',
                ]);
            }
            if (!$this->sections->isOwnedPage($pageId)) {
                return $this->textToolError($toolName, 404, 'page_not_owned', [
                    'RUNTIME SCOPE: page',
                    'PAGE_ID: ' . $pageId,
                    'NEXT STEP',
                    'Retry with an Uncanny Page Builder-owned page.',
                ]);
            }

            return ['scope' => 'page', 'owner_id' => $pageId];
        }

        $globalPartId = $this->requestGlobalPartId($request);
        if ($globalPartId <= 0) {
            return $this->textToolError($toolName, 400, 'missing_global_part_id', [
                'RUNTIME SCOPE: global_part',
                'NEXT STEP',
                'Retry with a valid global_part_id.',
            ]);
        }

        $resolved = $this->globalParts->findById($globalPartId);
        if ($resolved === null) {
            return $this->textToolError($toolName, 404, 'global_part_not_found', [
                'RUNTIME SCOPE: global_part',
                'GLOBAL_PART_ID: ' . $globalPartId,
                'NEXT STEP',
                'Retry with an existing reusable global part.',
            ]);
        }
        if (!$this->permissions->canEditPost($globalPartId)) {
            return $this->textToolError($toolName, 403, 'global_part_edit_forbidden', [
                'RUNTIME SCOPE: global_part',
                'GLOBAL_PART_ID: ' . $globalPartId,
                'NEXT STEP',
                'Use an account that can edit this global part.',
            ]);
        }

        return ['scope' => 'global_part', 'owner_id' => $globalPartId];
    }

    private function requestPageId(\WP_REST_Request $request): int
    {
        $pageId = \absint($request->get_param('page_id'));
        if ($pageId > 0) {
            return $pageId;
        }

        $context = $request->get_param('page_builder_context');
        if (!is_array($context)) {
            return 0;
        }

        return \absint($context['page_id'] ?? 0);
    }

    private function requestGlobalPartId(\WP_REST_Request $request): int
    {
        $globalPartId = \absint($request->get_param('global_part_id'));
        if ($globalPartId > 0) {
            return $globalPartId;
        }

        $context = $request->get_param('page_builder_context');
        if (!is_array($context)) {
            return 0;
        }

        return \absint($context['global_part_id'] ?? 0);
    }

    // ── Runtime request values ───────────────────────────────

    private function requestedJavaScript(\WP_REST_Request $request): ?string
    {
        $javascript = $request->get_param('javascript');
        if (!is_string($javascript) || trim($javascript) === '') {
            return null;
        }

        return $javascript;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestedPatches(\WP_REST_Request $request): array
    {
        $patches = $request->get_param('javascript_patches');

        return is_array($patches) ? array_values($patches) : [];
    }

    private function currentUserId(): int
    {
        if (!\function_exists('get_current_user_id')) {
            return 0;
        }

        return max(0, (int) \get_current_user_id());
    }

    // ── Agent response contract ──────────────────────────────

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

    private function unexpectedWriteToolError(string $toolName, string $scope, int $ownerId): \WP_REST_Response
    {
        return $this->textToolError($toolName, 500, 'runtime_write_failed', [
            'RUNTIME SCOPE: ' . $scope,
            strtoupper($scope) . '_ID: ' . $ownerId,
            'RETRY_SAFETY: The write result is uncertain. Do not retry blindly.',
            'NEXT STEP',
            'Call read_runtime first. Retry only if the requested change is absent and the current source still permits it.',
        ]);
    }

    private function missingJavaScriptError(string $scope, int $ownerId): \WP_REST_Response
    {
        return $this->textToolError('edit_runtime', 400, 'missing_javascript', [
            'RUNTIME SCOPE: ' . $scope,
            strtoupper($scope) . '_ID: ' . $ownerId,
            'NEXT STEP',
            'Retry with a non-empty javascript string for operation=replace.',
        ]);
    }

    private function missingPatchesError(
        string $toolName,
        string $scope,
        int $ownerId,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 400, 'missing_javascript_patches', [
            'RUNTIME SCOPE: ' . $scope,
            strtoupper($scope) . '_ID: ' . $ownerId,
            'NEXT STEP',
            'Retry with javascript_patches copied from read_runtime output.',
        ]);
    }

    private function sourcePatchFailedError(
        string $toolName,
        string $scope,
        int $ownerId,
        string $detail,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 422, 'source_patch_failed', [
            'RUNTIME SCOPE: ' . $scope,
            strtoupper($scope) . '_ID: ' . $ownerId,
            'DETAIL: ' . $detail,
            'NEXT STEP',
            'Call read_runtime again, quote an exact current non-empty substring, and retry.',
        ]);
    }

    private function sourceTooLargeError(
        string $scope,
        int $ownerId,
        string $toolName = 'edit_runtime',
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 413, 'javascript_source_too_large', [
            'RUNTIME SCOPE: ' . $scope,
            strtoupper($scope) . '_ID: ' . $ownerId,
            'MAX_BYTES: ' . PageJavaScriptRuntimeService::MAX_SOURCE_BYTES,
            'NEXT STEP',
            'Reduce the custom JavaScript source or move reusable library code to an approved library.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function runtimeReadLines(string $scope, int $ownerId, string $javascript): array
    {
        $lines = [
            'TOOL: read_runtime',
            'RESULT: success',
            'RUNTIME SCOPE: ' . $scope,
            strtoupper($scope) . '_ID: ' . $ownerId,
            'HAS_JAVASCRIPT: ' . (trim($javascript) !== '' ? 'yes' : 'no'),
            '',
        ];

        if (trim($javascript) === '') {
            $lines[] = 'JAVASCRIPT: none';
            $lines[] = '';
        } else {
            $lines[] = '```javascript';
            $lines[] = $javascript;
            $lines[] = '```';
            $lines[] = '';
        }

        $lines[] = 'NEXT STEP';
        $lines[] = 'For micro or local repairs, preview_runtime_change then edit_runtime operation=source_patch. Use operation=replace for broad rewrites, or operation=clear to remove it.';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function runtimeWriteLines(
        string $scope,
        int $ownerId,
        string $operation,
        bool $hasJavaScript,
        array $warnings = [],
    ): array {
        $lines = [
            'TOOL: edit_runtime',
            'RESULT: success',
            'OPERATION: ' . $operation,
            'RUNTIME SCOPE: ' . $scope,
            strtoupper($scope) . '_ID: ' . $ownerId,
            'HAS_JAVASCRIPT: ' . ($hasJavaScript ? 'yes' : 'no'),
        ];
        $this->appendWarningLines($lines, $warnings);
        $lines[] = 'NEXT STEP';
        $lines[] = 'Call read_runtime to verify the saved working source on this owner.';

        return $lines;
    }

    /**
     * @param list<array<string, mixed>> $patches
     * @return list<string>
     */
    private function runtimeSourcePatchWriteLines(
        string $scope,
        int $ownerId,
        array $patches,
        string $before,
        string $after,
        array $warnings = [],
    ): array {
        $lines = [
            'TOOL: edit_runtime',
            'RESULT: success',
            'OPERATION: source_patch',
            'RUNTIME SCOPE: ' . $scope,
            strtoupper($scope) . '_ID: ' . $ownerId,
            'HAS_JAVASCRIPT: ' . (trim($after) !== '' ? 'yes' : 'no'),
            '',
            'APPLIED',
            'JAVASCRIPT_PATCHES: ' . count($patches),
            '',
        ];
        $this->appendDiffLines(
            $lines,
            'JAVASCRIPT DIFF',
            $this->sourceDiffer->diff('JAVASCRIPT DIFF', $before, $after),
        );
        $this->appendWarningLines($lines, $warnings);
        $lines[] = 'NEXT STEP';
        $lines[] = 'Call read_runtime to verify the saved working source on this owner.';

        return $lines;
    }

    /** @param list<string> $warnings */
    private function appendWarningLines(array &$lines, array $warnings): void
    {
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
     * @param list<array<string, mixed>> $patches
     * @return list<string>
     */
    private function runtimePreviewLines(
        string $scope,
        int $ownerId,
        array $patches,
        string $before,
        string $after,
    ): array {
        $lines = [
            'TOOL: preview_runtime_change',
            'RESULT: success',
            'RUNTIME SCOPE: ' . $scope,
            strtoupper($scope) . '_ID: ' . $ownerId,
            '',
            'VALIDATION',
            'passed',
            '',
            'APPLIED',
            'JAVASCRIPT_PATCHES: ' . count($patches),
            '',
        ];
        $this->appendDiffLines(
            $lines,
            'JAVASCRIPT DIFF',
            $this->sourceDiffer->diff('JAVASCRIPT DIFF', $before, $after),
        );
        $lines[] = 'NEXT STEP';
        $lines[] = 'If this diff matches the user request, call edit_runtime operation=source_patch with the same payload.';

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function appendDiffLines(
        array &$lines,
        string $heading,
        CompactSourceDiff $diff,
    ): void {
        $lines[] = $heading;
        foreach (explode("\n", $diff->body()) as $line) {
            $lines[] = $line;
        }
        $lines[] = '';
    }
}
