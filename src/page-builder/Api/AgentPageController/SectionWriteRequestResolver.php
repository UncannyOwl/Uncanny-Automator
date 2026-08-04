<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;

/**
 * Resolves an Agent section write against one revision-bearing collection.
 *
 * Target validation and persistence must consume the returned collection so a
 * concurrent edit cannot land between a validation read and a fresh save.
 */
final class SectionWriteRequestResolver
{
    public function __construct(
        private readonly SectionService $sectionService,
        private readonly DatabaseSectionRepository $sections,
        private readonly PermissionChecker $permissions,
    ) {}

    /**
     * @return array{0: ?Section, 1: int, 2: ?\WP_Error, 3: ?SectionCollection}
     */
    public function resolve(\WP_REST_Request $request): array
    {
        [$resolved, $pageId, $error] = $this->resolveSection($request);
        if ($error instanceof \WP_Error || !$resolved instanceof Section) {
            return [null, 0, $error, null];
        }

        try {
            $sections = $this->sections->findByPageId($pageId);
            $section = $sections->getById((int) $resolved->id());
        } catch (SectionNotFoundException) {
            return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFound), null];
        }

        if ($section->pageId() !== $pageId) {
            return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFoundOnPage), null];
        }

        return [$section, $pageId, null, $sections];
    }

    /**
     * @return array{0: ?Section, 1: int, 2: ?\WP_Error}
     */
    private function resolveSection(\WP_REST_Request $request): array
    {
        $sectionId = \absint($request->get_param('section_id'));

        try {
            $section = $this->sections->findById($sectionId);
        } catch (SectionNotFoundException) {
            if (\get_post_type($sectionId) === 'upb_global_part') {
                return [null, 0, ApiResponse::error(ErrorMessage::AgentWrongTool)];
            }

            return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFound)];
        }

        $pageId = $section->pageId();
        $requestedPageId = \absint($request->get_param('page_id') ?: 0);
        if ($requestedPageId !== 0 && $requestedPageId !== $pageId) {
            return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFoundOnPage)];
        }
        if (!$this->permissions->canEditPage($pageId)) {
            return [null, 0, ApiResponse::error(ErrorMessage::PageEditForbidden)];
        }
        if (!$this->sectionService->isPageOwned($pageId)) {
            return [null, 0, ApiResponse::error(ErrorMessage::PageNotOwned)];
        }

        return [$section, $pageId, null];
    }
}
