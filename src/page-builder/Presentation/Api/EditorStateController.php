<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Editor\EditorStateService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\ErrorMessage;

final class EditorStateController
{
    public function __construct(
        private readonly EditorStateService $editorStateService,
        private readonly SectionService $sectionService,
        private readonly PermissionChecker $permissions,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/editor/state', [
            'methods'             => 'GET',
            'callback'            => [$this, 'read'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);
    }

    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = absint($request->get_param('page_id'));
        $globalPartId = absint($request->get_param('global_part_id'));

        if ($globalPartId > 0) {
            if (!$this->permissions->canEditPost($globalPartId)) {
                return ApiResponse::error(ErrorMessage::GlobalPartEditForbidden);
            }

            $state = $this->editorStateService->buildForGlobalPart(
                $globalPartId,
                $this->capabilitiesFor($globalPartId),
            );

            if ($state === null) {
                return ApiResponse::error(ErrorMessage::PageNotFoundGeneric);
            }

            return ApiResponse::ok($state->toArray())->toResponse();
        }

        if ($pageId <= 0) {
            return ApiResponse::error(ErrorMessage::MissingPageId);
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        $state = $this->editorStateService->buildForPage(
            $pageId,
            $this->capabilitiesFor($pageId, true),
        );

        return ApiResponse::ok($state->toArray())->toResponse();
    }

    /** @return array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish: bool} */
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
        ];
    }
}
