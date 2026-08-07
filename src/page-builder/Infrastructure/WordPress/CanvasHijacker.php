<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;
use UncannyPageBuilder\Domain\Shell\ShellMode;

final class CanvasHijacker
{
    private string $pluginPath;
    private PublicPageRenderPolicy $publicPageRenderPolicy;
    private CanvasAssetAllowlist $assetAllowlist;
    private GetPageBuilderAllowedCapabilities $allowedCapabilities;

    public function __construct(
        string $pluginPath,
        PublicPageRenderPolicy $publicPageRenderPolicy,
        GetPageBuilderAllowedCapabilities $allowedCapabilities,
        ?CanvasAssetAllowlist $assetAllowlist = null,
    ) {
        $this->pluginPath       = $pluginPath;
        $this->publicPageRenderPolicy = $publicPageRenderPolicy;
        $this->allowedCapabilities = $allowedCapabilities;
        $this->assetAllowlist   = $assetAllowlist ?? new CanvasAssetAllowlist();
    }

    public function hijack(string $template): string
    {
        if (!is_singular()) {
            return $template;
        }

        $postId = (int) get_the_ID();

        // Global parts: require edit capability (not publicly viewable).
        if (is_singular('upb_global_part')) {
            if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
                return $this->denyGlobalPartAccess($template);
            }

            if ($postId <= 0) {
                return $template;
            }

            // Global parts are authenticated working documents. They do not
            // have immutable page publication pointers.
            return $this->returnWorkingCanvas($template, $postId);
        }

        if ($postId <= 0) {
            return $template;
        }

        $publishedPage = $this->publicPageRenderPolicy->publishedPage($postId);
        if ($publishedPage === null) {
            return $template;
        }

        // In theme_composition mode, let the theme render normally.
        // ContentRenderer handles the exact artifact through the_content.
        if ($publishedPage->shellMode() === ShellMode::ThemeComposition) {
            return $template;
        }

        return $this->returnPublishedCanvas($template);
    }

    private function returnWorkingCanvas(string $fallback, int $postId): string
    {
        $canvas = $this->pluginPath . 'templates/canvas.php';
        if (!file_exists($canvas)) {
            return $fallback;
        }

        $this->prepareCanvasResponse();

        return apply_filters('uncanny_page_builder_canvas_template', $canvas, $postId);
    }

    private function returnPublishedCanvas(string $fallback): string
    {
        $canvas = $this->pluginPath . 'templates/published-canvas-loader.php';
        if (!file_exists($canvas)) {
            return $fallback;
        }

        $this->prepareCanvasResponse();

        /*
         * The immutable public loader is not filterable through the legacy
         * editor-template seam. That filter can intentionally route working
         * editors, but must never swap exact public output back to canvas.php.
         */
        return $canvas;
    }

    private function prepareCanvasResponse(): void
    {
        // Authenticated working canvases can carry REST nonces. Public exact
        // canvases normally remain cacheable for anonymous visitors.
        if (is_user_logged_in()) {
            nocache_headers();
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }
        }

        $this->assetAllowlist->registerPrintGuards();
    }

    private function denyGlobalPartAccess(string $fallback): string
    {
        global $wp_query;

        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }

        status_header(404);
        nocache_headers();

        $template = get_404_template();

        return is_string($template) && $template !== '' ? $template : $fallback;
    }
}
