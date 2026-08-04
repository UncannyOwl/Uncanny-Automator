<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
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
    private const PAGE_OVERRIDE_META_KEYS = [
        '_uncanny_engine_page_header_id',
        '_uncanny_engine_page_footer_id',
    ];

    public function __construct(
        private readonly GlobalSourceMutation $globalSource,
        private readonly ?WorkingCanvasRefreshScheduler $workingCanvasRefreshes = null,
    ) {}

    public function register(): void
    {
        // Core and third-party hooks finish their WordPress transition first.
        // Each callback below opens a transaction only around Page Builder rows
        // and the matching global generation increment.
        add_action('deleted_post', [$this, 'cleanup'], 10, 2);
        add_action('trashed_post', [$this, 'clearReferences'], 10, 2);
        add_action('untrashed_post', [$this, 'markUntrashed'], 10, 2);
    }

    public function cleanup(int $postId, mixed $post = null): void
    {
        if (!$this->isGlobalPart($postId, $post)) {
            return;
        }

        if (!$this->globalSource->isRunning()) {
            $this->globalSource->run(function () use ($postId, $post): void {
                $this->cleanup($postId, $post);
            });
            return;
        }

        $this->scheduleWorkingCanvasRefreshes();
        $this->clearGlobalPartReferences($postId);

        global $wpdb;
        $table = SchemaManager::globalSectionsTableName();

        $deleted = $wpdb->delete($table, ['global_part_id' => $postId], ['%d']);
        if ($deleted === false) {
            throw new \RuntimeException('Failed to delete reusable source rows.');
        }
    }

    public function clearReferences(int $postId, mixed $post = null): void
    {
        if (!$this->isGlobalPart($postId, $post)) {
            return;
        }

        if (!$this->globalSource->isRunning()) {
            $this->globalSource->run(function () use ($postId, $post): void {
                $this->clearReferences($postId, $post);
            });
            return;
        }

        $this->scheduleWorkingCanvasRefreshes();
        $this->clearGlobalPartReferences($postId);
    }

    public function markUntrashed(int $postId, mixed $post = null): void
    {
        if (!$this->isGlobalPart($postId, $post)) {
            return;
        }

        $this->globalSource->run(function (): void {
            $this->scheduleWorkingCanvasRefreshes();
        });
    }

    private function scheduleWorkingCanvasRefreshes(): void
    {
        $this->workingCanvasRefreshes?->enqueueAll();
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
}
