<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Canvas\OriginalPageContentStoreInterface;
use UncannyPageBuilder\Application\Canvas\ReturnPageToWordPressTransitionInterface;
use UncannyPageBuilder\Domain\Canvas\PageOwnershipRepositoryInterface;
use UncannyPageBuilder\Infrastructure\Persistence\SchemaManager;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;
use UncannyPageBuilder\Infrastructure\Persistence\WpPageOwnershipRepository;

/**
 * Atomically ends Page Builder public handover under the publication lock order.
 *
 * The page row is locked before publication state, matching PublishPage. This
 * prevents a stale human publication from moving the pointer while ownership,
 * original content, and publication state are returning to WordPress.
 */
final class WordPressPageHandover implements ReturnPageToWordPressTransitionInterface
{
    /** @var array<string, true> */
    private array $transactionalTables = [];

    public function __construct(
        private readonly PageOwnershipRepositoryInterface $ownership,
        private readonly OriginalPageContentStoreInterface $originalContent,
    ) {}

    public function returnToWordPress(int $pageId): bool
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('A positive page ID is required.');
        }

        global $wpdb;
        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $postmetaTable = isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta';
        $stateTable = SchemaManager::pageStateTableName();
        $this->assertTransactionalTables([$postsTable, $postmetaTable, $stateTable]);

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('Failed to start the WordPress handover transaction.');
        }

        try {
            $this->lockPage($postsTable, $pageId);
            $this->lockPageState($stateTable, $pageId);
            $this->lockHandoverMetadata($postmetaTable, $pageId);
            $this->cleanPageCache($pageId);

            if (!$this->ownership->isOwned($pageId)) {
                if ($wpdb->query('COMMIT') === false) {
                    throw new \RuntimeException('Failed to finish the WordPress handover transaction.');
                }

                $this->cleanPageCache($pageId);

                return false;
            }

            $this->originalContent->restore($pageId);
            $this->clearPublication($stateTable, $pageId);
            $this->originalContent->discardBackup($pageId);
            $this->ownership->markWordPressManaged($pageId);

            if ($wpdb->query('COMMIT') === false) {
                throw new \RuntimeException('Failed to commit the WordPress handover transaction.');
            }
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            $this->cleanPageCache($pageId);
            throw $exception;
        }

        $this->cleanPageCache($pageId);

        return true;
    }

    private function lockPage(string $postsTable, int $pageId): void
    {
        global $wpdb;
        $locked = $wpdb->query($wpdb->prepare(
            "SELECT ID FROM {$postsTable} WHERE ID = %d FOR UPDATE",
            $pageId,
        ));
        if ($locked === false || $locked < 1) {
            throw new \RuntimeException('Failed to lock the WordPress page for handover.');
        }
    }

    private function lockPageState(string $stateTable, int $pageId): void
    {
        global $wpdb;
        $locked = $wpdb->query($wpdb->prepare(
            "SELECT page_id FROM {$stateTable} WHERE page_id = %d FOR UPDATE",
            $pageId,
        ));
        if ($locked === false || $locked < 1) {
            throw new \RuntimeException('Failed to lock Page Builder publication state for handover.');
        }
    }

    private function lockHandoverMetadata(string $postmetaTable, int $pageId): void
    {
        global $wpdb;
        $locked = $wpdb->query($wpdb->prepare(
            "SELECT meta_id FROM {$postmetaTable}
             WHERE post_id = %d AND meta_key IN (%s, %s, %s)
             FOR UPDATE",
            $pageId,
            WpPageOwnershipRepository::META_OWNED,
            WpPageOwnershipRepository::META_ACTIVE,
            WpOriginalPageContentStore::META_KEY,
        ));
        if ($locked === false) {
            throw new \RuntimeException('Failed to lock Page Builder handover metadata.');
        }
    }

    private function clearPublication(string $stateTable, int $pageId): void
    {
        global $wpdb;
        $updated = $wpdb->update(
            $stateTable,
            [
                'published_artifact_id' => null,
                'published_source_snapshot_id' => null,
                'published_by' => null,
                'published_at' => null,
            ],
            ['page_id' => $pageId],
            ['%d', '%d', '%d', '%s'],
            ['%d'],
        );
        if ($updated === false) {
            throw new \RuntimeException('Failed to clear Page Builder publication during WordPress handover.');
        }
    }

    /** @param string[] $tables */
    private function assertTransactionalTables(array $tables): void
    {
        global $wpdb;

        foreach ($tables as $table) {
            if (isset($this->transactionalTables[$table])) {
                continue;
            }

            $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
            $engine = is_object($status) && isset($status->Engine) ? (string) $status->Engine : '';
            if (strcasecmp($engine, 'InnoDB') !== 0) {
                throw new SourceTransactionsUnavailableException($table, $engine !== '' ? $engine : 'unknown');
            }

            $this->transactionalTables[$table] = true;
        }
    }

    private function cleanPageCache(int $pageId): void
    {
        if (function_exists(__NAMESPACE__ . '\clean_post_cache') || function_exists('clean_post_cache')) {
            clean_post_cache($pageId);
        }
    }
}
