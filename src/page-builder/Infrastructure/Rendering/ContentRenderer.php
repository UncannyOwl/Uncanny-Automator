<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Canvas\OriginalPageContentReaderInterface;
use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;
use UncannyPageBuilder\Application\Rendering\PublishedPage;
use UncannyPageBuilder\Application\Rendering\PublishedPageStatus;
use UncannyPageBuilder\Application\Rendering\LucideRuntimeInitializer;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostId;

/**
 * Renders one exact published artifact through the_content filter.
 *
 * In this mode, the theme controls the full page structure. Uncanny injects
 * section HTML where the_content() is called, section CSS via wp_head,
 * and the Magic Bridge root via wp_footer.
 */
final class ContentRenderer
{
    private bool $didRender = false;

    public function __construct(
        private readonly PublicPageRenderPolicy $publicPageRenderPolicy,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly OriginalPageContentReaderInterface $originalContent,
    ) {}

    /**
     * Select the raw WordPress fallback before block, shortcode, and wpautop
     * filters run. A broken or absent pointer must never return a late raw body
     * after WordPress has already processed the legacy post_content value.
     *
     * Hook on the_content at priority 7 when the pointer runtime is activated.
     */
    public function selectOriginalContent($content = null): string
    {
        $content = is_string($content) ? $content : '';

        if (is_admin() || !is_singular() || !is_main_query()) {
            return $content;
        }

        $postId = $this->currentPostId();
        if ($postId === null || !$this->isActivePost($postId)) {
            return $content;
        }

        // Preserve the password form already prepared by WordPress. Falling
        // back to raw original content here would disclose the protected body.
        if ($this->publicPageRenderPolicy->isPasswordRequired($postId)) {
            return $content;
        }

        $read = $this->publicPageRenderPolicy->read($postId);
        if ($read->isReady() || $read->status() === PublishedPageStatus::NotManaged) {
            return $content;
        }

        try {
            return $this->originalContent->publicContent($postId);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Hook on the_content at priority 99 (after wpautop/shortcodes).
     */
    public function filter($content = null): string
    {
        $content = is_string($content) ? $content : '';

        // Theme composition frontend rendering never runs inside the admin canvas.
        if (is_admin()) {
            return $content;
        }

        if (!is_singular() || !is_main_query()) {
            return $content;
        }

        $postId = $this->currentPostId();

        if ($postId === null || !$this->isActivePost($postId)) {
            return $content;
        }

        $page = $this->publicPageRenderPolicy->publishedPage($postId);
        if (!$page instanceof PublishedPage) {
            return $content;
        }

        /*
         * Native artifacts belong to the standalone published template. If
         * template routing ever falls back to the theme, do not inject native
         * document HTML into the_content and create a malformed hybrid page.
         */
        if ($page->shellMode() !== ShellMode::ThemeComposition) {
            return $content;
        }

        $this->didRender = true;

        return $page->html();
    }

    /**
     * Pointer-derived shell classes for the eventual exact-render cutover.
     * Working draft shell state must never affect the public body class.
     *
     * @param mixed $classes
     * @return string[]
     */
    public function bodyClasses($classes = null): array
    {
        $classes = is_array($classes)
            ? array_values(array_filter($classes, 'is_string'))
            : [];

        if (is_admin() || !is_singular()) {
            return $classes;
        }

        $postId = $this->currentPostId();
        $page = $postId !== null ? $this->publicPageRenderPolicy->publishedPage($postId) : null;
        if (!$page instanceof PublishedPage) {
            return $classes;
        }

        $class = $page->shellMode() === ShellMode::ThemeComposition
            ? 'upb-theme-composed'
            : 'upb-uncanny-native';
        if (!in_array($class, $classes, true)) {
            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * Hook on wp_head at priority 99. Injects the pointed artifact CSS for
     * theme-composition pages.
     */
    public function injectCss(): void
    {
        // Theme composition frontend rendering never runs inside the admin canvas.
        if (is_admin()) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $postId = $this->currentPostId();

        $page = $postId !== null ? $this->themeCompositionPage($postId) : null;
        if (!$page instanceof PublishedPage) {
            return;
        }

        if ($page->css() !== '') {
            echo '<style id="uncanny-page-builder-published-css">'
                . StyleElementCss::escape($page->css())
                . '</style>';
        }
    }

    /**
     * Hook on wp_footer. Outputs the Magic Bridge root div for
     * theme_composition pages (in canvas mode, canvas.php handles this).
     */
    public function renderBridgeRoot(): void
    {
        // Theme composition frontend rendering never runs inside the admin canvas.
        if (is_admin()) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $postId = $this->currentPostId();

        $page = $postId !== null ? $this->themeCompositionPage($postId) : null;
        if (!$page instanceof PublishedPage) {
            return;
        }

        if ($this->allowedCapabilities->currentUserHasAllowedCapability()) {
            echo '<div id="uncanny-magic-bridge-root" data-page-id="' . esc_attr((string) $postId) . '"></div>';
        }
    }

    /**
     * Hook on wp_footer for theme_composition pages. Emits Page Builder-owned
     * page JavaScript after the canvas DOM exists.
     */
    public function renderCustomJavaScript(): void
    {
        // Theme composition frontend rendering never runs inside the admin canvas.
        if (is_admin()) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $postId = $this->currentPostId();

        $page = $postId !== null ? $this->themeCompositionPage($postId) : null;
        if (!$page instanceof PublishedPage) {
            return;
        }

        echo '<script>' . LucideRuntimeInitializer::script() . '</script>';
        echo $page->customJavaScript();
    }

    /**
     * Hook on wp_footer at a late priority. Warns admins if sections
     * were not rendered because the template didn't call the_content.
     */
    public function checkRenderFallback(): void
    {
        // Theme composition frontend rendering never runs inside the admin canvas.
        if (is_admin()) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $postId = $this->currentPostId();

        $page = $postId !== null ? $this->themeCompositionPage($postId) : null;
        if (!$page instanceof PublishedPage) {
            return;
        }

        if ($this->didRender) {
            return;
        }

        if ($this->allowedCapabilities->currentUserHasAllowedCapability()) {
            include __DIR__ . '/../../Presentation/Frontend/fallback-warning.php';
        }
    }

    private function themeCompositionPage(int $postId): ?PublishedPage
    {
        $page = $this->publicPageRenderPolicy->publishedPage($postId);

        return $page instanceof PublishedPage && $page->shellMode() === ShellMode::ThemeComposition
            ? $page
            : null;
    }

    private function currentPostId(): ?int
    {
        return WordPressPostId::fromCurrentQuery(get_queried_object_id());
    }

    private function isActivePost(int $queriedPostId): bool
    {
        return WordPressPostId::fromMixed(get_the_ID()) === $queriedPostId;
    }
}
