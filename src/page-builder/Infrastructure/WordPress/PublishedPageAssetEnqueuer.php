<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;

/**
 * Enqueues the installed plugin runtime required by the selected public artifact.
 */
final class PublishedPageAssetEnqueuer
{
    public function __construct(
        private readonly PublicPageRenderPolicy $publicPages,
    ) {}

    public function enqueue(): void
    {
        if (!is_singular()) {
            return;
        }

        $pageId = (int) get_the_ID();
        $page = $this->publicPages->publishedPage($pageId);
        if ($page === null) {
            return;
        }

        $assets = $page->assets();
        $bootstrap = $assets->get('bootstrap');
        $spacing = $assets->get('bootstrap_spacing');
        $lucide = $assets->get('lucide');
        $alpine = $assets->get('alpine');

        if ($bootstrap !== null) {
            wp_enqueue_style(
                'uncanny-page-builder-bootstrap',
                $bootstrap['url'],
                [],
                $bootstrap['sha256'],
            );
        }
        if ($spacing !== null) {
            wp_enqueue_style(
                'uncanny-page-builder-bootstrap-spacing',
                $spacing['url'],
                $bootstrap !== null ? ['uncanny-page-builder-bootstrap'] : [],
                $spacing['sha256'],
            );
        }
        if ($lucide !== null) {
            wp_enqueue_script(
                'uncanny-page-builder-lucide',
                $lucide['url'],
                [],
                $lucide['sha256'],
                ['in_footer' => true],
            );
        }
        if ($alpine !== null) {
            wp_enqueue_script(
                'uncanny-page-builder-alpine',
                $alpine['url'],
                [],
                $alpine['sha256'],
                ['strategy' => 'defer', 'in_footer' => true],
            );
        }
    }
}
