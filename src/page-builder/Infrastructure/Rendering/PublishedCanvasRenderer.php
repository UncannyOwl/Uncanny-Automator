<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Application\Canvas\OriginalPageContentReaderInterface;
use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;
use UncannyPageBuilder\Application\Rendering\PublishedPage;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Renders the standalone native document from one exact published artifact.
 *
 * Frontend routing reaches this service only through the exact published
 * pointer. Working source has no public rendering path.
 */
final class PublishedCanvasRenderer
{
    public function __construct(
        private readonly PublicPageRenderPolicy $publicPages,
        private readonly string $pluginPath,
        private readonly DynamicRenderer $dynamicRenderer,
        private readonly OriginalPageContentReaderInterface $originalContent,
    ) {}

    public function render(int $postId): bool
    {
        $publishedPage = $this->publicPages->publishedPage($postId);
        if (!$publishedPage instanceof PublishedPage || $publishedPage->shellMode() !== ShellMode::UncannyNative) {
            return false;
        }

        $publishedRuntimeEnabled = true;

        try {
            $publishedHtml = $this->dynamicRenderer->render($publishedPage->html());
        } catch (\Throwable $failure) {
            // A raw runtime template can contain request-sensitive placeholder
            // data. Use the preserved WordPress body and disable artifact
            // runtime instead of exposing raw source or an empty document.
            error_log('[Uncanny Page Builder] Published binding render failed (' . $failure::class . ')');
            $publishedRuntimeEnabled = false;
            $publishedHtml = $this->fallbackContent($postId);
        }

        include rtrim($this->pluginPath, '/') . '/templates/published-canvas.php';

        return true;
    }

    private function fallbackContent(int $postId): string
    {
        try {
            $content = $this->originalContent->publicContent($postId);
        } catch (\Throwable $failure) {
            error_log('[Uncanny Page Builder] Published WordPress fallback read failed (' . $failure::class . ')');

            return '';
        }

        try {
            $filtered = apply_filters('the_content', $content);

            return is_string($filtered) ? $filtered : $content;
        } catch (\Throwable $failure) {
            // The preserved body is safer than a blank public document when
            // an unrelated content filter fails.
            error_log('[Uncanny Page Builder] Published WordPress fallback filter failed (' . $failure::class . ')');

            return $content;
        }
    }
}
