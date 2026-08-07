<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifactRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceRowsCleanupInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshQueueCleanupInterface;

final class PublishedPageCleanup
{
    public function __construct(
        private readonly PageStateRepositoryInterface $pageStates,
        private readonly PublishedPageArtifactRepositoryInterface $publishedArtifacts,
        private readonly ?PageSourceRowsCleanupInterface $pageSourceRows = null,
        private readonly ?PageSourceSnapshotRepositoryInterface $pageSourceSnapshots = null,
        private readonly ?WorkingCanvasRefreshQueueCleanupInterface $workingCanvasRefreshQueue = null,
    ) {}

    public function register(): void
    {
        add_action('deleted_post', [$this, 'cleanup'], 10, 2);
    }

    public function cleanup($postId = null, $post = null): void
    {
        $postId = WordPressPostId::fromMixed($postId);
        if (
            $postId === null
            || !$post instanceof \WP_Post
            || WordPressPostId::fromMixed($post->ID) !== $postId
        ) {
            return;
        }

        // Cleanup runs only after WordPress has permanently deleted the post.
        // A cleanup failure can leave orphaned Page Builder rows, but can never
        // strand an existing page after its public pointer has been removed.
        $failures = [];
        foreach (
            [
            'page_state' => fn (): mixed => $this->pageStates->deleteForPage($postId),
            'published_artifacts' => fn (): mixed => $this->publishedArtifacts->deleteForPage($postId),
            'page_source_snapshots' => fn (): mixed => $this->pageSourceSnapshots?->deleteForPage($postId),
            'page_source' => fn (): mixed => $this->pageSourceRows?->deleteForPage($postId),
            'working_canvas_refresh_queue' => fn (): mixed => $this->workingCanvasRefreshQueue?->removePage($postId),
            ] as $boundary => $cleanup
        ) {
            try {
                $cleanup();
            } catch (\Throwable $error) {
                $failures[] = $boundary . ': ' . $error::class . ': ' . $error->getMessage();
            }
        }

        if ($failures !== []) {
            error_log(sprintf(
                '[Uncanny Page Builder] Permanent cleanup incomplete for page %d: %s',
                $postId,
                implode('; ', $failures),
            ));
        }
    }
}
