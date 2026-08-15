<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Application\Export\StaticPageExportBuilderInterface;
use UncannyPageBuilder\Application\Controls\PageDetailsProjectionInterface;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationSnapshot;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Exception\DeactivationFallbackCompilationFailed;
use UncannyPageBuilder\Domain\Export\StaticExportArtifact;
use UncannyPageBuilder\Domain\Export\StaticPageExport;
use UncannyPageBuilder\Domain\Export\StaticExportPurpose;
use UncannyPageBuilder\Domain\Publishing\PageArtifactCandidate;
use UncannyPageBuilder\Domain\Publishing\PageDeactivationFallback;
use UncannyPageBuilder\Domain\Publishing\PagePublicationState;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshot;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelectionRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Builds publication-ready output from one coherent working-source snapshot.
 *
 * This use case is intentionally read-only. It neither inserts an artifact nor
 * changes WordPress fields, the public pointer, or editor-derived metadata.
 */
final class BuildPageArtifact implements PageArtifactBuilderInterface
{
    private const PAGE_CSS_PATH = 'assets/page.css';

    public function __construct(
        private readonly StaticPageExportBuilderInterface $exports,
        private readonly PageStateRepositoryInterface $states,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
        private readonly ShellModeService $shellModes,
        private readonly PageDetailsProjectionInterface $pageDetails,
        private readonly ?SectionService $sections = null,
        private readonly ?DesignStandardsService $designStandards = null,
        private readonly ?PageJavaScriptRuntimeService $javaScript = null,
        private readonly ?PageGlobalPartSelectionRepositoryInterface $globalPartSelections = null,
        private readonly ?CapturePageSourceSnapshot $sourceCapture = null,
    ) {}

    public function buildForPage(
        int $pageId,
        int $createdBy,
        ?int $expectedPageGeneration = null,
    ): PageArtifactCandidate {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be positive.');
        }
        if ($createdBy <= 0) {
            throw new \InvalidArgumentException('Published artifacts require a human creator.');
        }

        $lastSnapshot = null;
        $capturedPageGeneration = 0;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $capturedPageGeneration = $this->sourceGenerations->pageGeneration($pageId);
            if (
                $expectedPageGeneration !== null
                && $capturedPageGeneration !== $expectedPageGeneration
            ) {
                throw new StaleSourceGenerationException(
                    'page',
                    $expectedPageGeneration,
                    $capturedPageGeneration,
                );
            }
            $state = $this->states->findForPage($pageId);

            if (!$state instanceof PagePublicationState) {
                throw new \RuntimeException('Page publication state must be initialized before an artifact is built.');
            }

            /*
             * Title and slug live outside the section export. Matching the
             * generation on both sides proves those details belong to the same
             * working snapshot that the exporter is about to capture.
             */
            if ($this->sourceGenerations->pageGeneration($pageId) !== $capturedPageGeneration) {
                continue;
            }

            $details = $this->pageDetails->projectDraft(
                $pageId,
                $state->draftTitle(),
                $state->draftSlug(),
            );
            if ($details === null || $details->pageId() !== $pageId || trim($details->permalink()) === '') {
                throw new \RuntimeException('The draft page identity could not be projected for publication.');
            }

            try {
                $export = $this->exports->buildForPage(
                    $pageId,
                    $details->title(),
                    $details->permalink(),
                    StaticExportPurpose::Publication,
                );
            } catch (DeactivationFallbackCompilationFailed $failure) {
                throw PagePublicationFailed::staticSafetyFailed(
                    [[
                        'status' => 'failed',
                        'code' => 'deactivation_fallback_compile_failed',
                        'source' => 'deactivation_fallback',
                        'message' => 'Dynamic content could not be safely omitted from the deactivation fallback.',
                    ]],
                    'The deactivation fallback could not safely omit dynamic content.',
                );
            }
            $lastSnapshot = SourceGenerationSnapshot::fromDependencies($export->dependencies());
            if (!$lastSnapshot instanceof SourceGenerationSnapshot || $lastSnapshot->pageId() !== $pageId) {
                throw new \RuntimeException('Page artifact output is missing its source generation snapshot.');
            }

            $shellContext = $this->shellModes->resolveForPage($pageId);
            if (
                $lastSnapshot->pageGeneration() !== $capturedPageGeneration
                || $this->sourceGenerations->pageGeneration($pageId) !== $lastSnapshot->pageGeneration()
                || $this->sourceGenerations->globalGeneration() !== $lastSnapshot->globalGeneration()
            ) {
                continue;
            }

            $candidate = $this->candidate(
                $export,
                $state,
                $shellContext->mode,
                $shellContext->isExplicit,
                $lastSnapshot->pageGeneration(),
                $createdBy,
            );
            if (
                $this->sourceGenerations->pageGeneration($pageId) !== $lastSnapshot->pageGeneration()
                || $this->sourceGenerations->globalGeneration() !== $lastSnapshot->globalGeneration()
            ) {
                continue;
            }

            return $candidate;
        }

        $currentPageGeneration = $this->sourceGenerations->pageGeneration($pageId);
        if ($currentPageGeneration !== $capturedPageGeneration) {
            throw new StaleSourceGenerationException(
                'page',
                $capturedPageGeneration,
                $currentPageGeneration,
            );
        }

        throw new StaleSourceGenerationException(
            'global',
            $lastSnapshot?->globalGeneration() ?? 0,
            $this->sourceGenerations->globalGeneration(),
        );
    }

    // Section: Candidate assembly

    private function candidate(
        StaticPageExport $export,
        PagePublicationState $state,
        ShellMode $shellMode,
        bool $shellModeExplicit,
        int $pageGeneration,
        int $createdBy,
    ): PageArtifactCandidate {
        $pageSections = $export->dependencies()['sections'] ?? null;
        $pageSectionCount = is_array($pageSections)
            ? count(array_filter($pageSections, 'is_array'))
            : 0;

        $safety = $export->staticRenderingReport();
        if (!$safety->isSafe()) {
            throw PagePublicationFailed::staticSafetyFailed(
                $safety->records(),
                $safety->message(),
            );
        }

        $entry = $this->requiredArtifact($export, $export->entryPath());
        $pageCss = $this->requiredArtifact($export, self::PAGE_CSS_PATH);
        $assetsManifest = $this->assetsManifest($export);
        $deactivationFallback = $export->deactivationFallback();
        if (!$deactivationFallback instanceof PageDeactivationFallback) {
            throw PagePublicationFailed::staticSafetyFailed(
                [[
                    'status' => 'failed',
                    'code' => 'deactivation_fallback_missing',
                    'source' => 'deactivation_fallback',
                    'message' => 'The publication export did not include a deactivation fallback.',
                ]],
                'The publication export did not include a deactivation fallback.',
            );
        }
        $this->assertFallbackMatchesExport(
            $deactivationFallback,
            $pageCss->content(),
            $export->customJavaScript(),
            $assetsManifest,
        );
        $dependencies = array_merge($export->dependencies(), [
            'shell_mode' => $shellMode->value,
            PageDeactivationFallback::DEPENDENCY_HASH_KEY => $deactivationFallback->contentHash(),
        ]);

        $sourceRevisionHash = $this->sourceRevisionHash($export, $state, $shellMode);

        return new PageArtifactCandidate(
            pageId: $export->pageId(),
            sourceRevisionHash: $sourceRevisionHash,
            pageSectionCount: $pageSectionCount,
            title: $state->draftTitle(),
            slug: $state->draftSlug(),
            shellMode: $shellMode,
            html: $this->pageHtml($entry->content()),
            css: $pageCss->content(),
            customJavaScript: $export->customJavaScript(),
            assetsManifest: $assetsManifest,
            dependencies: $dependencies,
            staticSafetyReport: $safety->records(),
            createdBy: $createdBy,
            deactivationFallback: $deactivationFallback,
            sourceSnapshot: $this->sourceSnapshot(
                pageId: $export->pageId(),
                sourceRevisionHash: $sourceRevisionHash,
                pageGeneration: $pageGeneration,
                state: $state,
                shellMode: $shellMode,
                shellModeExplicit: $shellModeExplicit,
                createdBy: $createdBy,
            ),
        );
    }

    private function sourceSnapshot(
        int $pageId,
        string $sourceRevisionHash,
        int $pageGeneration,
        PagePublicationState $state,
        ShellMode $shellMode,
        bool $shellModeExplicit,
        int $createdBy,
    ): ?PageSourceSnapshot {
        if ($this->sourceCapture instanceof CapturePageSourceSnapshot) {
            return $this->sourceCapture->capture(
                pageId: $pageId,
                sourceRevisionHash: $sourceRevisionHash,
                pageGeneration: $pageGeneration,
                state: $state,
                createdBy: $createdBy,
                shellMode: $shellMode,
                shellModeExplicit: $shellModeExplicit,
            );
        }

        if (
            !$this->sections instanceof SectionService
            || !$this->designStandards instanceof DesignStandardsService
            || !$this->javaScript instanceof PageJavaScriptRuntimeService
            || !$this->globalPartSelections instanceof PageGlobalPartSelectionRepositoryInterface
        ) {
            return null;
        }

        $layout = $this->sections->getLayout($pageId);
        $selection = $this->globalPartSelections->loadForPage($pageId);

        return PageSourceSnapshot::create(
            pageId: $pageId,
            sourceRevisionHash: $sourceRevisionHash,
            pageGeneration: $pageGeneration,
            source: [
                'sections' => is_array($layout['sections'] ?? null) ? $layout['sections'] : [],
                'page_design_overrides' => $this->designStandards->loadPageOverrides($pageId)->toArray(),
                'custom_javascript' => $this->javaScript->readForPage($pageId),
                'title' => $state->draftTitle(),
                'slug' => $state->draftSlug(),
                'shell_mode' => $shellMode->value,
                'shell_mode_explicit' => $shellModeExplicit,
                'header_override_id' => $selection->headerOverrideId(),
                'footer_override_id' => $selection->footerOverrideId(),
            ],
            createdBy: $createdBy,
        );
    }

    // Section: Compiled output extraction

    private function requiredArtifact(StaticPageExport $export, string $path): StaticExportArtifact
    {
        $artifact = $export->artifact($path);
        if (!$artifact instanceof StaticExportArtifact) {
            throw new \RuntimeException(sprintf('Page artifact output is missing: %s', $path));
        }

        return $artifact;
    }

    private function pageHtml(string $document): string
    {
        $html = $document;
        if (preg_match('#<body\b[^>]*>(.*?)</body>#is', $document, $matches) === 1) {
            $html = (string) $matches[1];
        }

        /*
         * Runtime libraries and validated custom JavaScript have dedicated
         * artifact fields. Keeping them out of page HTML prevents a future
         * pointer renderer from executing the same script twice.
         */
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<link\b[^>]*>#i', '', $html) ?? $html;

        return trim($html);
    }

    // Section: Runtime manifest and source identity

    /** @return array<string, mixed> */
    private function assetsManifest(StaticPageExport $export): array
    {
        $dependencies = $export->dependencies();
        $runtime = $dependencies['public_runtime_manifest'] ?? null;
        $assets = is_array($runtime) ? ($runtime['assets'] ?? null) : null;

        if (!is_array($assets) || $assets === [] || array_is_list($assets)) {
            throw new \RuntimeException('Page artifact output is missing its public runtime manifest.');
        }

        $fonts = $dependencies['font_assets'] ?? [];
        if (!is_array($fonts) || ($fonts !== [] && array_is_list($fonts))) {
            throw new \RuntimeException('Page artifact output contains an invalid font manifest.');
        }

        return [
            'assets' => $assets,
            'fonts' => [
                'google' => is_array($fonts['google'] ?? null) ? array_values($fonts['google']) : [],
                'custom' => is_array($fonts['custom'] ?? null) ? array_values($fonts['custom']) : [],
            ],
        ];
    }

    /** @param array<string, mixed> $assetsManifest */
    private function assertFallbackMatchesExport(
        PageDeactivationFallback $fallback,
        string $css,
        string $customJavaScript,
        array $assetsManifest,
    ): void {
        if ($fallback->css() !== $css) {
            $this->fallbackContractFailed('CSS');
        }
        if ($fallback->customJavaScript() !== $customJavaScript) {
            $this->fallbackContractFailed('JavaScript');
        }
        if ($fallback->assetsManifest() !== $assetsManifest) {
            $this->fallbackContractFailed('asset manifest');
        }
    }

    private function fallbackContractFailed(string $field): never
    {
        throw PagePublicationFailed::staticSafetyFailed(
            [[
                'status' => 'failed',
                'code' => 'deactivation_fallback_contract_mismatch',
                'source' => 'deactivation_fallback',
                'field' => $field,
                'message' => 'The deactivation fallback does not match the coherent publication export.',
            ]],
            'The deactivation fallback does not match the publication output.',
        );
    }

    private function sourceRevisionHash(
        StaticPageExport $export,
        PagePublicationState $state,
        ShellMode $shellMode,
    ): string {
        $artifacts = [];

        foreach ($export->artifacts() as $artifact) {
            $artifacts[$artifact->path()] = [
                'mime_type' => $artifact->mimeType(),
                'sha256' => hash('sha256', $artifact->content()),
            ];
        }
        ksort($artifacts);

        $payload = [
            'page_id' => $export->pageId(),
            'title' => $state->draftTitle(),
            'slug' => $state->draftSlug(),
            'shell_mode' => $shellMode->value,
            'artifacts' => $artifacts,
            PageDeactivationFallback::DEPENDENCY_HASH_KEY => $export->deactivationFallback()?->contentHash(),
        ];

        return hash('sha256', self::encodeJson(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private static function encodeJson(mixed $value, int $flags = 0): string|false
    {
        // Exact JSON bytes are part of the artifact hash; wp_json_encode() may repair invalid UTF-8 and change the digest.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This deterministic language operation is not an external capability.
        return json_encode($value, $flags);
    }
}
