<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Loads mutable plugin runtime assets for editor-owned canvases.
 *
 * Public pages use PublishedPageAssetEnqueuer from the exact selected
 * immutable artifact and never load assets from working source.
 */
final class WorkingCanvasAssetEnqueuer
{
    public function __construct(
        private readonly string $pluginUrl,
        private readonly string $version,
        private readonly SectionRepositoryInterface $sections,
    ) {}

    public function enqueue(int $postId, bool $isGlobalPart = false): void
    {
        if ($postId <= 0 || (!$isGlobalPart && !$this->sections->isOwnedPage($postId))) {
            return;
        }

        /*
         * The scoped builds provide Bootstrap layout behavior without
         * reboot-styling the editor chrome that shares this document.
         */
        wp_enqueue_style(
            'uncanny-page-builder-bootstrap',
            $this->pluginUrl . 'assets/css/bootstrap-scoped.min.css',
            [],
            $this->version,
        );
        wp_enqueue_style(
            'uncanny-page-builder-bootstrap-spacing',
            $this->pluginUrl . 'assets/css/bootstrap-extended-spacing-scoped.css',
            ['uncanny-page-builder-bootstrap'],
            $this->version,
        );

        // Section interactivity is Alpine-driven; Bootstrap JS is forbidden.
        wp_enqueue_script(
            'uncanny-page-builder-lucide',
            $this->pluginUrl . 'assets/js/lucide.min.js',
            [],
            $this->version,
            true,
        );
    }
}
