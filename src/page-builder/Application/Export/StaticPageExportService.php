<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Export;

use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\Rendering\LucideRuntimeInitializer;
use UncannyPageBuilder\Application\Rendering\PublicRuntimeAssetCatalog;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationSnapshot;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Canvas\AlpineVisibilityGuard;
use UncannyPageBuilder\Domain\Canvas\CanvasResetCss;
use UncannyPageBuilder\Domain\DesignStandards\DesignTokenCssRenderer;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Export\StaticExportArtifact;
use UncannyPageBuilder\Domain\Export\StaticExportPurpose;
use UncannyPageBuilder\Domain\Export\StaticExportContextProviderInterface;
use UncannyPageBuilder\Domain\Export\StaticExportAssetSourceInterface;
use UncannyPageBuilder\Domain\Export\StaticExportGlobalPartResolverInterface;
use UncannyPageBuilder\Domain\Export\StaticExportHtmlCleaner;
use UncannyPageBuilder\Domain\Export\StaticExportHtmlRendererInterface;
use UncannyPageBuilder\Domain\Export\StaticExportPageIdentity;
use UncannyPageBuilder\Domain\Export\StaticPageExport;
use UncannyPageBuilder\Domain\Export\StaticRenderingPolicy;
use UncannyPageBuilder\Domain\Export\StaticRenderingReport;
use UncannyPageBuilder\Domain\Export\StaticRenderingResult;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Builds portable static page artifacts from Page Builder-owned source.
 *
 * The export ships the runtime pieces the rendered HTML needs: Bootstrap,
 * Alpine, Lucide, resolved design-token CSS, compiled section CSS, and final
 * section HTML. It does not mutate WordPress content or theme files.
 */
final class StaticPageExportService implements StaticPageExportBuilderInterface
{
    public function __construct(
        private readonly SectionRepositoryInterface $sections,
        private readonly ShadowCompiler $compiler,
        private readonly DesignStandardsService $designStandards,
        private readonly StaticExportHtmlRendererInterface $htmlRenderer,
        private readonly StaticExportAssetSourceInterface $assetSource,
        private readonly StaticExportGlobalPartResolverInterface $globalParts,
        private readonly ?StaticRenderingPolicy $staticRenderingPolicy = null,
        private readonly ?StaticExportContextProviderInterface $contextProvider = null,
        private readonly ?PageJavaScriptExportRendererInterface $javaScriptRuntimeRenderer = null,
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
    ) {}

    public function buildForPage(
        int $pageId,
        ?string $documentTitle = null,
        ?string $documentPermalink = null,
        StaticExportPurpose $purpose = StaticExportPurpose::Portable,
    ): StaticPageExport {
        if (!$this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            return $this->buildCurrentPage($pageId, null, $documentTitle, $documentPermalink, $purpose);
        }

        $lastSnapshot = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $globalGeneration = $this->sourceGenerations->globalGeneration();
            $export = $this->buildCurrentPage(
                $pageId,
                $globalGeneration,
                $documentTitle,
                $documentPermalink,
                $purpose,
            );
            $lastSnapshot = SourceGenerationSnapshot::fromDependencies($export->dependencies());

            if (
                $lastSnapshot instanceof SourceGenerationSnapshot
                && $this->sourceGenerations->pageGeneration($pageId) === $lastSnapshot->pageGeneration()
                && $this->sourceGenerations->globalGeneration() === $lastSnapshot->globalGeneration()
            ) {
                return $export;
            }
        }

        $currentPageGeneration = $this->sourceGenerations->pageGeneration($pageId);
        if (
            $lastSnapshot instanceof SourceGenerationSnapshot
            && $currentPageGeneration !== $lastSnapshot->pageGeneration()
        ) {
            throw new StaleSourceGenerationException(
                'page',
                $lastSnapshot->pageGeneration(),
                $currentPageGeneration,
            );
        }

        throw new StaleSourceGenerationException(
            'global',
            $lastSnapshot?->globalGeneration() ?? 0,
            $this->sourceGenerations->globalGeneration(),
        );
    }

    private function buildCurrentPage(
        int $pageId,
        ?int $globalGeneration,
        ?string $documentTitle,
        ?string $documentPermalink,
        StaticExportPurpose $purpose,
    ): StaticPageExport {
        if ($pageId <= 0 || !$this->sections->pageExists($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        $collection = $this->sections->findByPageId($pageId);
        $compiled = $this->compiler->compile($collection);
        $profile = $this->designStandards->resolveForPage($pageId);
        $header = $this->globalParts->resolveForPage($pageId, GlobalPartType::Header);
        $footer = $this->globalParts->resolveForPage($pageId, GlobalPartType::Footer);
        $report = new StaticRenderingReport();
        $pageIdentity = $this->pageIdentity($pageId, $documentTitle, $documentPermalink);

        [$headerHtml, $headerReport] = $this->renderGlobalPart($header, $pageId, 'header', $pageIdentity, $purpose);
        [$sectionsHtml, $sectionsReport] = $this->renderSections(
            $collection->toArray(),
            $pageId,
            'page',
            $pageIdentity,
            $purpose,
        );
        [$footerHtml, $footerReport] = $this->renderGlobalPart($footer, $pageId, 'footer', $pageIdentity, $purpose);

        $report = $report->merge($headerReport)->merge($sectionsReport)->merge($footerReport);
        $bodyHtml = $headerHtml . $sectionsHtml . $footerHtml;

        $pageCss = DesignTokenCssRenderer::renderProfile($profile)
            . $this->canvasResetCss()
            . AlpineVisibilityGuard::cloakCss()
            . $this->globalPartCss($header)
            . $this->globalPartCss($footer)
            . $compiled->minifiedCss();

        $customJavaScript = $this->javaScriptRuntimeRenderer instanceof PageJavaScriptExportRendererInterface
            ? $this->javaScriptRuntimeRenderer->renderExportScripts($pageId, $header, $footer)
            : '';

        $artifacts = [
            new StaticExportArtifact(
                'index.html',
                'text/html; charset=utf-8',
                $this->renderDocument($pageId, $bodyHtml, $customJavaScript, $documentTitle),
            ),
            new StaticExportArtifact('assets/page.css', 'text/css; charset=utf-8', $pageCss),
        ];

        foreach (PublicRuntimeAssetCatalog::required() as $asset) {
            $artifacts[] = $this->pluginAsset(
                $asset['reference'],
                $asset['export_source_path'],
                $asset['mime_type'],
            );
        }

        foreach ($this->runtimeLibraryArtifacts($customJavaScript) as $artifact) {
            $artifacts[] = $artifact;
        }

        return new StaticPageExport(
            pageId: $pageId,
            entryPath: 'index.html',
            artifacts: $artifacts,
            staticRenderingReport: $report,
            dependencies: $this->dependencies(
                $pageId,
                $collection->toArray(),
                $profile->toArray(),
                $header,
                $footer,
                $artifacts,
                $customJavaScript,
                $globalGeneration !== null
                    ? new SourceGenerationSnapshot($pageId, $collection->generation(), $globalGeneration)
                    : null,
            ),
            customJavaScript: $customJavaScript,
        );
    }

    private function pluginAsset(string $exportPath, string $pluginPath, string $mimeType): StaticExportArtifact
    {
        return new StaticExportArtifact(
            $exportPath,
            $mimeType,
            $this->assetSource->read($pluginPath),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     */
    /**
     * @return array{0: string, 1: StaticRenderingReport}
     */
    private function renderSections(
        array $sections,
        int $pageId,
        string $source,
        ?StaticExportPageIdentity $pageIdentity,
        StaticExportPurpose $purpose,
    ): array {
        $html = '';
        $report = new StaticRenderingReport();

        foreach ($sections as $section) {
            $prepared = $this->prepareSection($section, $source, $purpose);
            $section['content']['html'] = $prepared->html();
            $report = $report->merge($prepared->report());

            $html .= StaticExportHtmlCleaner::clean(
                $this->htmlRenderer->renderSection($section, $pageId, $pageIdentity),
            );
        }

        return [$html, $report];
    }

    /**
     * @param array<string, mixed>|null $part
     */
    /**
     * @return array{0: string, 1: StaticRenderingReport}
     */
    private function renderGlobalPart(
        ?array $part,
        int $pageId,
        string $source,
        ?StaticExportPageIdentity $pageIdentity,
        StaticExportPurpose $purpose,
    ): array {
        $sections = $part['sections'] ?? null;
        if (!is_array($sections)) {
            return ['', new StaticRenderingReport()];
        }

        return $this->renderSections(
            array_filter($sections, 'is_array'),
            $pageId,
            $source,
            $pageIdentity,
            $purpose,
        );
    }

    private function pageIdentity(
        int $pageId,
        ?string $documentTitle,
        ?string $documentPermalink,
    ): ?StaticExportPageIdentity {
        if (trim((string) $documentTitle) === '' || trim((string) $documentPermalink) === '') {
            return null;
        }

        return new StaticExportPageIdentity(
            $pageId,
            (string) $documentTitle,
            (string) $documentPermalink,
        );
    }

    /**
     * @param array<string, mixed>|null $part
     */
    private function globalPartCss(?array $part): string
    {
        $css = is_array($part) ? (string) ($part['css'] ?? '') : '';
        if ($css === '') {
            return '';
        }

        return ShadowCompiler::scopeCssToCanvas(ShadowCompiler::repairCss($css));
    }

    private function renderDocument(
        int $pageId,
        string $bodyHtml,
        string $customJavaScript = '',
        ?string $documentTitle = null,
    ): string {
        $title = trim((string) $documentTitle);
        if ($title === '') {
            $title = 'Page ' . $pageId;
        }

        return '<!doctype html>' . "\n"
            . '<html lang="en">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>' . self::escapeText($title) . '</title>' . "\n"
            . '<link rel="stylesheet" href="assets/bootstrap.min.css">' . "\n"
            . '<link rel="stylesheet" href="assets/bootstrap-extended-spacing.css">' . "\n"
            . '<link rel="stylesheet" href="assets/page.css">' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '<div id="uncanny-pb-canvas-root"><div id="uncanny-pb-canvas">' . $bodyHtml . '</div></div>' . "\n"
            . '<script src="assets/lucide.min.js"></script>' . "\n"
            . '<script>' . LucideRuntimeInitializer::script() . '</script>' . "\n"
            . '<script defer src="assets/alpine.min.js"></script>' . "\n"
            . ($customJavaScript !== '' ? $customJavaScript . "\n" : '')
            . '</body>' . "\n"
            . '</html>';
    }

    /**
     * @return StaticExportArtifact[]
     */
    private function runtimeLibraryArtifacts(string $customJavaScript): array
    {
        if (
            $customJavaScript === ''
            || !$this->javaScriptRuntimeRenderer instanceof PageJavaScriptExportRendererInterface
        ) {
            return [];
        }

        $artifacts = [];

        foreach ($this->javaScriptRuntimeRenderer->approvedLibraryAssets($customJavaScript) as $asset) {
            $artifacts[] = $this->pluginAsset(
                $asset['export_path'],
                $asset['plugin_path'],
                $asset['mime_type'],
            );
        }

        return $artifacts;
    }

    private static function escapeText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function prepareSection(
        array $section,
        string $source,
        StaticExportPurpose $purpose,
    ): StaticRenderingResult {
        $html = (string) ($section['content']['html'] ?? '');

        return $this->policy()->prepareHtml($html, $source, $purpose);
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed> $profile
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     * @param StaticExportArtifact[] $artifacts
     * @return array<string, mixed>
     */
    private function dependencies(
        int $pageId,
        array $sections,
        array $profile,
        ?array $header,
        ?array $footer,
        array $artifacts,
        string $customJavaScript,
        ?SourceGenerationSnapshot $sourceGenerations = null,
    ): array {
        $dependencies = [
            'sections' => $this->sectionDependencies($sections),
            'global_parts' => array_values(array_filter([
                $this->globalPartDependency($header, 'header'),
                $this->globalPartDependency($footer, 'footer'),
            ])),
            'design_profile_hash' => $this->hashData($profile),
            'page_design_overrides_hash' => $this->hashData($this->designStandards->loadPageOverrides($pageId)->toArray()),
            'public_runtime_manifest' => $this->publicRuntimeManifest($customJavaScript),
            'asset_manifest' => array_map(
                static fn(StaticExportArtifact $artifact): array => [
                    'path' => $artifact->path(),
                    'mime_type' => $artifact->mimeType(),
                    'size' => strlen($artifact->content()),
                    'sha256' => hash('sha256', $artifact->content()),
                ],
                $artifacts,
            ),
            'static_rendering_policy' => $this->policy()->version(),
            'binding_static_safety_hash' => $this->policy()->fingerprint(),
        ] + $this->contextDependencies($pageId, $sections, $header, $footer);

        if ($sourceGenerations instanceof SourceGenerationSnapshot) {
            $dependencies[SourceGenerationSnapshot::DEPENDENCY_KEY] = $sourceGenerations->toArray();
        }

        return $dependencies;
    }

    /**
     * Describe the plugin-owned runtime files required by this page. Runtime
     * files follow the installed plugin release; page artifacts pin content,
     * not duplicate deployment assets. Scoped Bootstrap is deliberate: a
     * theme-composition publication must never reboot-style its WordPress theme.
     *
     * @return array{assets: array<string, array{}>}
     */
    private function publicRuntimeManifest(string $customJavaScript): array
    {
        $assetNames = array_fill_keys(array_keys(PublicRuntimeAssetCatalog::required()), true);

        if ($this->javaScriptRuntimeRenderer instanceof PageJavaScriptExportRendererInterface) {
            foreach ($this->javaScriptRuntimeRenderer->approvedLibraryAssets($customJavaScript) as $asset) {
                $name = (string) ($asset['name'] ?? '');
                if (PublicRuntimeAssetCatalog::get($name) !== null) {
                    $assetNames[$name] = true;
                }
            }
        }

        $assets = array_fill_keys(array_keys($assetNames), []);

        ksort($assets);

        return [
            'assets' => $assets,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array{id: int, fingerprint: string, position: int}>
     */
    private function sectionDependencies(array $sections): array
    {
        $dependencies = [];
        foreach ($sections as $position => $section) {
            if (!is_array($section)) {
                continue;
            }

            $dependencies[] = [
                'id' => (int) ($section['id'] ?? 0),
                'fingerprint' => $this->hashData([
                    'name'    => (string) ($section['name'] ?? ''),
                    'content' => $section['content'] ?? [],
                ]),
                'position' => (int) ($section['position'] ?? $position),
            ];
        }

        return $dependencies;
    }

    /**
     * @param array<string, mixed>|null $part
     * @return array{id: int, fingerprint: string, role: string, sections: array<int, array{id: int, fingerprint: string, position: int}>}|null
     */
    private function globalPartDependency(?array $part, string $role): ?array
    {
        if (!is_array($part)) {
            return null;
        }

        $sections = is_array($part['sections'] ?? null)
            ? array_filter($part['sections'], 'is_array')
            : [];

        return [
            'id' => (int) ($part['post_id'] ?? 0),
            'fingerprint' => $this->hashData([
                'title' => (string) ($part['title'] ?? ''),
                'css' => (string) ($part['css'] ?? ''),
                'sections' => $this->sectionDependencies($sections),
            ]),
            'role' => $role,
            'sections' => $this->sectionDependencies($sections),
        ];
    }

    /**
     * @param mixed $data
     */
    private function hashData($data): string
    {
        $this->sortRecursively($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param mixed $value
     */
    private function sortRecursively(&$value): void
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as &$child) {
            $this->sortRecursively($child);
        }
        unset($child);

        if (!array_is_list($value)) {
            ksort($value);
        }
    }

    private function policy(): StaticRenderingPolicy
    {
        return $this->staticRenderingPolicy ?? new StaticRenderingPolicy();
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     * @return array<string, mixed>
     */
    private function contextDependencies(int $pageId, array $sections, ?array $header, ?array $footer): array
    {
        if (!$this->contextProvider instanceof StaticExportContextProviderInterface) {
            return [];
        }

        return $this->contextProvider->contextForPage($pageId, $sections, $header, $footer);
    }

    private function canvasResetCss(): string
    {
        return CanvasResetCss::render();
    }
}
