<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\PartRead;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;

/**
 * Resolves unified read_part requests for page sections.
 */
final class SectionPartReader
{
    public function __construct(
        private readonly SectionService $sectionService,
        private readonly DatabaseSectionRepository $sections,
        private readonly PermissionChecker $permissions,
        private readonly PartDetailPresenter $details,
    ) {}

    /**
     * @param list<string> $includes
     */
    public function readPart(\WP_REST_Request $request, array $includes): \WP_REST_Response|\WP_Error
    {
        $sectionId = \absint($request->get_param('section_id'));
        if ($sectionId <= 0) {
            return $this->textToolError('read_part', 400, 'missing_section_id', [
                'KIND: section',
                'NEXT STEP',
                'Retry with a valid section_id.',
            ]);
        }

        [$section, , $error] = $this->resolve($request);
        if ($error instanceof \WP_Error || !$section instanceof Section) {
            return $error;
        }

        return AgentTextResponse::ok(implode("\n", $this->details->sectionLines($section, $includes, $request)));
    }

    /**
     * @return array{0: ?Section, 1: int, 2: ?\WP_Error}
     */
    private function resolve(\WP_REST_Request $request): array
    {
        $sectionId = \absint($request->get_param('section_id'));

        try {
            $section = $this->sections->findById($sectionId);
        } catch (SectionNotFoundException) {
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

    /**
     * @param list<string> $lines
     */
    private function textToolError(string $toolName, int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }
}
