<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\Controls\PageDetails;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiff;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiffer;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;
use UncannyPageBuilder\Infrastructure\Section\DynamicRegionToken;

/**
 * Resolves, previews, and persists section source against one guarded snapshot.
 */
final class SectionSourceWriter
{
    /** @var list<string>|null */
    private ?array $maskableBindingIds = null;

    public function __construct(
        private readonly SectionService $sectionService,
        private readonly DatabaseSectionRepository $sections,
        private readonly PermissionChecker $permissions,
        private readonly CompactSourceDiffer $sourceDiffer,
        private readonly ?BindingRegistry $bindingRegistry = null,
        private readonly ?PageDetailsPortInterface $pageDetails = null,
    ) {}

    /**
     * @return array{0: ?Section, 1: int, 2: ?\WP_Error}
     */
    public function resolve(\WP_REST_Request $request): array
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
     * @return array{section_id: int, page_id: int, position: int, name: string, preview: string, html_diff: CompactSourceDiff, css_diff: CompactSourceDiff, warnings: string[]}|\WP_REST_Response|\WP_Error
     */
    public function save(
        string $toolName,
        int $pageId,
        Section $section,
        string $html,
        string $css,
        ?string $name = null,
        bool $requireExactCss = false,
    ): array|\WP_REST_Response|\WP_Error {
        $preview = $this->preview(
            $toolName,
            $pageId,
            $section,
            $html,
            $css,
            $name,
            $requireExactCss,
        );
        if ($preview instanceof \WP_REST_Response || $preview instanceof \WP_Error) {
            return $preview;
        }

        try {
            $result = $this->sectionService->replaceLoadedSectionSource(
                pageId: $pageId,
                sections: $preview['sections'],
                sectionId: (int) $section->id(),
                sectionName: $name ?? $section->name(),
                content: ['html' => $preview['html'], 'css' => $preview['css']],
                requireExactCss: $requireExactCss,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError($toolName, 400, 'invalid_section_write', [
                'SECTION_ID: ' . (string) $section->id(),
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Read the section again and retry.',
            ]);
        } catch (SectionValidationException $exception) {
            return $this->textToolError($toolName, 422, 'section_validation_failed', [
                'SECTION_ID: ' . (string) $section->id(),
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Fix the replacement source and retry.',
            ]);
        } catch (CssRuleIntegrityException $exception) {
            return $this->cssRuleIntegrityError($toolName, $pageId, (int) $section->id(), $exception);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError($toolName, $exception);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (\RuntimeException $exception) {
            return $this->sectionWriteError($toolName, $pageId, (int) $section->id(), $exception);
        }

        return [
            'section_id' => (int) ($result['section_id'] ?? $section->id()),
            'page_id' => $pageId,
            'position' => $section->position(),
            'name' => $name ?? $section->name(),
            'preview' => $this->pagePreviewUrl($pageId, \get_permalink($pageId) ?: ''),
            'html_diff' => $preview['html_diff'],
            'css_diff' => $preview['css_diff'],
            'warnings' => \array_values(\array_unique([
                ...($preview['warnings'] ?? []),
                ...($result['warnings'] ?? []),
            ])),
        ];
    }

    /**
     * @return array{html: string, css: string, html_diff: CompactSourceDiff, css_diff: CompactSourceDiff, warnings: string[], sections: SectionCollection}|\WP_REST_Response|\WP_Error
     */
    public function preview(
        string $toolName,
        int $pageId,
        Section $section,
        string $html,
        string $css,
        ?string $name = null,
        bool $requireExactCss = false,
    ): array|\WP_REST_Response|\WP_Error {
        try {
            $sections = $this->sections->findByPageId($pageId);
            $currentSection = $sections->getById((int) $section->id());
            if (!$this->sameSectionSourceSnapshot($section, $currentSection)) {
                return $this->textToolError($toolName, 409, 'stale_source_generation', [
                    'PAGE_ID: ' . $pageId,
                    'SECTION_ID: ' . (string) $section->id(),
                    'DETAIL: The section changed after this edit was prepared. Nothing was saved.',
                    'NEXT STEP',
                    'Call read_part again and retry against the current source.',
                ]);
            }

            $preview = $this->sectionService->previewLoadedSectionSource(
                pageId: $pageId,
                sections: $sections,
                sectionId: (int) $section->id(),
                sectionName: $name ?? $section->name(),
                content: ['html' => $html, 'css' => $css],
                requireExactCss: $requireExactCss,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError($toolName, 400, 'invalid_section_write', [
                'SECTION_ID: ' . (string) $section->id(),
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Read the section again and retry.',
            ]);
        } catch (SectionValidationException $exception) {
            return $this->textToolError($toolName, 422, 'section_validation_failed', [
                'SECTION_ID: ' . (string) $section->id(),
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Fix the replacement source and retry.',
            ]);
        } catch (CssRuleIntegrityException $exception) {
            return $this->cssRuleIntegrityError($toolName, $pageId, (int) $section->id(), $exception);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError($toolName, $exception);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        }

        return [
            'html' => $preview['html'],
            'css' => $preview['css'],
            'html_diff' => $this->sourceDiffer->diff(
                'HTML DIFF',
                $this->maskForAgent($section->content()->html()),
                $this->maskForAgent((string) $preview['html']),
            ),
            'css_diff' => $this->sourceDiffer->diff(
                'CSS DIFF',
                $section->content()->css(),
                $preview['css'],
            ),
            'warnings' => $preview['warnings'] ?? [],
            'sections' => $sections,
        ];
    }

    private function sameSectionSourceSnapshot(Section $expected, Section $current): bool
    {
        return $expected->id() === $current->id()
            && $expected->pageId() === $current->pageId()
            && $expected->position() === $current->position()
            && $expected->name() === $current->name()
            && $expected->content()->toArray() === $current->content()->toArray();
    }

    private function maskForAgent(string $html): string
    {
        $this->maskableBindingIds ??= $this->bindingRegistry?->fullyProjectedBindingIds() ?? [];

        return DynamicRegionToken::encodeForCodeEditor(
            $html,
            $this->maskableBindingIds,
            payloadMasks: false,
        );
    }

    private function cssRuleIntegrityError(
        string $toolName,
        int $pageId,
        int $sectionId,
        CssRuleIntegrityException $exception,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 422, 'css_rule_integrity_failed', [
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            'DETAIL: ' . $exception->getMessage(),
            'NEXT STEP',
            match ($exception->reason()) {
                CssRuleIntegrityException::MALFORMED_SOURCE => 'Call read_part include=source, repair the unbalanced CSS with mode=source_replace, then retry the css_rule edit.',
                CssRuleIntegrityException::AMBIGUOUS_COMMENT => 'Call read_part include=source and use mode=source_patch with an exact current substring so the comment and intended replacement are both explicit.',
                CssRuleIntegrityException::AMBIGUOUS_DECLARATION_BOUNDARY => 'Call read_part include=source and repair the declaration boundary with mode=source_patch or mode=source_replace before retrying css_rule.',
                CssRuleIntegrityException::MULTIPLE_GLOBAL_PART_SOURCE_ROWS => 'Call read_part kind=global_part include=source. Migrate or explicitly consolidate every stored source row before retrying the write.',
                CssRuleIntegrityException::UNPRESERVABLE_GLOBAL_PART_SOURCE_ROWS => 'Ask an administrator to repair or explicitly consolidate the stored legacy global-part rows before retrying the write.',
                default => 'Call read_part include=source again. Preserve or explicitly repair the rejected CSS with mode=source_replace before retrying the requested edit.',
            },
        ]);
    }

    private function sectionWriteError(
        string $toolName,
        int $pageId,
        int $sectionId,
        \RuntimeException $exception,
    ): \WP_REST_Response {
        if ($exception instanceof SourceTransactionsUnavailableException) {
            return $this->textToolError($toolName, 500, 'source_transactions_unavailable', [
                'KIND: section',
                'PAGE_ID: ' . $pageId,
                'SECTION_ID: ' . $sectionId,
                'DETAIL: ' . $exception->getMessage(),
                'RETRY_SAFETY: Nothing was saved by this operation.',
                'NEXT STEP',
                'Convert the named database table to InnoDB. Then call read_part include=source again and retry against the current source.',
            ]);
        }

        return $this->textToolError($toolName, 500, 'section_write_failed', [
            'KIND: section',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            'DETAIL: The section write did not complete cleanly: ' . $exception->getMessage(),
            'RETRY_SAFETY: The source may already have been saved. Do not retry blindly.',
            'NEXT STEP',
            'Call read_part kind=section section_id=' . $sectionId . ' include=source first. If the requested change is present, do not retry. If it is absent, resolve the server error before retrying.',
        ]);
    }

    private function staleSourceToolError(
        string $toolName,
        StaleSourceGenerationException $exception,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 409, 'stale_source_generation', [
            'SCOPE: ' . $exception->scope(),
            'DETAIL: Page Builder source changed while this write was running.',
            'NEXT STEP',
            'Call read_page_context or read_part again, then reapply the change to the current source.',
        ]);
    }

    /** @param list<string> $lines */
    private function textToolError(
        string $toolName,
        int $status,
        string $code,
        array $lines,
    ): \WP_REST_Response {
        return AgentTextResponse::withStatus(\implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }

    private function pagePreviewUrl(int $pageId, string $fallback = ''): string
    {
        $previewUrl = $this->workingPageDetails($pageId)?->previewUrl();

        return \is_string($previewUrl) && $previewUrl !== '' ? $previewUrl : $fallback;
    }

    private function workingPageDetails(int $pageId): ?PageDetails
    {
        return $this->pageDetails?->find($pageId);
    }
}
