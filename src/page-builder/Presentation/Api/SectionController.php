<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;

final class SectionController
{
    public function __construct(
        private readonly SectionService $sectionService,
        private readonly PermissionChecker $permissions,
        private readonly ?EditorLockWriteGuard $editorLock = null,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/sections/(?P<section_id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'patch'],
                'permission_callback' => [$this->permissions, 'canEdit'],
                'args'                => ['section_id' => RequestId::routeArgument()],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'delete'],
                'permission_callback' => [$this->permissions, 'canManage'],
                'args'                => ['section_id' => RequestId::routeArgument()],
            ],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/layout/(?P<page_id>\d+)/sections', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'restore'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args'                => ['page_id' => RequestId::routeArgument()],
        ]);
    }

    public function patch(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $sectionId = RequestId::fromUrl($request, 'section_id');
        $pageId    = absint($request->get_param('page_id'));
        $html      = $request->get_param('html');

        if ($sectionId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        if (!is_string($html) || !$html) {
            return ApiResponse::error(ErrorMessage::HtmlRequired);
        }

        if (!$pageId) {
            return ApiResponse::error(ErrorMessage::LayoutParamsRequired);
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        $ownershipError = $this->editorLock?->check($request, $pageId, 'section.patch');
        if ($ownershipError instanceof \WP_Error) {
            return $ownershipError;
        }

        try {
            return ApiResponse::ok($this->sectionService->patchHtml($pageId, $sectionId, $html))
                ->toResponse();
        } catch (SectionValidationException $e) {
            return ApiResponse::validationError($e);
        } catch (PageNotFoundException $e) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException $e) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (StaleSourceGenerationException $e) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
        }
    }

    public function delete(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $sectionId = RequestId::fromUrl($request, 'section_id');
        $pageId    = absint($request->get_param('page_id'));

        if ($sectionId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        if (!$pageId) {
            return ApiResponse::error(ErrorMessage::LayoutParamsRequired);
        }

        if (!$this->permissions->canManagePage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        $ownershipError = $this->editorLock?->check($request, $pageId, 'section.delete');
        if ($ownershipError instanceof \WP_Error) {
            return $ownershipError;
        }

        try {
            return ApiResponse::ok($this->sectionService->delete($pageId, $sectionId))->toResponse();
        } catch (PageNotFoundException $e) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException $e) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (StaleSourceGenerationException $e) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
        }
    }

    public function restore(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId   = RequestId::fromUrl($request, 'page_id');
        $sections = $request->get_param('sections');

        if ($pageId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        if (!is_array($sections)) {
            return ApiResponse::error(ErrorMessage::SectionsRequired);
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        $ownershipError = $this->editorLock?->check($request, $pageId, 'section.restore');
        if ($ownershipError instanceof \WP_Error) {
            return $ownershipError;
        }

        try {
            return ApiResponse::ok($this->sectionService->restore($pageId, $sections))->toResponse();
        } catch (SectionValidationException $e) {
            return ApiResponse::validationError($e);
        } catch (PageNotFoundException $e) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException $e) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (StaleSourceGenerationException $e) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
        }
    }
}
