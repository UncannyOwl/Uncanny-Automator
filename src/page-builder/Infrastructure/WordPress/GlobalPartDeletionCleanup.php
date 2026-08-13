<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Infrastructure\Persistence\SchemaManager;
use UncannyPageBuilder\Infrastructure\Persistence\WpSettingsRepository;

/**
 * Cleans Page Builder-owned global-part persistence when WordPress deletes the CPT.
 *
 * The global-part section table has no database-level foreign key because it
 * runs on arbitrary WordPress installs. Keep the cascade explicit at the
 * WordPress hook boundary.
 */
final class GlobalPartDeletionCleanup
{
    private const CPT           = 'upb_global_part';
    private const CLEANUP_RETRY_OPTION = 'uncanny_page_builder_global_part_cleanup_retries';
    private const PAGE_OVERRIDE_META_KEYS = [
        '_uncanny_engine_page_header_id',
        '_uncanny_engine_page_footer_id',
    ];

    public function __construct(
        private readonly GlobalSourceMutation $globalSource,
        private readonly ?WorkingCanvasRefreshScheduler $workingCanvasRefreshes = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function register(): void
    {
        // Core and third-party hooks finish their WordPress transition first.
        // Each callback below opens a transaction only around Page Builder rows
        // and the matching global generation increment.
        add_action('deleted_post', [$this, 'cleanup'], 10, 2);
        add_action('trashed_post', [$this, 'clearReferences'], 10, 2);
        add_action('untrashed_post', [$this, 'markUntrashed'], 10, 2);
        add_action('admin_init', [$this, 'retryPendingCleanup']);
        add_action('admin_init', [$this, 'reconcileOrphanedSources']);
    }

    public function cleanup($postId = null, $post = null): void
    {
        $postId = WordPressPostId::fromMixed($postId);
        if ($postId === null) {
            return;
        }

        if (!$this->isGlobalPart($postId, $post)) {
            return;
        }

        try {
            $this->cleanupGlobalPart($postId);
        } catch (\Throwable $failure) {
            // WordPress has already deleted the post. This callback cannot
            // reverse that transition, and it must not terminate callbacks
            // from other plugins in the same request.
            $this->reportFailure('permanent_cleanup', $postId, $failure);
            $this->rememberCleanupRetry($postId, 'permanent_cleanup');
            return;
        }

        $this->clearCleanupRetry($postId);
        $this->scheduleWorkingCanvasRefreshesAfterCommit($postId, 'permanent_refresh');
    }

    public function clearReferences($postId = null, $post = null): void
    {
        $postId = WordPressPostId::fromMixed($postId);
        if ($postId === null) {
            return;
        }

        if (!$this->isGlobalPart($postId, $post)) {
            return;
        }

        try {
            $this->clearReferencesForGlobalPart($postId);
        } catch (\Throwable $failure) {
            // WordPress has already moved the post to Trash. Keep the shared
            // request alive and record that Page Builder cleanup is incomplete.
            $this->reportFailure('trash_reference_cleanup', $postId, $failure);
            $this->rememberCleanupRetry($postId, 'trash_reference_cleanup');
            return;
        }

        $this->clearCleanupRetry($postId);
        $this->scheduleWorkingCanvasRefreshesAfterCommit($postId, 'trash_refresh');
    }

    public function markUntrashed($postId = null, $post = null): void
    {
        $postId = WordPressPostId::fromMixed($postId);
        if ($postId === null) {
            return;
        }

        if (!$this->isGlobalPart($postId, $post)) {
            return;
        }

        try {
            $this->globalSource->run(static fn(): mixed => null);
        } catch (\Throwable $failure) {
            // WordPress has already restored the post. The derived refresh is
            // secondary and must not terminate the shared request.
            $this->reportFailure('untrash_refresh', $postId, $failure);
            $this->rememberCleanupRetry($postId, 'untrash_refresh');
            return;
        }

        $this->clearCleanupRetry($postId);
        $this->scheduleWorkingCanvasRefreshesAfterCommit($postId, 'untrash_refresh');
    }

    public function retryPendingCleanup(): void
    {
        try {
            $pending = get_option(self::CLEANUP_RETRY_OPTION, []);
        } catch (\Throwable $failure) {
            $this->reportFailure('read_cleanup_retries', 0, $failure);
            return;
        }

        if (!is_array($pending)) {
            return;
        }

        foreach (array_slice($pending, 0, 20, true) as $postId => $operation) {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null || !is_string($operation)) {
                continue;
            }

            try {
                $this->retryCleanupOperation($postId, $operation);
            } catch (\Throwable $failure) {
                $this->reportFailure($operation, $postId, $failure);
                continue;
            }

            $this->clearCleanupRetry($postId);
            if ($operation !== 'working_canvas_refresh') {
                $this->scheduleWorkingCanvasRefreshesAfterCommit($postId, 'retry_refresh');
            }
        }
    }

    public function reconcileOrphanedSources(): void
    {
        try {
            $postIds = $this->findOrphanedGlobalPartIds();
        } catch (\Throwable $failure) {
            $this->reportFailure('orphan_scan', 0, $failure);
            return;
        }

        if ($postIds === []) {
            return;
        }

        try {
            $this->globalSource->run(function () use ($postIds): void {
                foreach ($postIds as $postId) {
                    $this->cleanupGlobalPart($postId);
                }
            });
        } catch (\Throwable $failure) {
            foreach ($postIds as $postId) {
                $this->reportFailure('orphan_cleanup', $postId, $failure);
                $this->rememberCleanupRetry($postId, 'permanent_cleanup');
            }
            return;
        }

        foreach ($postIds as $postId) {
            $this->clearCleanupRetry($postId);
        }
        $this->scheduleWorkingCanvasRefreshesAfterCommit($postIds[0], 'orphan_refresh');
    }

    private function cleanupGlobalPart(int $postId): void
    {
        if (!$this->globalSource->isRunning()) {
            $this->globalSource->run(function () use ($postId): void {
                $this->cleanupGlobalPart($postId);
            });
            return;
        }

        $this->clearGlobalPartReferences($postId);

        global $wpdb;
        $table = SchemaManager::globalSectionsTableName();

        $deleted = $wpdb->delete($table, ['global_part_id' => $postId], ['%d']);
        if ($deleted === false) {
            throw new \RuntimeException('Failed to delete reusable source rows.');
        }
    }

    /** @return int[] */
    private function findOrphanedGlobalPartIds(): array
    {
        global $wpdb;
        $sourcesTable = SchemaManager::globalSectionsTableName();
        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $wpdb->last_error = '';
        $postIds = $wpdb->get_col(
            "SELECT DISTINCT sources.global_part_id
             FROM {$sourcesTable} AS sources
             LEFT JOIN {$postsTable} AS posts ON posts.ID = sources.global_part_id
             WHERE posts.ID IS NULL
             ORDER BY sources.global_part_id ASC
             LIMIT 20",
        );
        if (!is_array($postIds) || (string) ($wpdb->last_error ?? '') !== '') {
            throw new \RuntimeException('Failed to find orphaned reusable source rows.');
        }

        $validPostIds = [];
        foreach ($postIds as $postId) {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId !== null) {
                $validPostIds[$postId] = $postId;
            }
        }

        return array_values($validPostIds);
    }

    private function clearReferencesForGlobalPart(int $postId): void
    {
        if (!$this->globalSource->isRunning()) {
            $this->globalSource->run(function () use ($postId): void {
                $this->clearReferencesForGlobalPart($postId);
            });
            return;
        }

        $this->clearGlobalPartReferences($postId);
    }

    private function scheduleWorkingCanvasRefreshesAfterCommit(int $postId, string $operation): void
    {
        try {
            $this->workingCanvasRefreshes?->enqueueAll();
        } catch (\Throwable $failure) {
            // Required cleanup is committed. A derived refresh failure must
            // not roll it back or make the WordPress transition fatal.
            $this->reportFailure($operation, $postId, $failure);
            $this->rememberCleanupRetry($postId, 'working_canvas_refresh');
        }
    }

    private function retryCleanupOperation(int $postId, string $operation): void
    {
        if ($operation === 'permanent_cleanup') {
            $this->cleanupGlobalPart($postId);
            return;
        }

        if ($operation === 'trash_reference_cleanup') {
            $this->clearReferencesForGlobalPart($postId);
            return;
        }

        if ($operation === 'untrash_refresh') {
            $this->globalSource->run(static fn(): mixed => null);
            return;
        }

        if ($operation === 'working_canvas_refresh') {
            $this->workingCanvasRefreshes?->enqueueAll();
            return;
        }

        throw new \RuntimeException('Unknown reusable cleanup retry operation.');
    }

    private function rememberCleanupRetry(int $postId, string $operation): void
    {
        try {
            $pending = get_option(self::CLEANUP_RETRY_OPTION, []);
            $pending = is_array($pending) ? $pending : [];
            $pending[$postId] = $operation;
            $updated = update_option(self::CLEANUP_RETRY_OPTION, $pending, false);
            if ($updated === false && get_option(self::CLEANUP_RETRY_OPTION, []) !== $pending) {
                throw new \RuntimeException('Failed to store the reusable cleanup retry.');
            }
        } catch (\Throwable $failure) {
            // The original WordPress transition already completed. Recording a
            // retry is best effort and must not turn that transition fatal.
            $this->reportFailure('record_cleanup_retry', $postId, $failure);
        }
    }

    private function clearCleanupRetry(int $postId): void
    {
        try {
            $pending = get_option(self::CLEANUP_RETRY_OPTION, []);
            if (!is_array($pending) || !array_key_exists($postId, $pending)) {
                return;
            }

            unset($pending[$postId]);
            if ($pending === []) {
                delete_option(self::CLEANUP_RETRY_OPTION);
                return;
            }

            $updated = update_option(self::CLEANUP_RETRY_OPTION, $pending, false);
            if ($updated === false && get_option(self::CLEANUP_RETRY_OPTION, []) !== $pending) {
                throw new \RuntimeException('Failed to clear the reusable cleanup retry.');
            }
        } catch (\Throwable $failure) {
            // A stale retry can repeat idempotent cleanup. It is safer than
            // losing the recovery signal for a completed WordPress transition.
            $this->reportFailure('clear_cleanup_retry', $postId, $failure);
        }
    }

    private function clearGlobalPartReferences(int $postId): void
    {
        $settingsRepository = new WpSettingsRepository();
        $settingsRepository->mutate(
            static function (\UncannyPageBuilder\Domain\Settings\Settings $settings) use ($postId): \UncannyPageBuilder\Domain\Settings\Settings {
                $layout = $settings->pageLayout();
                if ($layout->defaultHeaderId() !== $postId && $layout->defaultFooterId() !== $postId) {
                    return $settings;
                }

                return $settings->withPageLayout(
                    new \UncannyPageBuilder\Domain\Settings\PageLayoutSettings(
                        $layout->defaultHeaderId() === $postId ? null : $layout->defaultHeaderId(),
                        $layout->defaultFooterId() === $postId ? null : $layout->defaultFooterId(),
                    ),
                );
            },
        );

        foreach (self::PAGE_OVERRIDE_META_KEYS as $metaKey) {
            $deleted = delete_metadata('post', 0, $metaKey, (string) $postId, true);
            if ($deleted === false && $this->pageOverrideReferenceExists($metaKey, $postId)) {
                throw new \RuntimeException('Failed to clear reusable page references.');
            }
        }
    }

    private function pageOverrideReferenceExists(string $metaKey, int $postId): bool
    {
        global $wpdb;

        $postmetaTable = isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta';
        $wpdb->last_error = '';
        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM {$postmetaTable}
             WHERE meta_key = %s AND meta_value = %s
             LIMIT 1",
            $metaKey,
            (string) $postId,
        ));
        if ((string) ($wpdb->last_error ?? '') !== '') {
            throw new \RuntimeException('Failed to verify reusable page reference cleanup.');
        }

        return $found !== null && $found !== false;
    }

    private function isGlobalPart(int $postId, mixed $post): bool
    {
        if ($post instanceof \WP_Post) {
            return $post->post_type === self::CPT;
        }

        return get_post_type($postId) === self::CPT;
    }

    private function reportFailure(string $operation, int $postId, \Throwable $failure): void
    {
        try {
            if ($this->failureReporter instanceof FailureReporterInterface) {
                $this->failureReporter->report('reusable cleanup', $postId, $operation, $failure);
                return;
            }
        } catch (\Throwable) {
            // A reporting failure cannot escape this WordPress hook boundary.
        }

        error_log(sprintf(
            '[Uncanny Page Builder] Reusable cleanup %s failed for post %d (%s).',
            $operation,
            $postId,
            $failure::class,
        ));
    }
}
