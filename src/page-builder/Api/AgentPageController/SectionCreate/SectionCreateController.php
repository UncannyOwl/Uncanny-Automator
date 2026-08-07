<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\SectionCreate;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;

/**
 * Routes create_section to page append or blank reusable source bootstrap.
 */
final class SectionCreateController
{
    public function __construct(
        private readonly SectionService $sectionService,
        private readonly DatabaseSectionRepository $sections,
        private readonly PermissionChecker $permissions,
        private readonly CreateTargetResolver $targets,
        private readonly GlobalPartSourceCreator $globalPartSource,
        private readonly SectionCreateResponseFormatter $responses,
    ) {}

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = RequestId::fromUrl($request, 'page_id');
        if ($pageId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        $name = \sanitize_text_field($request->get_param('name') ?? '');
        $html = $request->get_param('html');
        $css = $request->get_param('css');

        if (!\is_string($html) || $html === '') {
            return ApiResponse::error(ErrorMessage::AgentMissingHtml);
        }
        if (!\is_string($css)) {
            $css = '';
        }

        $globalPartId = $this->targets->globalPartId($request, $pageId);
        if ($globalPartId > 0) {
            if (!$this->permissions->canEditPost($globalPartId)) {
                return ApiResponse::error(ErrorMessage::PageEditForbidden);
            }

            return $this->createGlobalPartSourceSection($globalPartId, $name, $html, $css);
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }
        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        try {
            $result = $this->sectionService->create(
                pageId: $pageId,
                sectionName: $name,
                content: ['html' => $html, 'css' => $css],
            );
        } catch (StaleSourceGenerationException $exception) {
            return $this->responses->stale('create_section', $exception);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionValidationException $exception) {
            return ApiResponse::validationError($exception);
        }

        $sections = $this->sections->findByPageId($pageId)->all();
        $last = \end($sections);

        return $this->responses->pageSuccess($pageId, $last, $result);
    }

    public function createGlobalPartSourceSection(
        int $globalPartId,
        string $name,
        string $html,
        string $css,
    ): \WP_REST_Response {
        return $this->globalPartSource->create($globalPartId, $name, $html, $css);
    }
}
