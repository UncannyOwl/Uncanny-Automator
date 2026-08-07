<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\DesignStyles\CommitsDesignStyles;
use UncannyPageBuilder\Application\DesignStyles\DesignStyleChange;
use UncannyPageBuilder\Application\DesignStyles\DesignStyleCommitRequest;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\DesignStyles\DesignWriteScope;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Handles durable element-style writes for Agent page edits.
 *
 * Target parsing remains separate from the application-layer design commit.
 * The commit service owns validation and persistence; this controller preserves
 * the Agent tool's authorization, status, and line-oriented response contract.
 */
final class DesignStyleController
{
    public function __construct(
        private readonly SectionService $sectionService,
        private readonly SectionRepositoryInterface $sections,
        private readonly PermissionChecker $permissions,
        private readonly CommitsDesignStyles $designStyles,
    ) {}

    public function update(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$section, $pageId, $error] = $this->resolveSection($request);
        if ($error) {
            return $error;
        }

        $sectionId = (int) $section->id();
        $rawChanges = $request->get_param('changes');

        if (!is_array($rawChanges) || $rawChanges === []) {
            return $this->textToolError('update_element_style', 400, 'missing_changes', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Retry with a non-empty changes array from read_part include=design_targets.',
            ]);
        }

        $changes = [];
        foreach ($rawChanges as $rawChange) {
            if (is_array($rawChange)) {
                $changes[] = DesignStyleChange::fromArray($rawChange);
            }
        }
        if ($changes === []) {
            return $this->textToolError('update_element_style', 400, 'invalid_changes', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Retry with change objects that include property, value, viewport, state, and target.',
            ]);
        }

        $result = $this->designStyles->commit(new DesignStyleCommitRequest(
            scope: DesignWriteScope::Element,
            pageId: $pageId,
            changes: $changes,
            capabilities: $this->capabilitiesForPage($pageId),
            sectionId: $sectionId,
        ));
        $payload = $result->toArray();

        if (!$result->isSuccess()) {
            return $this->textToolError('update_element_style', $this->styleErrorStatus($payload), $this->styleErrorCode($payload), [
                'SECTION_ID: ' . $sectionId,
                'MESSAGE: ' . $result->message(),
                ...$this->rejectedStyleLines($payload['rejected'] ?? []),
                'NEXT STEP',
                'Call read_part include=design_targets again and retry with a supported property, target, viewport, and state.',
            ]);
        }

        $sectionData = is_array($payload['refreshed'] ?? null) && is_array($payload['refreshed']['section'] ?? null)
            ? $payload['refreshed']['section']
            : [];
        $elementId = (string) ($sectionData['element_id'] ?? '');
        $compiledSelector = $this->elementStylePreviewSelector($sectionId, $elementId);
        $sourcePath = $this->firstChangeSourcePath($changes);
        $tag = $this->firstChangeTag($changes);

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: update_element_style',
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            '',
            'TARGET',
            'TAG: ' . ($tag !== '' ? $tag : 'unknown'),
            'SOURCE_PATH: ' . ($sourcePath !== '' ? $sourcePath : 'unknown'),
            'ELEMENT_ID: ' . ($elementId !== '' ? $elementId : 'unknown'),
            'COMPILED_SELECTOR: ' . ($compiledSelector !== '' ? $compiledSelector : 'unknown'),
            'PROMOTED: ' . (!empty($sectionData['promoted']) ? 'yes' : 'no'),
            '',
            'APPLIED',
            ...$this->appliedStyleLines($payload['applied'] ?? []),
            '',
            'STRUCTURED CSS',
            ...$this->writtenCssLines($compiledSelector, $payload['applied'] ?? []),
            '',
            'NEXT STEP',
            'Call read_part include=design_targets to confirm the change.',
        ]));
    }

    /**
     * Resolve a section write against its actual owning page.
     *
     * @return array{0: ?\UncannyPageBuilder\Domain\Section\Section, 1: int, 2: ?\WP_Error}
     */
    private function resolveSection(\WP_REST_Request $request): array
    {
        $sectionId = RequestId::positive($request->get_param('section_id'));
        $pageIdValue = $request->get_param('page_id');
        $requestedPageId = $pageIdValue === null ? 0 : RequestId::positive($pageIdValue);
        if ($sectionId === null || $requestedPageId === null) {
            return [null, 0, ApiResponse::error(ErrorMessage::InvalidRouteId)];
        }

        if ($requestedPageId !== 0) {
            if (!$this->permissions->canEditPage($requestedPageId)) {
                return [null, 0, ApiResponse::error(ErrorMessage::PageEditForbidden)];
            }
            if (!$this->sectionService->isPageOwned($requestedPageId)) {
                return [null, 0, ApiResponse::error(ErrorMessage::PageNotOwned)];
            }
        }

        try {
            $section = $this->sections->findById($sectionId);
        } catch (SectionNotFoundException) {
            if ($requestedPageId !== 0) {
                return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFoundOnPage)];
            }
            // A global part post ID is a common Agent tool-selection mistake.
            if (\get_post_type($sectionId) === 'upb_global_part') {
                return [null, 0, ApiResponse::error(ErrorMessage::AgentWrongTool)];
            }

            return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFound)];
        }

        $pageId = $section->pageId();
        if ($requestedPageId !== 0 && $requestedPageId !== $pageId) {
            return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFoundOnPage)];
        }
        if ($requestedPageId === 0) {
            if (!$this->permissions->canEditPage($pageId)) {
                return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFound)];
            }
            if (!$this->sectionService->isPageOwned($pageId)) {
                return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFound)];
            }
        }

        return [$section, $pageId, null];
    }

    /**
     * @return array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish: bool}
     */
    private function capabilitiesForPage(int $pageId): array
    {
        return [
            'can_edit' => $this->permissions->canEditPage($pageId),
            'can_manage' => $this->permissions->canManagePage($pageId),
            'can_upload' => $this->permissions->canUploadFiles(),
            'can_publish' => $this->permissions->canPublishPost($pageId),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function styleErrorStatus(array $payload): int
    {
        foreach ($payload['rejected'] ?? [] as $rejected) {
            $reason = is_array($rejected) ? (string) ($rejected['reason'] ?? '') : '';
            if ($reason === 'missing_section_id') {
                return 400;
            }
        }

        return 422;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function styleErrorCode(array $payload): string
    {
        foreach ($payload['rejected'] ?? [] as $rejected) {
            if (is_array($rejected) && (string) ($rejected['reason'] ?? '') !== '') {
                return (string) $rejected['reason'];
            }
        }

        return 'element_style_update_failed';
    }

    /**
     * @param mixed $rejected
     * @return list<string>
     */
    private function rejectedStyleLines(mixed $rejected): array
    {
        if (!is_array($rejected) || $rejected === []) {
            return ['REJECTED: none'];
        }

        $lines = ['REJECTED'];
        foreach ($rejected as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = '- ' . (string) ($item['property'] ?? '') . ': ' . (string) ($item['reason'] ?? '');
        }

        return $lines;
    }

    /**
     * @param list<DesignStyleChange> $changes
     */
    private function firstChangeSourcePath(array $changes): string
    {
        foreach ($changes as $change) {
            return (string) ($change->sourcePath() ?? '');
        }

        return '';
    }

    /**
     * @param list<DesignStyleChange> $changes
     */
    private function firstChangeTag(array $changes): string
    {
        foreach ($changes as $change) {
            return (string) ($change->tag() ?? '');
        }

        return '';
    }

    /**
     * @param mixed $applied
     * @return list<string>
     */
    private function appliedStyleLines(mixed $applied): array
    {
        if (!is_array($applied) || $applied === []) {
            return ['none'];
        }

        $lines = [];
        foreach ($applied as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = sprintf(
                '%s %s %s: %s',
                (string) ($item['viewport'] ?? 'desktop'),
                (string) ($item['state'] ?? 'normal'),
                (string) ($item['property'] ?? ''),
                (string) ($item['value'] ?? ''),
            );
        }

        return $lines !== [] ? $lines : ['none'];
    }

    /**
     * @param mixed $applied
     * @return list<string>
     */
    private function writtenCssLines(string $selector, mixed $applied): array
    {
        if ($selector === '' || !is_array($applied) || $applied === []) {
            return ['not available'];
        }

        $lines = [$selector . ' {'];
        foreach ($applied as $item) {
            if (!is_array($item)) {
                continue;
            }
            $property = trim((string) ($item['property'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));
            if ($property !== '') {
                $lines[] = '  ' . $property . ': ' . trim(preg_replace('/\s*!important\s*$/i', '', $value) ?? $value) . ';';
            }
        }
        $lines[] = '}';

        return $lines;
    }

    private function elementStylePreviewSelector(int $sectionId, string $elementId): string
    {
        $elementId = trim($elementId);
        if ($sectionId <= 0 || $elementId === '') {
            return '';
        }

        $sectionSelector = '#upb-section-' . $sectionId;

        return $elementId === 'upb-section-' . $sectionId
            ? $sectionSelector
            : $sectionSelector . ' #' . $elementId;
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
