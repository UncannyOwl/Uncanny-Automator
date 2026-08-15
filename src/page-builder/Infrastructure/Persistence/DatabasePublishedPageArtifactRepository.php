<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\Publishing\PageArtifactStaticSafetyStatus;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifact;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifactRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Immutable storage for artifacts eligible to be selected by page state.
 */
final class DatabasePublishedPageArtifactRepository implements PublishedPageArtifactRepositoryInterface
{
    private const HISTORY_KEEP_ROWS = 20;

    /** @var array<string, true> */
    private array $transactionalTables = [];

    public function insert(PublishedPageArtifact $artifact): PublishedPageArtifact
    {
        if ($artifact->id() !== null) {
            throw new \InvalidArgumentException('A stored artifact cannot be inserted again.');
        }

        $this->ensureSchema();
        $this->assertTransactionalTables([SchemaManager::pageArtifactsTableName()]);
        $createdAt = $artifact->createdAt()?->format('Y-m-d H:i:s') ?? $this->now();

        global $wpdb;
        $inserted = $wpdb->insert(
            SchemaManager::pageArtifactsTableName(),
            [
                'page_id' => $artifact->pageId(),
                'artifact_version' => $artifact->artifactVersion(),
                'source_snapshot_id' => $artifact->sourceSnapshotId(),
                'source_revision_hash' => $artifact->sourceRevisionHash(),
                'content_hash' => $artifact->contentHash(),
                'dependency_hash' => $artifact->dependencyHash(),
                'dependencies_json' => $this->encodeAssociativeArray($artifact->dependencies()),
                'page_section_count' => $artifact->pageSectionCount(),
                'title' => $artifact->title(),
                'slug' => $artifact->slug(),
                'shell_mode' => $artifact->shellMode()->value,
                'html' => $artifact->html(),
                'css' => $artifact->css(),
                'custom_javascript' => $artifact->customJavaScript(),
                'assets_manifest_json' => $this->encodeAssociativeArray($artifact->assetsManifest()),
                'static_safety_status' => $artifact->staticSafetyStatus()->value,
                'static_safety_report_json' => $this->encodeList($artifact->staticSafetyReport()),
                'created_by' => $artifact->createdBy(),
                'created_at' => $createdAt,
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'],
        );

        if ($inserted === false || (int) $wpdb->insert_id <= 0) {
            throw new \RuntimeException('Failed to insert the immutable page artifact.');
        }

        return PublishedPageArtifact::hydrate(
            id: (int) $wpdb->insert_id,
            pageId: $artifact->pageId(),
            artifactVersion: $artifact->artifactVersion(),
            sourceRevisionHash: $artifact->sourceRevisionHash(),
            storedContentHash: $artifact->contentHash(),
            storedDependencyHash: $artifact->dependencyHash(),
            pageSectionCount: $artifact->pageSectionCount(),
            title: $artifact->title(),
            slug: $artifact->slug(),
            shellMode: $artifact->shellMode(),
            html: $artifact->html(),
            css: $artifact->css(),
            customJavaScript: $artifact->customJavaScript(),
            assetsManifest: $artifact->assetsManifest(),
            dependencies: $artifact->dependencies(),
            staticSafetyStatus: $artifact->staticSafetyStatus(),
            staticSafetyReport: $artifact->staticSafetyReport(),
            createdBy: $artifact->createdBy(),
            createdAt: new \DateTimeImmutable($createdAt),
            sourceSnapshotId: $artifact->sourceSnapshotId(),
        );
    }

    public function findForPage(int $pageId, int $artifactId): ?PublishedPageArtifact
    {
        if ($pageId <= 0 || $artifactId <= 0) {
            return null;
        }

        $this->ensureSchema();

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . SchemaManager::pageArtifactsTableName() . '
             WHERE page_id = %d AND id = %d
             LIMIT 1',
            $pageId,
            $artifactId,
        ));

        return is_object($row) ? $this->hydrate($row) : null;
    }

    public function pageIdForArtifact(int $artifactId): ?int
    {
        if ($artifactId <= 0) {
            return null;
        }

        $this->ensureSchema();

        global $wpdb;
        $pageId = $wpdb->get_var($wpdb->prepare(
            'SELECT page_id FROM ' . SchemaManager::pageArtifactsTableName() . '
             WHERE id = %d
             LIMIT 1',
            $artifactId,
        ));

        return $pageId !== null ? (int) $pageId : null;
    }

    public function historyForPage(int $pageId, int $limit = 20): array
    {
        if ($pageId <= 0 || $limit <= 0) {
            return [];
        }

        $this->ensureSchema();
        $limit = min($limit, 100);

        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . SchemaManager::pageArtifactsTableName() . '
             WHERE page_id = %d
             ORDER BY id DESC
             LIMIT %d',
            $pageId,
            $limit,
        ));

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn(object $row): PublishedPageArtifact => $this->hydrate($row), $rows);
    }

    public function pruneForPage(int $pageId): int
    {
        if ($pageId <= 0) {
            return 0;
        }

        $this->ensureSchema();
        $this->assertTransactionalTables([
            SchemaManager::pageStateTableName(),
            SchemaManager::pageArtifactsTableName(),
        ]);

        return $this->transaction(function () use ($pageId): int {
            $publishedArtifactId = $this->lockedPublishedArtifactId($pageId);

            global $wpdb;
            $cutoffId = $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . SchemaManager::pageArtifactsTableName() . '
                 WHERE page_id = %d
                 ORDER BY id DESC
                 LIMIT 1 OFFSET %d',
                $pageId,
                self::HISTORY_KEEP_ROWS - 1,
            ));
            if ($cutoffId === null || (int) $cutoffId <= 0) {
                return 0;
            }

            $pointerClause = $publishedArtifactId !== null ? ' AND id <> %d' : '';
            $args = [$pageId, (int) $cutoffId];
            if ($publishedArtifactId !== null) {
                $args[] = $publishedArtifactId;
            }

            $deleted = $wpdb->query($wpdb->prepare(
                'DELETE FROM ' . SchemaManager::pageArtifactsTableName() . '
                 WHERE page_id = %d AND id < %d' . $pointerClause,
                ...$args,
            ));
            if ($deleted === false) {
                throw new \RuntimeException('Failed to prune old immutable page artifacts.');
            }

            return (int) $deleted;
        });
    }

    public function deleteForPage(int $pageId): int
    {
        if ($pageId <= 0) {
            return 0;
        }

        $this->ensureSchema();
        $this->assertTransactionalTables([
            SchemaManager::pageStateTableName(),
            SchemaManager::pageArtifactsTableName(),
        ]);

        return $this->transaction(function () use ($pageId): int {
            if ($this->lockedPublishedArtifactId($pageId) !== null) {
                throw new \RuntimeException('Page artifacts cannot be deleted while page state points to one of them.');
            }

            global $wpdb;
            $deleted = $wpdb->delete(SchemaManager::pageArtifactsTableName(), ['page_id' => $pageId], ['%d']);
            if ($deleted === false) {
                throw new \RuntimeException('Failed to delete immutable page artifacts.');
            }

            return (int) $deleted;
        });
    }

    // Section: Hydration and row locks

    private function lockedPublishedArtifactId(int $pageId): ?int
    {
        global $wpdb;

        $artifactId = $wpdb->get_var($wpdb->prepare(
            'SELECT published_artifact_id FROM ' . SchemaManager::pageStateTableName() . '
             WHERE page_id = %d
             LIMIT 1 FOR UPDATE',
            $pageId,
        ));

        return $artifactId !== null && (int) $artifactId > 0 ? (int) $artifactId : null;
    }

    private function hydrate(object $row): PublishedPageArtifact
    {
        return PublishedPageArtifact::hydrate(
            id: (int) $row->id,
            pageId: (int) $row->page_id,
            artifactVersion: (int) $row->artifact_version,
            sourceRevisionHash: (string) $row->source_revision_hash,
            storedContentHash: (string) $row->content_hash,
            storedDependencyHash: (string) $row->dependency_hash,
            pageSectionCount: (int) $row->page_section_count,
            title: (string) $row->title,
            slug: (string) $row->slug,
            shellMode: ShellMode::from((string) $row->shell_mode),
            html: (string) $row->html,
            css: (string) $row->css,
            customJavaScript: (string) $row->custom_javascript,
            assetsManifest: $this->decodeAssociativeArray((string) $row->assets_manifest_json),
            dependencies: $this->decodeAssociativeArray((string) $row->dependencies_json),
            staticSafetyStatus: PageArtifactStaticSafetyStatus::fromStorage((string) $row->static_safety_status),
            staticSafetyReport: $this->decodeList((string) ($row->static_safety_report_json ?? '[]')),
            createdBy: (int) $row->created_by,
            createdAt: new \DateTimeImmutable((string) $row->created_at),
            sourceSnapshotId: isset($row->source_snapshot_id) && (int) $row->source_snapshot_id > 0
                ? (int) $row->source_snapshot_id
                : null,
        );
    }

    /** @param array<string, mixed> $records */
    private function encodeAssociativeArray(array $records): string
    {
        if ($records === []) {
            return '{}';
        }

        return self::encodeJson($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<int, array<string, mixed>> $records */
    private function encodeList(array $records): string
    {
        return self::encodeJson($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    private function decodeAssociativeArray(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) && ($decoded === [] || !array_is_list($decoded)) ? $decoded : [];
    }

    /** @return array<int, array<string, mixed>> */
    private function decodeList(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded) || !array_is_list($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    /** @param callable(): mixed $operation */
    private function transaction(callable $operation): mixed
    {
        global $wpdb;

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('Failed to start an artifact-retention transaction.');
        }

        try {
            $result = $operation();
            if ($wpdb->query('COMMIT') === false) {
                throw new \RuntimeException('Failed to commit an artifact-retention transaction.');
            }

            return $result;
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
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

    private function now(): string
    {
        return (string) current_time('mysql');
    }

    private function ensureSchema(): void
    {
        if (function_exists('get_option') && defined('ABSPATH')) {
            SchemaManager::ensureSchema();
        }
    }

    private static function encodeJson(mixed $value, int $flags = 0): string|false
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($value, $flags);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone persistence tests run without WordPress functions.
        return json_encode($value, $flags);
    }
}
