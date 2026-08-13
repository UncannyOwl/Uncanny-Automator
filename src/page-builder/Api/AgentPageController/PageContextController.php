<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\Controls\PageDetails;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartResolverInterface;
use UncannyPageBuilder\Domain\Section\ComponentCategory;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Domain\Shell\ShellModeContext;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Infrastructure\Section\ComponentCategoryClassifier;
use UncannyPageBuilder\Infrastructure\Section\DomSectionManifestExtractor;

/**
 * Reads Agent-facing page identity, structure, shell, and reusable context.
 */
final class PageContextController
{
    public function __construct(
        private readonly SectionService $sectionService,
        private readonly DatabaseSectionRepository $sections,
        private readonly PermissionChecker $permissions,
        private readonly GlobalPartDefaultsService $globalPartDefaults,
        private readonly ComponentCategoryClassifier $categoryClassifier,
        private readonly DomSectionManifestExtractor $manifestExtractor,
        private readonly GlobalPartService $globalParts,
        private readonly ?ShellModeService $shellModes = null,
        private readonly ?PageGlobalPartResolverInterface $pagePartResolver = null,
        private readonly ?PageDetailsPortInterface $pageDetails = null,
    ) {}

    public function readContext(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            return $this->readContextText($request, 'read_page_context');
        } catch (\Throwable $failure) {
            error_log(sprintf('[Uncanny Page Builder] read_page_context failed (%s).', $failure::class));

            return $this->textToolError('read_page_context', 500, 'read_failed', [
                'NEXT STEP',
                'Retry read_page_context. If the error continues, review the WordPress error log.',
            ]);
        }
    }

    private function readContextText(
        \WP_REST_Request $request,
        string $toolName,
    ): \WP_REST_Response|\WP_Error {
        $pageId = RequestId::fromUrl($request, 'page_id');
        if ($pageId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        $globalPartId = $this->contextGlobalPartId($request, $pageId);
        if ($globalPartId > 0) {
            return $this->readGlobalPartContext($toolName, $globalPartId);
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }
        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        $details = $this->workingPageDetails($pageId);
        $lines = [
            'TOOL: ' . $toolName,
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'PAGE_TITLE: ' . ($details?->title() ?? (\get_the_title($pageId) ?: '')),
        ];
        if ($details instanceof PageDetails) {
            $lines[] = 'PAGE_SLUG: ' . $details->slug();
            $lines[] = 'DRAFT_URL_PREVIEW: ' . $details->permalink();
            $lines[] = 'DRAFT_PREVIEW_URL: ' . $details->previewUrl();
            $lines[] = 'DRAFT_URL_IS_LIVE: ' . ($details->permalinkIsLive() ? 'yes' : 'no');
        }

        // Shell ownership determines which header/footer changes can render.
        $shellContext = $this->shellModes?->resolveForPage($pageId);
        if ($shellContext !== null) {
            $lines[] = 'PAGE_MODE: ' . $shellContext->mode->value . ' — ' . match ($shellContext->mode) {
                ShellMode::UncannyNative => $this->describeUncannyNativeShell($shellContext),
                ShellMode::ThemeComposition => 'the WordPress theme owns the wrapper, header, and footer; sections render inside the theme content area.',
                ShellMode::None => 'no mode chosen yet; the page renders as a standalone Uncanny canvas with no header or footer (neither theme nor Uncanny) until a mode is selected.',
            };
        }

        $lines[] = '';
        $lines[] = 'SECTIONS';

        $sections = $this->sections->findByPageId($pageId)->all();
        if ($sections === []) {
            $lines[] = 'none';
        }

        foreach ($sections as $index => $section) {
            $lines[] = ((int) $index + 1) . '. SECTION_ID: ' . (string) $section->id();
            $lines[] = '   NAME: ' . $section->name();
            $lines[] = '   POSITION: ' . (string) $section->position();
            $lines[] = '   CATEGORY: ' . $this->classifySection($section)->value;
            $lines[] = '   NEXT: read_part kind=section include=manifest,source,design_targets';
            $lines[] = '';
        }

        if ($this->wantsGlobalParts($request)) {
            $lines[] = 'GLOBAL PARTS';
            foreach (['header', 'footer'] as $type) {
                $part = $this->resolvePagePart($pageId, GlobalPartType::from($type));
                $label = \strtoupper($type);
                $lines[] = $label . ': ' . ($part !== null
                    ? 'post ' . (int) $part['post_id'] . ', ' . (string) ((($part['title'] ?? '') !== '') ? $part['title'] : $type)
                    : 'none');
            }
            $lines[] = '';
        }

        $lines[] = 'NEXT STEP';
        $lines[] = $sections === []
            ? 'Create the first section for this page.'
            : "Read the section that contains the user's requested target.";

        return AgentTextResponse::ok(\implode("\n", $lines));
    }

    /**
     * Native mode renders only shell slots resolved for the current page.
     */
    private function describeUncannyNativeShell(ShellModeContext $context): string
    {
        if ($context->hasUncannyHeader && $context->hasUncannyFooter) {
            return 'Uncanny Page Builder owns the full page including its global header/footer.';
        }

        $missing = \array_keys(\array_filter([
            'header' => !$context->hasUncannyHeader,
            'footer' => !$context->hasUncannyFooter,
        ]));

        return 'Uncanny Page Builder owns the page, but no global '
            . \implode(' or ', $missing) . ' applies to this page; '
            . (\count($missing) === 2 ? 'those slots render' : 'that slot renders')
            . ' nothing until a part is assigned.';
    }

    /**
     * Resolve the same shell slot that the page renderer will display.
     *
     * @return array<string, mixed>|null
     */
    private function resolvePagePart(int $pageId, GlobalPartType $type): ?array
    {
        $mode = $this->shellModes?->resolveForPage($pageId)->mode;
        if ($mode !== null && $mode !== ShellMode::UncannyNative) {
            return null;
        }

        if ($this->pagePartResolver !== null) {
            return $this->pagePartResolver->resolveForPage($pageId, $type);
        }

        return $this->globalPartDefaults->resolveForType($type);
    }

    private function readGlobalPartContext(
        string $toolName,
        int $globalPartId,
    ): \WP_REST_Response|\WP_Error {
        if (!$this->permissions->canEditPost($globalPartId)) {
            return ApiResponse::error(ErrorMessage::GlobalPartEditForbidden);
        }

        $resolved = $this->globalParts->findById($globalPartId);
        if ($resolved === null) {
            return $this->textToolError($toolName, 404, 'no_active_global_part', [
                'KIND: global_part',
                'POST_ID: ' . $globalPartId,
                'NEXT STEP',
                'Open an existing reusable canvas before reading reusable context.',
            ]);
        }

        $section = $this->globalPartSourceSection($resolved);
        if (!$section instanceof Section) {
            return $this->textToolError($toolName, 404, 'no_global_part_source', [
                'KIND: global_part',
                'POST_ID: ' . $globalPartId,
                'NEXT STEP',
                'Create source content for this reusable before reading reusable context.',
            ]);
        }

        return AgentTextResponse::ok(\implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: success',
            'KIND: global_part',
            'POST_ID: ' . $globalPartId,
            'TITLE: ' . (string) ($resolved['title'] ?? ''),
            'PART_TYPE: ' . $this->resolvedGlobalPartType($resolved, ''),
            '',
            'SECTIONS',
            '1. SOURCE_SECTION_NAME: ' . $section->name(),
            '   POSITION: ' . (string) $section->position(),
            '   NEXT: read_part kind=global_part include=manifest,source,design_targets',
            '',
            'NEXT STEP',
            'Read the reusable source that contains the requested target.',
        ]));
    }

    /**
     * @param array<string, mixed> $globalPart
     */
    private function globalPartSourceSection(array $globalPart): ?Section
    {
        $sections = $globalPart['sections'] ?? [];
        if (!\is_array($sections) || $sections === []) {
            return null;
        }

        $sectionData = $sections[0] ?? null;
        if (!\is_array($sectionData)) {
            return null;
        }

        if (!isset($sectionData['content']) && (isset($sectionData['html']) || isset($sectionData['css']))) {
            $sectionData['content'] = [
                'html' => (string) ($sectionData['html'] ?? ''),
                'css' => (string) ($sectionData['css'] ?? ''),
            ];
        }

        return Section::fromStoredArray(
            $sectionData,
            (int) ($globalPart['post_id'] ?? 0),
            (int) ($sectionData['position'] ?? 0),
        );
    }

    private function classifySection(Section $section): ComponentCategory
    {
        try {
            $manifest = $this->manifestExtractor->extract($section);

            return $this->categoryClassifier->classifyWithManifest($section->name(), $manifest);
        } catch (\Throwable) {
            $nameLower = \strtolower(\trim($section->name()));
            foreach (ComponentCategory::cases() as $case) {
                if ($case !== ComponentCategory::Generic && \str_contains($nameLower, $case->value)) {
                    return $case;
                }
            }

            return ComponentCategory::Generic;
        }
    }

    private function wantsGlobalParts(\WP_REST_Request $request): bool
    {
        $include = $request->get_param('include');

        return $include !== null && \str_contains((string) $include, 'global_parts');
    }

    private function contextGlobalPartId(\WP_REST_Request $request, int $pageId): int
    {
        $requestId = $this->requestGlobalPartId($request, $pageId <= 0);
        if ($requestId > 0) {
            return $requestId;
        }

        if ($pageId > 0) {
            return $this->isPublishedGlobalPartPostId($pageId) ? $pageId : 0;
        }

        return 0;
    }

    private function requestGlobalPartId(\WP_REST_Request $request, bool $allowContextFallback): int
    {
        $requestId = \absint($request->get_param('global_part_id'));
        if ($requestId > 0) {
            return $requestId;
        }

        if (!$allowContextFallback) {
            return 0;
        }

        $context = $request->get_param('page_builder_context');
        if (\is_array($context)) {
            return \absint($context['global_part_id'] ?? 0);
        }

        return 0;
    }

    private function isPublishedGlobalPartPostId(int $postId): bool
    {
        return $postId > 0
            && \get_post_type($postId) === 'upb_global_part'
            && \get_post_status($postId) === 'publish';
    }

    /**
     * @param array<string, mixed> $resolved
     */
    private function resolvedGlobalPartType(array $resolved, string $fallback): string
    {
        $resolvedType = \trim((string) ($resolved['type'] ?? ''));

        return $resolvedType !== '' ? $resolvedType : $fallback;
    }

    /** @param list<string> $lines */
    private function textToolError(string $toolName, int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(\implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }

    private function workingPageDetails(int $pageId): ?PageDetails
    {
        return $this->pageDetails?->find($pageId);
    }
}
