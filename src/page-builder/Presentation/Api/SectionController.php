<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
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
        private readonly ?FailureReporterInterface $failureReporter = null,
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
        $pageId = 0;
        $writeStarted = false;

        try {
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
                $writeStarted = true;
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
            } catch (\Throwable $failure) {
                $this->recordFailure('page sections', $pageId, 'patch.uncertain', $failure);
                return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                    'retryable' => false,
                    'requires_read' => true,
                    'detail' => 'The write result is uncertain. Read the current layout before another write.',
                ]);
            }
        } catch (\Throwable $failure) {
            return $this->unexpectedWriteFailure($pageId, 'patch', $writeStarted, $failure);
        }
    }

    public function delete(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = 0;
        $writeStarted = false;

        try {
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
                $writeStarted = true;
                return ApiResponse::ok($this->sectionService->delete($pageId, $sectionId))->toResponse();
            } catch (PageNotFoundException $e) {
                return ApiResponse::error(ErrorMessage::PageNotFound);
            } catch (SectionNotFoundException $e) {
                return ApiResponse::error(ErrorMessage::SectionNotFound);
            } catch (StaleSourceGenerationException $e) {
                return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
            } catch (\Throwable $failure) {
                $this->recordFailure('page sections', $pageId, 'delete.uncertain', $failure);
                return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                    'retryable' => false,
                    'requires_read' => true,
                    'detail' => 'The write result is uncertain. Read the current layout before another write.',
                ]);
            }
        } catch (\Throwable $failure) {
            return $this->unexpectedWriteFailure($pageId, 'delete', $writeStarted, $failure);
        }
    }

    public function restore(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = 0;
        $writeStarted = false;

        try {
            $resolvedPageId = RequestId::fromUrl($request, 'page_id');
            $sections = $request->get_param('sections');

            if ($resolvedPageId === null) {
                return ApiResponse::error(ErrorMessage::InvalidRouteId);
            }
            $pageId = $resolvedPageId;

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
                $writeStarted = true;
                return ApiResponse::ok($this->sectionService->restore($pageId, $sections))->toResponse();
            } catch (SectionValidationException $e) {
                return ApiResponse::validationError($e);
            } catch (PageNotFoundException $e) {
                return ApiResponse::error(ErrorMessage::PageNotFound);
            } catch (SectionNotFoundException $e) {
                return ApiResponse::error(ErrorMessage::SectionNotFound);
            } catch (StaleSourceGenerationException $e) {
                return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
            } catch (\Throwable $failure) {
                $this->recordFailure('page sections', $pageId, 'restore.uncertain', $failure);
                return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                    'retryable' => false,
                    'requires_read' => true,
                    'detail' => 'The write result is uncertain. Read the current layout before another write.',
                ]);
            }
        } catch (\Throwable $failure) {
            return $this->unexpectedWriteFailure($pageId, 'restore', $writeStarted, $failure);
        }
    }

    private function unexpectedWriteFailure(
        int $pageId,
        string $operation,
        bool $writeStarted,
        \Throwable $failure,
    ): \WP_Error {
        $this->recordFailure(
            'page sections',
            $pageId,
            $operation . ($writeStarted ? '.response' : '.preflight'),
            $failure,
        );

        return ApiResponse::error($writeStarted ? ErrorMessage::WriteResultUncertain : ErrorMessage::ControlInvokeFailed, $writeStarted
            ? [
                'retryable' => false,
                'requires_read' => true,
                'detail' => 'The write result is uncertain. Read the current layout before another write.',
            ]
            : ['retryable' => true]);
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
