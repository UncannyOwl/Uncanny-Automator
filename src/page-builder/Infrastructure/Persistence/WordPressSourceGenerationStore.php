<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\Concurrency\SourceGenerationSnapshot;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;

/**
 * Stores the two Page Builder source generations in WordPress.
 *
 * Page writes serialize on the owning wp_posts row and keep their generation
 * in post meta. Global writes serialize on one wp_options row. Publication
 * locks the page, its publication state, and the global row in a stable order,
 * then validates the captured generations before public state can move.
 */
final class WordPressSourceGenerationStore implements SourceGenerationStoreInterface
{
    public const PAGE_META_KEY = '_uncanny_page_builder_page_generation';
    public const GLOBAL_OPTION_KEY = 'uncanny_page_builder_global_generation';

    /** @var array<string, true> */
    private array $transactionalTables = [];

    public function pageGeneration(int $pageId): int
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be positive.');
        }

        global $wpdb;
        if (isset($wpdb) && method_exists($wpdb, 'get_var')) {
            $postmetaTable = isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta';
            $stored = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$postmetaTable}
                 WHERE post_id = %d AND meta_key = %s
                 ORDER BY meta_id DESC LIMIT 1",
                $pageId,
                self::PAGE_META_KEY,
            ));
            if ($stored !== null && $stored !== false && $stored !== '') {
                return max(0, (int) $stored);
            }
        }

        return max(0, (int) $this->readPostMeta($pageId, self::PAGE_META_KEY));
    }

    public function globalGeneration(): int
    {
        global $wpdb;
        if (isset($wpdb) && method_exists($wpdb, 'get_var')) {
            $optionsTable = isset($wpdb->options) ? (string) $wpdb->options : (string) $wpdb->prefix . 'options';
            $stored = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$optionsTable} WHERE option_name = %s LIMIT 1",
                self::GLOBAL_OPTION_KEY,
            ));
            if ($stored !== null && $stored !== false && $stored !== '') {
                return max(0, (int) $stored);
            }
        }

        return max(0, (int) $this->readOption(self::GLOBAL_OPTION_KEY, 0));
    }

    public function commitPage(int $pageId, int $expectedGeneration, callable $write): mixed
    {
        if ($pageId <= 0 || $expectedGeneration < 0) {
            throw new \InvalidArgumentException('A valid page generation is required.');
        }

        global $wpdb;
        $this->assertTransactionalTables([
            isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts',
            isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta',
            SchemaManager::tableName(),
            SchemaManager::pageStateTableName(),
            SchemaManager::operationsTableName(),
        ]);

        try {
            return $this->transaction(function () use ($pageId, $expectedGeneration, $write): mixed {
                $this->lockPage($pageId);
                $current = $this->lockedPageGeneration($pageId);
                $this->assertCurrent('page', $expectedGeneration, $current);

                $result = $write();
                $this->writePostMeta($pageId, self::PAGE_META_KEY, $expectedGeneration + 1);

                return $result;
            });
        } catch (\Throwable $exception) {
            /*
             * WordPress may have populated postmeta cache while the SQL
             * transaction was open. A rollback restores the database, so the
             * matching cache bucket must be discarded before another read.
             */
            $this->clearPostMetaCache($pageId);

            throw $exception;
        }
    }

    public function commitGlobal(int $expectedGeneration, callable $write): mixed
    {
        if ($expectedGeneration < 0) {
            throw new \InvalidArgumentException('A valid global generation is required.');
        }

        global $wpdb;
        $this->assertTransactionalTables([
            isset($wpdb->options) ? (string) $wpdb->options : (string) $wpdb->prefix . 'options',
            isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts',
            isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta',
            SchemaManager::globalSectionsTableName(),
        ]);
        $this->ensureGlobalGenerationRow();

        return $this->transaction(function () use ($expectedGeneration, $write): mixed {
            $this->lockGlobal();
            $current = $this->lockedGlobalGeneration();
            $this->assertCurrent('global', $expectedGeneration, $current);

            $result = $write();
            $this->writeOption(self::GLOBAL_OPTION_KEY, $expectedGeneration + 1);

            return $result;
        });
    }

    public function publishIfCurrent(SourceGenerationSnapshot $snapshot, callable $publish): mixed
    {
        global $wpdb;
        $this->assertTransactionalTables([
            isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts',
            isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta',
            isset($wpdb->options) ? (string) $wpdb->options : (string) $wpdb->prefix . 'options',
            SchemaManager::pageStateTableName(),
            SchemaManager::pageArtifactsTableName(),
            SchemaManager::pageSourceSnapshotsTableName(),
        ]);
        $this->ensureGlobalGenerationRow();

        try {
            return $this->transaction(function () use ($snapshot, $publish): mixed {
                /*
                 * Stable publication lock order: page, page-owned publication
                 * state, then global source. Draft page commits already serialize
                 * on the page before touching page state; global commits acquire
                 * only the global row. Keeping this order prevents the explicit
                 * pointer transaction from crossing either writer in reverse.
                 */
                $this->lockPage($snapshot->pageId());
                $this->lockPageState($snapshot->pageId());
                $this->lockGlobal();

                $this->assertCurrent(
                    'page',
                    $snapshot->pageGeneration(),
                    $this->lockedPageGeneration($snapshot->pageId()),
                );
                $this->assertCurrent(
                    'global',
                    $snapshot->globalGeneration(),
                    $this->lockedGlobalGeneration(),
                );

                return $publish();
            });
        } catch (\Throwable $exception) {
            /*
             * Publication adapters may read or write post meta while the SQL
             * transaction is open. Rollback restores the database, so evict
             * any uncommitted template or ownership value from object cache.
             */
            $this->clearPostMetaCache($snapshot->pageId());

            throw $exception;
        }
    }

    /**
     * @param callable(): mixed $operation
     */
    private function transaction(callable $operation): mixed
    {
        global $wpdb;

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('Failed to start a source generation transaction.');
        }

        try {
            $result = $operation();
            if ($wpdb->query('COMMIT') === false) {
                throw new \RuntimeException('Failed to commit a source generation transaction.');
            }

            return $result;
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    private function clearPostMetaCache(int $pageId): void
    {
        if (
            function_exists(__NAMESPACE__ . '\\wp_cache_delete')
            || function_exists('wp_cache_delete')
        ) {
            wp_cache_delete($pageId, 'post_meta');
        }
    }

    /**
     * Source generations are compare-and-swap guards only when every table in
     * the write boundary honors row locks and rollback. MySQL accepts START
     * TRANSACTION for MyISAM tables but silently commits their writes, so fail
     * before mutation instead of offering false concurrency safety.
     *
     * @param string[] $tables
     */
    private function assertTransactionalTables(array $tables): void
    {
        global $wpdb;

        foreach (array_values(array_unique($tables)) as $table) {
            if (isset($this->transactionalTables[$table])) {
                continue;
            }

            $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
            $engine = is_object($status) && isset($status->Engine) ? (string) $status->Engine : '';
            if (strcasecmp($engine, 'InnoDB') !== 0) {
                $reportedEngine = $engine !== '' ? $engine : 'unknown';
                throw new SourceTransactionsUnavailableException($table, $reportedEngine);
            }

            $this->transactionalTables[$table] = true;
        }
    }

    private function lockPage(int $pageId): void
    {
        global $wpdb;

        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $locked = $wpdb->query($wpdb->prepare(
            "SELECT ID FROM {$postsTable} WHERE ID = %d FOR UPDATE",
            $pageId,
        ));
        if ($locked === false || $locked < 1) {
            throw new \RuntimeException('Failed to lock the page source generation.');
        }
    }

    private function lockGlobal(): void
    {
        global $wpdb;

        $optionsTable = isset($wpdb->options) ? (string) $wpdb->options : (string) $wpdb->prefix . 'options';
        $locked = $wpdb->query($wpdb->prepare(
            "SELECT option_id FROM {$optionsTable} WHERE option_name = %s FOR UPDATE",
            self::GLOBAL_OPTION_KEY,
        ));
        if ($locked === false || $locked < 1) {
            throw new \RuntimeException('Failed to lock the global source generation.');
        }
    }

    private function lockPageState(int $pageId): void
    {
        global $wpdb;

        $locked = $wpdb->query($wpdb->prepare(
            'SELECT page_id FROM ' . SchemaManager::pageStateTableName() . '
             WHERE page_id = %d FOR UPDATE',
            $pageId,
        ));
        if ($locked === false || $locked < 1) {
            throw new \RuntimeException('Failed to lock Page Builder publication state.');
        }
    }

    private function lockedPageGeneration(int $pageId): int
    {
        global $wpdb;

        $postmetaTable = isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta';
        if (method_exists($wpdb, 'get_var')) {
            $stored = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$postmetaTable}
                 WHERE post_id = %d AND meta_key = %s
                 ORDER BY meta_id DESC LIMIT 1",
                $pageId,
                self::PAGE_META_KEY,
            ));
            if ($stored !== null && $stored !== false && $stored !== '') {
                return max(0, (int) $stored);
            }
        }

        return $this->pageGeneration($pageId);
    }

    private function lockedGlobalGeneration(): int
    {
        global $wpdb;

        $optionsTable = isset($wpdb->options) ? (string) $wpdb->options : (string) $wpdb->prefix . 'options';
        if (method_exists($wpdb, 'get_var')) {
            $stored = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$optionsTable} WHERE option_name = %s LIMIT 1",
                self::GLOBAL_OPTION_KEY,
            ));
            if ($stored !== null && $stored !== false && $stored !== '') {
                return max(0, (int) $stored);
            }
        }

        return $this->globalGeneration();
    }

    private function ensureGlobalGenerationRow(): void
    {
        $function = $this->wordpressFunction('add_option');
        if ($function !== null) {
            $function(self::GLOBAL_OPTION_KEY, 0, '', false);
        }
    }

    private function readPostMeta(int $postId, string $key): mixed
    {
        $function = $this->wordpressFunction('get_post_meta');

        return $function !== null ? $function($postId, $key, true) : 0;
    }

    private function writePostMeta(int $postId, string $key, int $value): void
    {
        $function = $this->wordpressFunction('update_post_meta');
        if ($function === null || $function($postId, $key, $value) === false) {
            throw new \RuntimeException('Failed to advance the page source generation.');
        }
    }

    private function readOption(string $key, mixed $default): mixed
    {
        $function = $this->wordpressFunction('get_option');

        return $function !== null ? $function($key, $default) : $default;
    }

    private function writeOption(string $key, int $value): void
    {
        $function = $this->wordpressFunction('update_option');
        if ($function === null || $function($key, $value, false) === false) {
            throw new \RuntimeException('Failed to advance the global source generation.');
        }
    }

    private function wordpressFunction(string $name): ?\Closure
    {
        $namespaced = __NAMESPACE__ . '\\' . $name;
        if (function_exists($namespaced)) {
            return \Closure::fromCallable($namespaced);
        }
        if (function_exists($name)) {
            return \Closure::fromCallable($name);
        }

        return null;
    }

    private function assertCurrent(string $scope, int $expected, int $current): void
    {
        if ($expected !== $current) {
            throw new StaleSourceGenerationException($scope, $expected, $current);
        }
    }
}
