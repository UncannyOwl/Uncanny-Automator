<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
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
    private const REFRESH_WARNING = [
        'code' => 'working_canvas_refresh_failed',
        'message' => 'The site shell mode was saved, but working canvases could not be queued for refresh.',
    ];

    private const READBACK_WARNING = [
        'code' => 'shell_mode_readback_failed',
        'message' => 'The page layout was saved, but Page Builder could not confirm the saved values. Read the current page layout before another write.',
    ];

    public function __construct(
        private readonly ShellModeService $service,
        private readonly SectionService $sectionService,
        private readonly PermissionChecker $permissions,
        private readonly UpdatePageLayout $pageLayout,
        private readonly ?WorkingCanvasRefresherInterface $workingCanvas = null,
        private readonly ?EditorLockWriteGuard $editorLock = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
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
                'args'                => ['page_id' => RequestId::routeArgument()],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updatePageMode'],
                'permission_callback' => [$this->permissions, 'canEdit'],
                'args'                => ['page_id' => RequestId::routeArgument()],
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
        try {
            return $this->readShellMode($request);
        } catch (\Throwable $failure) {
            $this->recordFailure('site shell mode', 0, 'request.read', $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }
    }

    private function readShellMode(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = absint($request->get_param('page_id'));

        if ($pageId > 0) {
            if (!$this->permissions->canEditPage($pageId)) {
                return ApiResponse::error(ErrorMessage::PageEditForbidden);
            }
            if (!$this->sectionService->isPageOwned($pageId)) {
                return ApiResponse::error(ErrorMessage::NotEnginePage);
            }

            try {
                $ctx = $this->service->resolveForPage($pageId);
            } catch (\Throwable $failure) {
                $this->recordFailure('page shell mode', $pageId, 'read', $failure);
                return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
            }
            return ApiResponse::ok($ctx->toArray())->toResponse();
        }

        try {
            $siteDefault = $this->service->getSiteDefault();
        } catch (\Throwable $failure) {
            $this->recordFailure('site shell mode', 0, 'read', $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }

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
        try {
            return $this->readPageShellMode($request);
        } catch (\Throwable $failure) {
            $this->recordFailure('page shell mode', 0, 'request.read', $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }
    }

    private function readPageShellMode(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = RequestId::fromUrl($request, 'page_id');
        if ($pageId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::NotEnginePage);
        }

        try {
            $ctx = $this->service->resolveForPage($pageId);
        } catch (\Throwable $failure) {
            $this->recordFailure('page shell mode', $pageId, 'read', $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }
        return ApiResponse::ok($ctx->toArray())->toResponse();
    }

    /**
     * PUT /shell-mode
     *
     * Set the site-level default shell mode. Admin only.
     */
    public function updateSiteDefault(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return $this->updateSiteDefaultMode($request);
        } catch (\Throwable $failure) {
            $this->recordFailure('site shell mode', 0, 'request.uncertain', $failure);
            return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                'retryable' => false,
                'requires_read' => true,
                'detail' => 'The request result is uncertain. Read the current shell mode before another write.',
            ]);
        }
    }

    private function updateSiteDefaultMode(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
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

        try {
            $refreshQueued = $this->service->setSiteDefault($mode);
        } catch (StaleSourceGenerationException $exception) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $exception->scope()]);
        } catch (\Throwable $failure) {
            $this->recordFailure('site shell mode', 0, 'write.uncertain', $failure);
            return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                'retryable' => false,
                'requires_read' => true,
                'detail' => 'The write result is uncertain. Read the current shell mode before another write.',
            ]);
        }

        $response = [
            'mode'       => $mode->value,
            'mode_label' => $mode->label(),
        ];
        if (!$refreshQueued) {
            $response['rebuild_warning'] = self::REFRESH_WARNING;
        }

        return ApiResponse::ok($response)->toResponse();
    }

    /**
     * PUT /shell-mode/page/{page_id}
     *
     * Set the page-level shell mode override. Requires Engine-owned page.
     */
    public function updatePageMode(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return $this->updatePageShellMode($request);
        } catch (\Throwable $failure) {
            $this->recordFailure('page shell mode', 0, 'request.uncertain', $failure);
            return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                'retryable' => false,
                'requires_read' => true,
                'detail' => 'The request result is uncertain. Read the current page layout before another write.',
            ]);
        }
    }

    private function updatePageShellMode(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = RequestId::fromUrl($request, 'page_id');
        if ($pageId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

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
        } catch (\RuntimeException $failure) {
            $this->recordFailure('page shell mode', $pageId, 'working_canvas.transaction', $failure);
            return ApiResponse::error(ErrorMessage::WorkingCanvasRefreshFailed, [
                'failure_stage' => 'working_canvas_transaction',
                'detail' => 'The working canvas transaction could not be completed safely.',
                'retryable' => true,
            ]);
        } catch (\Throwable $failure) {
            $this->recordFailure('page shell mode', $pageId, 'working_canvas.unknown', $failure);
            return ApiResponse::error(ErrorMessage::WorkingCanvasRefreshFailed, [
                'failure_stage' => 'working_canvas_unknown',
                'detail' => 'The working canvas refresh stopped unexpectedly.',
                'retryable' => true,
            ]);
        }

        try {
            $this->pageLayout->update($pageId, $mode, $body['global_parts'] ?? null);
        } catch (StaleSourceGenerationException $exception) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $exception->scope()]);
        } catch (\RuntimeException $failure) {
            $this->recordFailure('page shell mode', $pageId, 'write.uncertain', $failure);
            return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                'failure_stage' => 'page_layout_transaction',
                'retryable' => false,
                'requires_read' => true,
                'detail' => 'The write result is uncertain. Read the current page layout before another write.',
            ]);
        } catch (\Throwable $failure) {
            $this->recordFailure('page shell mode', $pageId, 'write.uncertain', $failure);
            return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                'failure_stage' => 'page_layout_unknown',
                'retryable' => false,
                'requires_read' => true,
                'detail' => 'The write result is uncertain. Read the current page layout before another write.',
            ]);
        }

        try {
            $response = $this->service->resolveForPage($pageId)->toArray();
        } catch (\Throwable $failure) {
            $this->recordFailure('page shell mode', $pageId, 'readback', $failure);
            $response = [
                'mode' => $mode->value,
                'mode_label' => $mode->label(),
                'is_explicit' => true,
                'readback_warning' => self::READBACK_WARNING,
            ];
        }

        return ApiResponse::ok($response)->toResponse();
    }

    /**
     * GET /shell-mode/signals
     *
     * Detection hints for the mode chooser UI.
     */
    public function readSignals(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return ApiResponse::ok($this->service->detectSignals()->toArray())->toResponse();
        } catch (\Throwable $failure) {
            $this->recordFailure('shell mode signals', 0, 'read', $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }
    }

    private function refreshWorkingCanvas(int $pageId): void
    {
        if (!$this->workingCanvas instanceof WorkingCanvasRefresherInterface) {
            return;
        }

        $this->workingCanvas->refresh($pageId);
    }

    private function recordFailure(string $scope, int $ownerId, string $step, \Throwable $failure): void
    {
        try {
            $this->failureReporter?->report($scope, $ownerId, $step, $failure);
        } catch (\Throwable) {
            // A report failure cannot change the controlled REST response.
        }
    }
}
