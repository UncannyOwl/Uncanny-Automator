<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Controls\ControlContext;
use UncannyPageBuilder\Application\Controls\ControlDispatcher;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlRegistry;
use UncannyPageBuilder\Application\Controls\ControlStateService;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class ControlController
{
    /** These lifecycle decisions remain human-only even in legacy registries. */
    private const HUMAN_ONLY_CONTROL_IDS = [
        'history.undo',
        'history.redo',
        'page.manual_changes.commit',
        'page.resume_draft',
        'page.save_draft',
        'page.save_published',
        'page.make_live',
        'page.switch_to_draft',
        'page.publish',
    ];

    public function __construct(
        private readonly ControlStateService $stateService,
        private readonly ControlDispatcher $dispatcher,
        private readonly SectionRepositoryInterface $sectionRepository,
        private readonly PermissionChecker $permissions,
        private readonly ?ControlRegistry $registry = null,
        private readonly ?EditorLockWriteGuard $editorLock = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/editor/controls', [
            'methods'             => 'GET',
            'callback'            => [$this, 'read'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/editor/controls/(?P<control_id>[a-z0-9_.-]+)/invoke', [
            'methods'             => 'POST',
            'callback'            => [$this, 'invoke'],
            'permission_callback' => [$this, 'canInvoke'],
        ]);
    }

    public function canInvoke(\WP_REST_Request $request): bool|\WP_Error
    {
        if (!$this->permissions->canEdit($request)) {
            return false;
        }

        if ($this->isBearerEditorWriteInvocation($request)) {
            return ApiResponse::error(ErrorMessage::ControlInvokeForbidden);
        }

        return true;
    }

    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $context = $this->contextFromRequest($request);
            if ($context instanceof \WP_Error) {
                return $context;
            }

            return ApiResponse::ok($this->stateService->build($context))->toResponse();
        } catch (\Throwable $failure) {
            $this->recordFailure('read', $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed);
        }
    }

    public function invoke(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return $this->invokeRequest($request);
        } catch (\Throwable $failure) {
            $this->recordFailure('invoke', $failure);
            $controlId = trim((string) $request->get_param('control_id'));
            if ($this->registry?->get($controlId)?->writesEditorState()) {
                return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                    'control_id' => $controlId,
                    'retryable' => false,
                    'requires_read' => true,
                    'detail' => 'The write result is uncertain. Read the current editor source before another write.',
                ]);
            }

            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }
    }

    private function invokeRequest(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if ($this->isBearerEditorWriteInvocation($request)) {
            return ApiResponse::error(ErrorMessage::ControlInvokeForbidden);
        }

        $controlId = trim((string) $request->get_param('control_id'));
        if ($controlId === '') {
            return ApiResponse::error(ErrorMessage::ControlUnknown);
        }

        $context = $this->contextFromRequest($request);
        if ($context instanceof \WP_Error) {
            return $context;
        }

        $definition = $this->registry?->get($controlId);
        if ($definition?->writesEditorState()) {
            $targetId = $context->globalPartId() > 0
                ? $context->globalPartId()
                : $context->pageId();
            $ownershipError = $this->editorLock?->check(
                $request,
                $targetId,
                'control.' . $controlId,
            );
            if ($ownershipError instanceof \WP_Error) {
                return $ownershipError;
            }
        }

        $extra = $request->get_param('extra');
        $invokeRequest = new ControlInvokeRequest(
            controlId: $controlId,
            context: $context,
            value: $request->get_param('value'),
            extra: is_array($extra) ? $extra : [],
        );

        $result = $this->dispatcher->invoke($invokeRequest);
        if ($result instanceof \WP_Error) {
            return $result;
        }

        return ApiResponse::ok($result->toArray())->toResponse();
    }

    private function recordFailure(string $step, \Throwable $failure): void
    {
        try {
            $this->failureReporter?->report('editor controls', 0, $step, $failure);
        } catch (\Throwable) {
            // A report failure cannot change the controlled REST response.
        }
    }

    private function isBearerEditorWriteInvocation(\WP_REST_Request $request): bool
    {
        if (!$this->permissions->isBearerRequest($request)) {
            return false;
        }

        $controlId = trim((string) $request->get_param('control_id'));
        if (in_array($controlId, self::HUMAN_ONLY_CONTROL_IDS, true)) {
            return true;
        }

        /*
         * Agent mutations have guarded, auto-saved facades that also mark the
         * durable draft active. Do not let bearer callers bypass that boundary
         * through a browser control handler.
         */
        return $this->registry?->get($controlId)?->writesEditorState() === true;
    }

    private function contextFromRequest(\WP_REST_Request $request): ControlContext|\WP_Error
    {
        $pageId = absint($request->get_param('page_id'));
        $globalPartId = absint($request->get_param('global_part_id'));
        $userId = (int) get_current_user_id();

        if ($globalPartId > 0) {
            if (!$this->permissions->canEditPost($globalPartId)) {
                return ApiResponse::error(ErrorMessage::GlobalPartEditForbidden);
            }

            return ControlContext::forGlobalPart($globalPartId, $userId, $this->capabilitiesFor($globalPartId));
        }

        if ($pageId <= 0) {
            return ApiResponse::error(ErrorMessage::MissingPageId);
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionRepository->isOwnedPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        return ControlContext::forPage($pageId, $userId, $this->capabilitiesFor($pageId, true));
    }

    /** @return array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish: bool, can_edit_custom_javascript: bool} */
    private function capabilitiesFor(int $postId, bool $page = false): array
    {
        return [
            'can_edit'    => $page
                ? $this->permissions->canEditPage($postId)
                : $this->permissions->canEditPost($postId),
            'can_manage'  => $page
                ? $this->permissions->canManagePage($postId)
                : $this->permissions->canManagePost($postId),
            'can_upload'  => $this->permissions->canUploadFiles(),
            'can_publish' => $this->permissions->canPublishPost($postId),
            'can_edit_custom_javascript' => $this->permissions->canCapability('unfiltered_html'),
        ];
    }
}
