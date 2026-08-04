<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

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
    ) {}

    public function render(int $postId): bool
    {
        $publishedPage = $this->publicPages->publishedPage($postId);
        if (!$publishedPage instanceof PublishedPage || $publishedPage->shellMode() !== ShellMode::UncannyNative) {
            return false;
        }

        include rtrim($this->pluginPath, '/') . '/templates/published-canvas.php';

        return true;
    }
}
