<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Handles Agent-facing page section lifecycle operations.
 *
 * Reorder and delete remain revision-aware SectionService operations. The
 * repository is used only to resolve the delete target before authorization,
 * preserving the existing rule that permissions are checked against the
 * section's actual owning page rather than an untrusted request page ID.
 */
final class SectionManagementController
{
    public function __construct(
        private readonly SectionService $sections,
        private readonly SectionRepositoryInterface $sectionRepository,
        private readonly PermissionChecker $permissions,
        private readonly ?PageDetailsPortInterface $pageDetails = null,
    ) {}

    public function manage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = \absint($request->get_param('page_id'));
        $operation = trim((string) ($request->get_param('operation') ?? ''));

        if (!in_array($operation, ['reorder', 'delete'], true)) {
            return $this->textToolError('manage_sections', 400, 'invalid_operation', [
                'PAGE_ID: ' . $pageId,
                'OPERATION: ' . ($operation !== '' ? $operation : 'missing'),
                'NEXT STEP',
                'Retry with operation reorder or delete.',
            ]);
        }

        if ($operation === 'reorder') {
            return $this->reorder($request);
        }

        $result = $this->performDeleteSection($request);
        if ($result instanceof \WP_Error) {
            return $result;
        }

        // Preserve the lifecycle facade's post-delete layout refresh.
        $this->sections->getLayout((int) ($result['page_id'] ?? $pageId));

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_sections',
            'RESULT: success',
            'PAGE_ID: ' . (string) ($result['page_id'] ?? $pageId),
            'OPERATION: delete',
            'DELETED_SECTION_ID: ' . (string) ($result['section_id'] ?? 0),
            'SECTIONS_REMAINING: ' . (string) ($result['sections'] ?? ''),
            'PREVIEW: ' . (string) ($result['preview'] ?? ''),
            '',
            'NEXT STEP',
            'Call read_page_context before making another section write.',
        ]));
    }

    public function reorder(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = \absint($request->get_param('page_id'));
        $sectionIds = $request->get_param('section_ids');

        if (!is_array($sectionIds) || $sectionIds === []) {
            return ApiResponse::error(ErrorMessage::AgentMissingSectionIds);
        }

        $sectionIds = array_map('absint', $sectionIds);

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }
        if (!$this->sections->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        try {
            $result = $this->sections->reorder($pageId, $sectionIds);
        } catch (\InvalidArgumentException) {
            return ApiResponse::error(ErrorMessage::AgentMissingSectionIds);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('manage_sections', $exception, 'reorder');
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        }

        $lines = [
            'TOOL: manage_sections',
            'RESULT: success',
            'OPERATION: reorder',
            'PAGE_ID: ' . $pageId,
            '',
            'SECTIONS',
        ];
        foreach ((array) ($result['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $lines[] = '- SECTION_ID: ' . (string) ($section['id'] ?? '');
            $lines[] = '  POSITION: ' . (string) ($section['position'] ?? '');
            $lines[] = '  NAME: ' . (string) ($section['name'] ?? '');
        }
        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Call read_page_context to confirm the new section order.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    public function delete(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $result = $this->performDeleteSection($request);
        if ($result instanceof \WP_Error) {
            return $result;
        }

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_sections',
            'RESULT: success',
            'OPERATION: delete',
            'PAGE_ID: ' . (string) ($result['page_id'] ?? 0),
            'DELETED_SECTION_ID: ' . (string) ($result['section_id'] ?? 0),
            'SECTIONS_REMAINING: ' . (string) ($result['sections'] ?? ''),
            'PREVIEW: ' . (string) ($result['preview'] ?? ''),
            '',
            'NEXT STEP',
            'Call read_page_context before making another section write.',
        ]));
    }

    /**
     * Execute the guarded delete path shared by the public lifecycle methods.
     *
     * @return array{page_id: int, section_id: int, sections: int, preview: string}|\WP_Error
     */
    private function performDeleteSection(\WP_REST_Request $request): array|\WP_Error
    {
        $sectionId = \absint($request->get_param('section_id'));
        $requestedPageId = \absint($request->get_param('page_id') ?? 0);

        try {
            $section = $this->sectionRepository->findById($sectionId);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        }

        $pageId = $section->pageId();
        if ($requestedPageId !== 0 && $requestedPageId !== $pageId) {
            return ApiResponse::error(ErrorMessage::SectionNotFoundOnPage);
        }

        if (!$this->permissions->canManagePage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }
        if (!$this->sections->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        try {
            $result = $this->sections->delete($pageId, $sectionId);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('manage_sections', $exception);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        }

        return [
            'page_id' => (int) ($result['page_id'] ?? $pageId),
            'section_id' => $sectionId,
            'sections' => (int) ($result['sections'] ?? 0),
            'preview' => $this->pagePreviewUrl($pageId, (string) ($result['preview'] ?? '')),
        ];
    }

    /**
     * Build a text-protocol error body.
     *
     * `$operation` is emitted directly after `RESULT:`, matching where the
     * lifecycle facade used to inject it when it wrapped a sub-operation.
     *
     * @param list<string> $lines
     */
    private function textToolError(
        string $toolName,
        int $status,
        string $code,
        array $lines,
        ?string $operation = null,
    ): \WP_REST_Response {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            ...($operation !== null ? ['OPERATION: ' . $operation] : []),
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }

    private function staleSourceToolError(
        string $toolName,
        StaleSourceGenerationException $exception,
        ?string $operation = null,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 409, 'stale_source_generation', [
            'SCOPE: ' . $exception->scope(),
            'DETAIL: Page Builder source changed while this write was running.',
            'NEXT STEP',
            'Call read_page_context or read_part again, then reapply the change to the current source.',
        ], $operation);
    }

    private function pagePreviewUrl(int $pageId, string $fallback = ''): string
    {
        $previewUrl = $this->pageDetails?->find($pageId)?->previewUrl();

        return is_string($previewUrl) && $previewUrl !== '' ? $previewUrl : $fallback;
    }
}
