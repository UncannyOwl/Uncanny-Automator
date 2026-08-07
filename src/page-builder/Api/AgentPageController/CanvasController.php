<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\Access\PageBuilderDisabledException;
use UncannyPageBuilder\Application\Canvas\AttachReusableToCanvasCommand;
use UncannyPageBuilder\Application\Canvas\AttachReusableToCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\CreateCanvasCommand;
use UncannyPageBuilder\Application\Canvas\CreateCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\DeleteCanvasCommand;
use UncannyPageBuilder\Application\Canvas\DeleteCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\EditCanvasCommand;
use UncannyPageBuilder\Application\Canvas\EditCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\ListCanvasQuery;
use UncannyPageBuilder\Application\Canvas\ListCanvasUseCase;
use UncannyPageBuilder\Domain\Canvas\Canvas;
use UncannyPageBuilder\Domain\Canvas\CanvasKind;
use UncannyPageBuilder\Domain\Exception\CanvasNotFoundException;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Domain\Exception\ReusableNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;
use UncannyPageBuilder\Infrastructure\Persistence\WordPressWriteVerificationException;

/**
 * Handles Agent-facing Canvas lifecycle operations.
 *
 * Publication remains human-owned. This collaborator manages working Canvas
 * metadata, deletion safeguards, and attaching reusable sections while the
 * root controller retains the stable WordPress REST callback.
 */
final class CanvasController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly CreateCanvasUseCase $createCanvas,
        private readonly EditCanvasUseCase $editCanvas,
        private readonly DeleteCanvasUseCase $deleteCanvas,
        private readonly ListCanvasUseCase $listCanvas,
        private readonly AttachReusableToCanvasUseCase $attachReusableToCanvas,
    ) {}

    public function manage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $operation = trim((string) ($request->get_param('operation') ?? ''));

        return match ($operation) {
            'list' => $this->listFromRequest($request),
            'create' => $this->createFromRequest($request),
            'update' => $this->updateFromRequest($request),
            'delete' => $this->deleteFromRequest($request),
            'attach_reusable' => $this->attachReusableFromRequest($request),
            default => $this->textToolError('manage_canvas', 400, 'invalid_operation', [
                'OPERATION: ' . ($operation !== '' ? $operation : 'missing'),
                'NEXT STEP',
                'Retry with operation list, create, update, delete, or attach_reusable.',
            ]),
        };
    }

    // ---------------------------------------------------------------------
    // Canvas operations
    // ---------------------------------------------------------------------

    private function createFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $kindValue = trim((string) ($request->get_param('kind') ?? ''));
        $kind = CanvasKind::tryFrom($kindValue);
        if (!$kind instanceof CanvasKind) {
            return $this->textToolError('manage_canvas', 400, 'invalid_canvas_kind', [
                'KIND: ' . ($kindValue !== '' ? $kindValue : 'missing'),
                'NEXT STEP',
                'Retry with kind page or global_part.',
            ]);
        }

        $title = $request->get_param('title');
        $requestedReusableType = $this->requestedReusableType($request);
        if ($kind === CanvasKind::GlobalPart && $requestedReusableType === false) {
            return $this->textToolError('manage_canvas', 400, 'invalid_global_part_type', [
                'KIND: ' . $kind->value,
                'NEXT STEP',
                'Retry with global_part_type header, footer, or section.',
            ]);
        }

        $globalPartType = $requestedReusableType instanceof GlobalPartType
            ? $requestedReusableType
            : GlobalPartType::Section;

        try {
            $canvas = ($this->createCanvas)(new CreateCanvasCommand(
                kind: $kind,
                title: is_string($title) ? $title : '',
                globalPartType: $globalPartType,
            ));
        } catch (PageBuilderDisabledException $exception) {
            return $this->textToolError('manage_canvas', 403, 'page_builder_disabled', [
                'KIND: ' . $kind->value,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Enable Uncanny Page Builder in Automator settings before creating a new Page Builder page.',
            ]);
        } catch (\RuntimeException $exception) {
            $this->rethrowAgentWriteBoundaryFailure($exception);

            return $this->textToolError('manage_canvas', 500, 'canvas_create_failed', [
                'KIND: ' . $kind->value,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Retry once. If it still fails, inspect the server error log.',
            ]);
        }

        $lines = [
            'TOOL: manage_canvas',
            'RESULT: success',
            'OPERATION: create',
            ...$this->canvasSummaryLines($canvas),
            '',
            'NEXT STEP',
        ];
        $lines[] = $canvas->kind() === CanvasKind::Page
            ? 'Call read_page_context before section planning.'
            : 'This reusable is blank. Use create_section once to bootstrap source content, then switch to edit_part kind=global_part.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    private function listFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $kind = $this->requestedCanvasKind($request);
        if ($kind === false) {
            return $this->textToolError('manage_canvas', 400, 'invalid_canvas_kind', [
                'NEXT STEP',
                'Retry with kind page or global_part, or omit kind to list both.',
            ]);
        }

        $canvases = ($this->listCanvas)(new ListCanvasQuery($kind));
        $lines = [
            'TOOL: manage_canvas',
            'RESULT: success',
            'OPERATION: list',
            'COUNT: ' . count($canvases),
        ];

        foreach ($canvases as $index => $canvas) {
            $lines[] = '';
            $lines[] = 'ITEM ' . ($index + 1);
            array_push($lines, ...$this->canvasSummaryLines($canvas));
        }

        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = $canvases === []
            ? 'Create a canvas with manage_canvas operation=create.'
            : 'Pick a CANVAS_ID from the list, then use read_page_context or manage_canvas update/delete.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    private function updateFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $canvasId = $this->requestedCanvasId($request);
        if ($canvasId <= 0) {
            return $this->textToolError('manage_canvas', 400, 'missing_canvas_id', [
                'NEXT STEP',
                'Retry with canvas_id, or run this from an active canvas.',
            ]);
        }

        /*
         * Agent edits belong to working state. Publication is a separate,
         * human-only decision, so even stale callers that still send status
         * must fail visibly instead of changing WordPress public state.
         */
        if ($request->get_param('status') !== null) {
            return $this->textToolError('manage_canvas', 400, 'publication_status_not_supported', [
                'CANVAS_ID: ' . $canvasId,
                'DETAIL: Uncanny Agent cannot publish or unpublish a page.',
                'NEXT STEP',
                'Save draft changes without status. A human can review and publish them from the Manual editor.',
            ]);
        }

        $shellMode = $this->requestedShellMode($request);
        if ($shellMode === false) {
            return $this->textToolError('manage_canvas', 400, 'invalid_shell_mode', [
                'CANVAS_ID: ' . $canvasId,
                'NEXT STEP',
                'Retry with shell_mode uncanny_native, theme_composition, or none.',
            ]);
        }

        try {
            $canvas = ($this->editCanvas)(new EditCanvasCommand(
                canvasId: $canvasId,
                title: is_string($request->get_param('title')) ? (string) $request->get_param('title') : null,
                shellMode: $shellMode instanceof ShellMode ? $shellMode : null,
            ));
        } catch (CanvasNotFoundException) {
            return $this->textToolError('manage_canvas', 404, 'canvas_not_found', [
                'CANVAS_ID: ' . $canvasId,
                'NEXT STEP',
                'Refresh context and retry with a valid canvas_id.',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError('manage_canvas', 400, 'invalid_canvas_update', [
                'CANVAS_ID: ' . $canvasId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Adjust the requested properties and retry.',
            ]);
        } catch (\RuntimeException $exception) {
            $this->rethrowAgentWriteBoundaryFailure($exception);

            return $this->textToolError('manage_canvas', 500, 'canvas_update_failed', [
                'CANVAS_ID: ' . $canvasId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Retry once. If it still fails, inspect the server error log.',
            ]);
        }

        $lines = [
            'TOOL: manage_canvas',
            'RESULT: success',
            'OPERATION: update',
            ...$this->canvasSummaryLines($canvas),
            '',
            'NEXT STEP',
        ];
        $lines[] = $canvas->kind() === CanvasKind::Page
            ? 'Call read_page_context to continue working on this page.'
            : 'Use create_section once if this reusable is blank; otherwise use edit_part kind=global_part to keep working on this reusable source.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    private function deleteFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $requestedCanvasId = $request->get_param('canvas_id');
        if ($requestedCanvasId !== null && RequestId::positive($requestedCanvasId) === null) {
            return $this->textToolError('manage_canvas', 400, 'invalid_canvas_id', [
                'NEXT STEP',
                'Retry with a positive integer canvas_id.',
            ]);
        }

        $canvasId = $this->requestedCanvasId($request);
        if ($canvasId <= 0) {
            return $this->textToolError('manage_canvas', 400, 'missing_canvas_id', [
                'NEXT STEP',
                'Retry with canvas_id, or run this from an active canvas.',
            ]);
        }

        if ($this->permissions->isBearerRequest($request)) {
            return $this->textToolError('manage_canvas', 403, 'page_lifecycle_not_supported', [
                'CANVAS_ID: ' . $canvasId,
                'DETAIL: Uncanny Agent cannot trash or permanently delete a page.',
                'NEXT STEP',
                'A human can move the page to Trash from the Manual editor or WordPress Pages screen.',
            ]);
        }

        if (!$this->permissions->canManagePost($canvasId)) {
            return $this->textToolError('manage_canvas', 403, 'canvas_manage_forbidden', [
                'CANVAS_ID: ' . $canvasId,
                'NEXT STEP',
                'Ask a site administrator for permission to manage this canvas.',
            ]);
        }

        $deleteMode = trim((string) ($request->get_param('delete_mode') ?? 'trash'));
        if (!in_array($deleteMode, ['trash', 'delete'], true)) {
            return $this->textToolError('manage_canvas', 400, 'invalid_delete_mode', [
                'CANVAS_ID: ' . $canvasId,
                'DELETE_MODE: ' . ($deleteMode !== '' ? $deleteMode : 'missing'),
                'NEXT STEP',
                'Retry with delete_mode trash or delete.',
            ]);
        }

        try {
            $result = ($this->deleteCanvas)(new DeleteCanvasCommand(
                canvasId: $canvasId,
                forceDelete: $deleteMode === 'delete',
            ));
        } catch (CanvasNotFoundException) {
            return $this->textToolError('manage_canvas', 404, 'canvas_not_found', [
                'CANVAS_ID: ' . $canvasId,
                'NEXT STEP',
                'Refresh context and retry with a valid canvas_id.',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError('manage_canvas', 400, 'invalid_canvas_delete', [
                'CANVAS_ID: ' . $canvasId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Adjust the request and retry.',
            ]);
        } catch (\RuntimeException $exception) {
            $this->rethrowAgentWriteBoundaryFailure($exception);

            return $this->textToolError('manage_canvas', 500, 'canvas_delete_failed', [
                'CANVAS_ID: ' . $canvasId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Retry once. If it still fails, inspect the server error log.',
            ]);
        }

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_canvas',
            'RESULT: success',
            'OPERATION: delete',
            'CANVAS_ID: ' . $result->canvas()->id(),
            'KIND: ' . $result->canvas()->kind()->value,
            'TITLE: ' . $result->canvas()->title(),
            'DELETE_MODE: ' . ($result->forceDeleted() ? 'delete' : 'trash'),
            '',
            'NEXT STEP',
            'Refresh the canvas list or open another canvas before continuing.',
        ]));
    }

    private function attachReusableFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $canvasId = $this->requestedCanvasId($request);
        if ($canvasId <= 0) {
            return $this->textToolError('manage_canvas', 400, 'missing_canvas_id', [
                'NEXT STEP',
                'Retry with canvas_id, or run this from an active page canvas.',
            ]);
        }

        $reusableId = $this->requestedReusableId($request);
        if ($reusableId <= 0) {
            return $this->textToolError('manage_canvas', 400, 'missing_reusable_id', [
                'CANVAS_ID: ' . $canvasId,
                'NEXT STEP',
                'Retry with reusable_id from manage_reusable list, or run this from an active reusable canvas and a page canvas target.',
            ]);
        }

        try {
            $result = ($this->attachReusableToCanvas)(new AttachReusableToCanvasCommand(
                canvasId: $canvasId,
                reusableId: $reusableId,
            ));
        } catch (CanvasNotFoundException) {
            return $this->textToolError('manage_canvas', 404, 'canvas_not_found', [
                'CANVAS_ID: ' . $canvasId,
                'NEXT STEP',
                'Refresh context and retry with a valid page canvas_id.',
            ]);
        } catch (ReusableNotFoundException $exception) {
            return $this->textToolError('manage_canvas', 404, 'reusable_not_found', [
                'CANVAS_ID: ' . $canvasId,
                'REUSABLE_ID: ' . $exception->reusableId(),
                'NEXT STEP',
                'Refresh the reusable list and retry with a valid reusable_id.',
            ]);
        } catch (SectionValidationException $exception) {
            return $this->textToolError('manage_canvas', 422, 'reusable_attach_failed', [
                'CANVAS_ID: ' . $canvasId,
                'REUSABLE_ID: ' . $reusableId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Fix the reusable source content and retry.',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError('manage_canvas', 400, 'invalid_canvas_attach', [
                'CANVAS_ID: ' . $canvasId,
                'REUSABLE_ID: ' . $reusableId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Attach a reusable with source content to a page canvas.',
            ]);
        } catch (\RuntimeException $exception) {
            $this->rethrowAgentWriteBoundaryFailure($exception);

            return $this->textToolError('manage_canvas', 500, 'canvas_attach_failed', [
                'CANVAS_ID: ' . $canvasId,
                'REUSABLE_ID: ' . $reusableId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Retry once. If it still fails, inspect the server error log.',
            ]);
        }

        $lines = [
            'TOOL: manage_canvas',
            'RESULT: success',
            'OPERATION: attach_reusable',
            'CANVAS_ID: ' . $result->canvas()->id(),
            'KIND: ' . $result->canvas()->kind()->value,
            'REUSABLE_ID: ' . $result->reusableId(),
            'REUSABLE_TITLE: ' . $result->reusableTitle(),
            'REUSABLE_TYPE: ' . $result->reusableType(),
            'SECTION_ID: ' . $result->sectionId(),
            'POSITION: ' . $result->position(),
            'NAME: ' . $result->sectionName(),
        ];

        if ($result->previewUrl() !== '') {
            $lines[] = 'PREVIEW_URL: ' . $result->previewUrl();
        }

        $lines[] = '';
        $this->appendWarningLines($lines, $result->warnings());
        $lines[] = 'NEXT STEP';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    // ---------------------------------------------------------------------
    // Request parsing and response formatting
    // ---------------------------------------------------------------------

    private function requestedCanvasId(\WP_REST_Request $request): int
    {
        $canvasIdValue = $request->get_param('canvas_id');
        if ($canvasIdValue !== null) {
            return RequestId::positive($canvasIdValue) ?? 0;
        }

        $pageIdValue = $request->get_param('page_id');
        if ($pageIdValue !== null) {
            return RequestId::positive($pageIdValue) ?? 0;
        }

        $globalPartIdValue = $request->get_param('global_part_id');
        if ($globalPartIdValue !== null) {
            return RequestId::positive($globalPartIdValue) ?? 0;
        }

        $context = $request->get_param('page_builder_context');
        if (!is_array($context)) {
            return 0;
        }

        if (array_key_exists('global_part_id', $context)) {
            return RequestId::positive($context['global_part_id']) ?? 0;
        }

        return RequestId::positive($context['page_id'] ?? null) ?? 0;
    }

    private function requestedCanvasKind(\WP_REST_Request $request): CanvasKind|false|null
    {
        $kindValue = $request->get_param('kind');
        if (!is_string($kindValue) || trim($kindValue) === '') {
            return null;
        }

        return CanvasKind::tryFrom(trim($kindValue)) ?: false;
    }

    private function requestedReusableId(\WP_REST_Request $request): int
    {
        $reusableId = absint($request->get_param('reusable_id'));
        if ($reusableId > 0) {
            return get_post_type($reusableId) === 'upb_global_part' ? $reusableId : 0;
        }

        $globalPartId = $this->requestGlobalPartId($request);
        if ($globalPartId > 0) {
            return get_post_type($globalPartId) === 'upb_global_part' ? $globalPartId : 0;
        }

        $canvasId = absint($request->get_param('canvas_id'));
        if ($canvasId > 0) {
            return get_post_type($canvasId) === 'upb_global_part' ? $canvasId : 0;
        }

        $pageId = absint($request->get_param('page_id'));
        if ($pageId > 0) {
            return get_post_type($pageId) === 'upb_global_part' ? $pageId : 0;
        }

        $context = $request->get_param('page_builder_context');
        if (!is_array($context)) {
            return 0;
        }

        $contextGlobalPartId = absint($context['global_part_id'] ?? 0);
        if ($contextGlobalPartId > 0) {
            return get_post_type($contextGlobalPartId) === 'upb_global_part' ? $contextGlobalPartId : 0;
        }

        $contextPageId = absint($context['page_id'] ?? 0);

        return $contextPageId > 0 && get_post_type($contextPageId) === 'upb_global_part'
            ? $contextPageId
            : 0;
    }

    private function requestedReusableType(\WP_REST_Request $request): GlobalPartType|false|null
    {
        $typeValue = $request->get_param('reusable_type');
        if (!is_string($typeValue) || trim($typeValue) === '') {
            $typeValue = $request->get_param('type');
        }
        if (!is_string($typeValue) || trim($typeValue) === '') {
            $typeValue = $request->get_param('global_part_type');
        }
        if (!is_string($typeValue) || trim($typeValue) === '') {
            return null;
        }

        $typeValue = trim($typeValue);

        return in_array($typeValue, GlobalPartType::validValues(), true)
            ? GlobalPartType::fromString($typeValue)
            : false;
    }

    private function requestedShellMode(\WP_REST_Request $request): ShellMode|false|null
    {
        $modeValue = $request->get_param('shell_mode');
        if (!is_string($modeValue) || trim($modeValue) === '') {
            return null;
        }

        return ShellMode::tryFrom(trim($modeValue)) ?: false;
    }

    private function requestGlobalPartId(\WP_REST_Request $request): int
    {
        $requestId = absint($request->get_param('global_part_id'));
        if ($requestId > 0) {
            return $requestId;
        }

        $context = $request->get_param('page_builder_context');
        if (is_array($context)) {
            return absint($context['global_part_id'] ?? 0);
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function canvasSummaryLines(Canvas $canvas): array
    {
        $lines = [
            'CANVAS_ID: ' . $canvas->id(),
            'KIND: ' . $canvas->kind()->value,
            'TITLE: ' . $canvas->title(),
            'STATUS: ' . $canvas->status(),
            'EDITOR_URL: ' . $canvas->editorUrl(),
        ];

        if ($canvas->previewUrl() !== '') {
            $lines[] = 'PREVIEW_URL: ' . $canvas->previewUrl();
        }

        if ($canvas->shellMode() instanceof ShellMode) {
            $lines[] = 'SHELL_MODE: ' . $canvas->shellMode()->value;
        }

        if ($canvas->globalPartType() instanceof GlobalPartType) {
            $lines[] = 'GLOBAL_PART_TYPE: ' . $canvas->globalPartType()->value;
        }

        return $lines;
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
     * Broad use-case catches keep their product-specific errors, but integrity
     * failures still belong to the root Agent write boundary.
     */
    private function rethrowAgentWriteBoundaryFailure(\RuntimeException $exception): void
    {
        if (
            $exception instanceof CssRuleIntegrityException
            || $this->wordpressWriteVerificationFailureInChain($exception) instanceof WordPressWriteVerificationException
            || $this->sourceTransactionFailureInChain($exception) instanceof SourceTransactionsUnavailableException
        ) {
            throw $exception;
        }
    }

    private function wordpressWriteVerificationFailureInChain(
        \Throwable $exception,
    ): ?WordPressWriteVerificationException {
        for ($current = $exception; $current instanceof \Throwable; $current = $current->getPrevious()) {
            if ($current instanceof WordPressWriteVerificationException) {
                return $current;
            }
        }

        return null;
    }

    private function sourceTransactionFailureInChain(
        \Throwable $exception,
    ): ?SourceTransactionsUnavailableException {
        for ($current = $exception; $current instanceof \Throwable; $current = $current->getPrevious()) {
            if ($current instanceof SourceTransactionsUnavailableException) {
                return $current;
            }
        }

        return null;
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
