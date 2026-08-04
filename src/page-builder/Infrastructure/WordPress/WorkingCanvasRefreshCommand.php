<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Publishing\OwnedPageFinderInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;

final class WorkingCanvasRefreshCommand
{
    public function __construct(
        private readonly OwnedPageFinderInterface $ownedPages,
        private readonly WorkingCanvasRefresherInterface $workingCanvas,
    ) {
    }

    /**
     * Backfill derived working canvas state for Page Builder-owned pages.
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Maximum number of owned pages to process. Default 100.
     *
     * [--after-page-id=<page-id>]
     * : Resume after this page id. The next run prints the last processed id.
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        unset($args);

        $limit = isset($assocArgs['limit']) ? max(1, min(1000, (int) $assocArgs['limit'])) : 100;
        $afterPageId = isset($assocArgs['after-page-id']) ? max(0, (int) $assocArgs['after-page-id']) : 0;
        $processed = 0;
        $failed = 0;
        $lastPageId = $afterPageId;

        foreach ($this->ownedPages->ownedPageIds($limit, $afterPageId) as $pageId) {
            $lastPageId = $pageId;
            try {
                $this->workingCanvas->refresh($pageId);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                if (class_exists('\WP_CLI')) {
                    \WP_CLI::warning(sprintf('Page %d working-canvas refresh failed: %s', $pageId, $e->getMessage()));
                }
            }
        }

        if (class_exists('\WP_CLI')) {
            \WP_CLI::success(sprintf(
                'Refreshed working canvas state for %d %s. Failures: %d. Next cursor: --after-page-id=%d.',
                $processed,
                $processed === 1 ? 'page' : 'pages',
                $failed,
                $lastPageId,
            ));
        }
    }

    public function register(): void
    {
        if (!class_exists('\WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('uncanny-page-builder working-canvas refresh', $this);
    }
}
