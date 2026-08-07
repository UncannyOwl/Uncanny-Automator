<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Export\StaticPageExportService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class StaticExportController
{
    public function __construct(
        private readonly StaticPageExportService $exportService,
        private readonly SectionRepositoryInterface $sections,
        private readonly PermissionChecker $permissions,
        private readonly PageDetailsPortInterface $pageDetails,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/static-export/(?P<page_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'read'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args'                => ['page_id' => RequestId::routeArgument()],
        ]);
    }

    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = RequestId::fromUrl($request, 'page_id');
        if ($pageId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sections->isOwnedPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        try {
            $details = $this->pageDetails->find($pageId);
            if ($details === null) {
                return ApiResponse::error(ErrorMessage::PageNotFound);
            }

            return ApiResponse::ok($this->exportService->buildForPage(
                $pageId,
                $details->title(),
                $details->permalink(),
            )->toArray())->toResponse();
        } catch (PageNotFoundException $e) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (\Throwable $e) {
            return ApiResponse::error(ErrorMessage::StaticExportFailed);
        }
    }
}
