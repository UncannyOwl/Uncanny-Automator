<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\GlobalPartDefaultsResolverInterface;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartResolverInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Isolates mutable editor reads from the public artifact renderer.
 */
final class WorkingCanvasSource
{
    /** @var array<int, \UncannyPageBuilder\Application\Editor\EditorPageSourceSelection|null> */
    private array $renderSelections = [];

    public function __construct(
        private readonly SectionRepositoryInterface $sections,
        private readonly GlobalPartRepositoryInterface $globalParts,
        private readonly ?ShellModeService $shellModes = null,
        private readonly ?PageGlobalPartResolverInterface $pageParts = null,
        private readonly ?PageJavaScriptRuntimeRenderer $javaScript = null,
        private readonly ?SelectEditorPageSource $pageSources = null,
        private readonly ?ShadowCompiler $compiler = null,
        private readonly ?GlobalPartDefaultsResolverInterface $globalPartDefaults = null,
    ) {}

    /**
     * @return array{sections: array<int, array<string, mixed>>, compiled_css: string, shell_mode: ShellMode, header: array<string, mixed>|null, footer: array<string, mixed>|null}
     */
    public function read(int $pageId): array
    {
        $selection = $this->pageSources?->forPage($pageId);
        $this->renderSelections[$pageId] = $selection;
        $snapshot = $selection?->loadedSource() === 'published'
            ? $selection->publishedSnapshot()
            : null;
        if ($snapshot !== null && $this->compiler instanceof ShadowCompiler) {
            $source = $snapshot->source();
            $sections = SectionCollection::fromArray(
                is_array($source['sections'] ?? null) ? $source['sections'] : [],
                $pageId,
                $snapshot->pageGeneration(),
            );
            $shellMode = ShellMode::tryFrom((string) ($source['shell_mode'] ?? ''))
                ?? ShellMode::UncannyNative;
            $usesUncannyShell = $shellMode === ShellMode::UncannyNative;

            return [
                'sections' => $sections->toArray(),
                'compiled_css' => $this->compiler->compile($sections)->minifiedCss(),
                'shell_mode' => $shellMode,
                'header' => $usesUncannyShell
                    ? $this->snapshotPart(GlobalPartType::Header, $source['header_override_id'] ?? null)
                    : null,
                'footer' => $usesUncannyShell
                    ? $this->snapshotPart(GlobalPartType::Footer, $source['footer_override_id'] ?? null)
                    : null,
            ];
        }

        $shellMode = $this->shellModes?->resolveForPage($pageId)->mode ?? ShellMode::UncannyNative;
        $usesUncannyShell = $shellMode === ShellMode::UncannyNative;

        return [
            'sections' => $this->sections->findByPageId($pageId)->toArray(),
            'compiled_css' => (string) get_post_meta($pageId, '_uncanny_page_builder_compiled_css', true),
            'shell_mode' => $shellMode,
            'header' => $usesUncannyShell ? $this->part($pageId, GlobalPartType::Header) : null,
            'footer' => $usesUncannyShell ? $this->part($pageId, GlobalPartType::Footer) : null,
        ];
    }

    /**
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     */
    public function javaScript(int $pageId, ?array $header, ?array $footer): string
    {
        /*
         * CanvasRenderer asks for source and runtime separately. Reuse the
         * selection captured by read() so one concurrent save cannot combine
         * published sections with working JavaScript in the same response.
         */
        $selection = array_key_exists($pageId, $this->renderSelections)
            ? $this->renderSelections[$pageId]
            : $this->pageSources?->forPage($pageId);
        $snapshot = $selection?->loadedSource() === 'published'
            ? $selection->publishedSnapshot()
            : null;
        if ($snapshot instanceof \UncannyPageBuilder\Domain\Publishing\PageSourceSnapshot) {
            return $this->javaScript?->renderStandaloneCanvasScriptsFromPageSource(
                $pageId,
                (string) ($snapshot->source()['custom_javascript'] ?? ''),
                $header,
                $footer,
            ) ?? '';
        }

        return $this->javaScript?->renderStandaloneCanvasScripts($pageId, $header, $footer) ?? '';
    }

    /** @return array<string, mixed>|null */
    private function part(int $pageId, GlobalPartType $type): ?array
    {
        if ($this->pageParts instanceof PageGlobalPartResolverInterface) {
            return $this->pageParts->resolveForPage($pageId, $type);
        }

        return $this->globalParts->findByType($type);
    }

    private function snapshotPart(GlobalPartType $type, mixed $overrideId): ?array
    {
        if (is_int($overrideId) && $overrideId === -1) {
            return null;
        }
        if (is_int($overrideId) && $overrideId > 0) {
            $part = $this->globalParts->findById($overrideId);

            return is_array($part) && ($part['type'] ?? null) === $type->value ? $part : null;
        }

        if ($this->globalPartDefaults instanceof GlobalPartDefaultsResolverInterface) {
            return $this->globalPartDefaults->resolveForType($type);
        }

        return $this->globalParts->findByType($type);
    }
}
