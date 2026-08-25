<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\DesignStyles\WorkingDesignTokenCssRendererInterface;
use UncannyPageBuilder\Domain\DesignStandards\DesignTokenCssRenderer;
use UncannyPageBuilder\Domain\DesignStandards\PageDesignOverrides;

/**
 * Renders mutable design settings only for editor and global-part surfaces.
 */
final class WorkingDesignTokenCss implements WorkingDesignTokenCssRendererInterface
{
    public function __construct(
        private readonly DesignStandardsService $designStandards,
        private readonly ?SelectEditorPageSource $pageSources = null,
    ) {}

    public function render(int $pageId, bool $isGlobalPart): string
    {
        $profile = $this->designStandards->resolveForPage($isGlobalPart ? 0 : $pageId);
        if (!$isGlobalPart && $pageId > 0 && $this->pageSources instanceof SelectEditorPageSource) {
            $selection = $this->pageSources->forPage($pageId);
            $source = $selection->loadedSource() === 'published'
                ? $selection->publishedSnapshot()?->source()
                : null;
            if (is_array($source)) {
                try {
                    $overrides = PageDesignOverrides::fromArray(
                        is_array($source['page_design_overrides'] ?? null)
                            ? $source['page_design_overrides']
                            : [],
                    );
                    $profile = $this->designStandards
                        ->resolveForPageWithAudit($pageId, $overrides)
                        ->resolved();
                } catch (\Throwable) {
                    /*
                     * Never fall back to mutable working overrides while the
                     * editor is displaying published source. An invalid legacy
                     * snapshot safely receives no page overrides rather than
                     * leaking a hidden parked draft into the visible canvas.
                     */
                    $profile = $this->designStandards
                        ->resolveForPageWithAudit($pageId, new PageDesignOverrides())
                        ->resolved();
                }
            }
        }
        $css = DesignTokenCssRenderer::renderProfile($profile, DesignTokenCssRenderer::CANVAS_SELECTOR);

        $filteredCss = apply_filters('uncanny_engine_theme_css', $css, $pageId);

        return is_string($filteredCss) ? $filteredCss : $css;
    }

    public function renderForEditor(int $postId): ?string
    {
        try {
            return $this->render(
                $postId,
                $postId > 0 && get_post_type($postId) === 'upb_global_part',
            );
        } catch (\Throwable $failure) {
            /*
             * WordPress filters can contain third-party callbacks. A callback
             * failure must not turn a completed design write into an uncertain
             * REST failure. The browser keeps its current preview until reload
             * when the exact server projection is unavailable.
             */
            error_log(sprintf(
                '[Uncanny Page Builder] Working design token projection unavailable (%s).',
                $failure::class,
            ));

            return null;
        }
    }
}
