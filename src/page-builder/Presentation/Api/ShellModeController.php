<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Application\UpdatePageLayout;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\Persistence\WordPressWriteVerificationException;

/**
 * REST endpoints for shell mode management.
 *
 * GET  /uncanny-page-builder/v1/shell-mode[?page_id=N]
 * PUT  /uncanny-page-builder/v1/shell-mode
 * PUT  /uncanny-page-builder/v1/shell-mode/page/{page_id}
 * GET  /uncanny-page-builder/v1/shell-mode/signals
 */
final class ShellModeController
{
    public function __construct(
        private readonly ShellModeService $service,
        private readonly SectionService $sectionService,
        private readonly PermissionChecker $permissions,
        private readonly UpdatePageLayout $pageLayout,
        private readonly ?WorkingCanvasRefresherInterface $workingCanvas = null,
        private readonly ?EditorLockWriteGuard $editorLock = null,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/shell-mode', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'read'],
                'permission_callback' => [$this->permissions, 'canEdit'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updateSiteDefault'],
                'permission_callback' => [$this->permissions, 'canManage'],
            ],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/shell-mode/page/(?P<page_id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'readPageMode'],
                'permission_callback' => [$this->permissions, 'canEdit'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updatePageMode'],
                'permission_callback' => [$this->permissions, 'canEdit'],
            ],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/shell-mode/signals', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'readSignals'],
                'permission_callback' => [$this->permissions, 'canEdit'],
            ],
        ]);
    }

    /**
     * GET /shell-mode[?page_id=N]
     *
     * Without page_id: returns site default mode and label.
     * With page_id: returns resolved mode context for that page (requires Engine-owned page).
     */
    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = absint($request->get_param('page_id'));

        if ($pageId > 0) {
            if (!$this->permissions->canEditPage($pageId)) {
                return ApiResponse::error(ErrorMessage::PageEditForbidden);
            }
            if (!$this->sectionService->isPageOwned($pageId)) {
                return ApiResponse::error(ErrorMessage::NotEnginePage);
            }

            $ctx = $this->service->resolveForPage($pageId);
            return ApiResponse::ok($ctx->toArray())->toResponse();
        }

        $siteDefault = $this->service->getSiteDefault();

        return ApiResponse::ok([
            'mode'       => $siteDefault->value,
            'mode_label' => $siteDefault->label(),
        ])->toResponse();
    }

    /**
     * GET /shell-mode/page/{page_id}
     *
     * Returns resolved mode context for a specific Engine-owned page.
     */
    public function readPageMode(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = absint($request['page_id']);

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::NotEnginePage);
        }

        $ctx = $this->service->resolveForPage($pageId);
        return ApiResponse::ok($ctx->toArray())->toResponse();
    }

    /**
     * PUT /shell-mode
     *
     * Set the site-level default shell mode. Admin only.
     */
    public function updateSiteDefault(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $body = $request->get_json_params();
        $modeValue = $body['mode'] ?? null;

        if (!is_string($modeValue)) {
            return ApiResponse::error(ErrorMessage::InvalidMode, ['detail' => 'Request body must include a "mode" string.']);
        }

        $mode = ShellMode::tryFrom($modeValue);
        if ($mode === null) {
            $validValues = implode(', ', array_map(static fn (ShellMode $m) => $m->value, ShellMode::cases()));
            return ApiResponse::error(ErrorMessage::InvalidMode, ['detail' => "Invalid shell mode. Valid values: {$validValues}."]);
        }

        $this->service->setSiteDefault($mode);

        return ApiResponse::ok([
            'mode'       => $mode->value,
            'mode_label' => $mode->label(),
        ])->toResponse();
    }

    /**
     * PUT /shell-mode/page/{page_id}
     *
     * Set the page-level shell mode override. Requires Engine-owned page.
     */
    public function updatePageMode(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = absint($request['page_id']);

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::NotEnginePage);
        }

        $ownershipError = $this->editorLock?->check($request, $pageId, 'shell_mode.page');
        if ($ownershipError instanceof \WP_Error) {
            return $ownershipError;
        }

        $body = $request->get_json_params();
        $modeValue = $body['mode'] ?? null;

        if (!is_string($modeValue)) {
            return ApiResponse::error(ErrorMessage::InvalidMode, ['detail' => 'Request body must include a "mode" string.']);
        }

        $mode = ShellMode::tryFrom($modeValue);
        if ($mode === null) {
            $validValues = implode(', ', array_map(static fn (ShellMode $m) => $m->value, ShellMode::cases()));
            return ApiResponse::error(ErrorMessage::InvalidMode, ['detail' => "Invalid shell mode. Valid values: {$validValues}."]);
        }

        /*
         * Compile before changing any page layout setting. Compiled CSS is
         * derived only from working sections, so this keeps a persistence
         * failure from leaving shell mode or global-part selection half-applied.
         */
        try {
            $this->refreshWorkingCanvas($pageId);
        } catch (StaleSourceGenerationException $exception) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $exception->scope()]);
        } catch (WordPressWriteVerificationException $exception) {
            return ApiResponse::error(ErrorMessage::WorkingCanvasRefreshFailed, [
                'failure_stage' => 'compiled_css_persistence',
                'detail' => $exception->getMessage(),
                'retryable' => true,
            ]);
        } catch (\RuntimeException) {
            return ApiResponse::error(ErrorMessage::WorkingCanvasRefreshFailed, [
                'failure_stage' => 'working_canvas_transaction',
                'detail' => 'The working canvas transaction could not be completed safely.',
                'retryable' => true,
            ]);
        }

        try {
            $this->pageLayout->update($pageId, $mode, $body['global_parts'] ?? null);
        } catch (StaleSourceGenerationException $exception) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $exception->scope()]);
        } catch (\RuntimeException) {
            return ApiResponse::error(ErrorMessage::PageLayoutUpdateFailed, [
                'failure_stage' => 'page_layout_transaction',
                'retryable' => true,
            ]);
        }

        $ctx = $this->service->resolveForPage($pageId);

        return ApiResponse::ok($ctx->toArray())->toResponse();
    }

    /**
     * GET /shell-mode/signals
     *
     * Detection hints for the mode chooser UI.
     */
    public function readSignals(\WP_REST_Request $request): \WP_REST_Response
    {
        return ApiResponse::ok($this->service->detectSignals()->toArray())->toResponse();
    }

    private function refreshWorkingCanvas(int $pageId): void
    {
        if (!$this->workingCanvas instanceof WorkingCanvasRefresherInterface) {
            return;
        }

        $this->workingCanvas->refresh($pageId);
    }
}
