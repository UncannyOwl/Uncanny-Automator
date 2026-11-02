<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\ArrayShape;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshot;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface;

/**
 * Immutable storage for editable source captured by publication.
 */
final class DatabasePageSourceSnapshotRepository implements PageSourceSnapshotRepositoryInterface
{
    public function insert(PageSourceSnapshot $snapshot): PageSourceSnapshot
    {
        if ($snapshot->id() !== null) {
            throw new \InvalidArgumentException('A stored page source snapshot cannot be inserted again.');
        }

        $this->ensureSchema();
        $createdAt = $snapshot->createdAt()?->format('Y-m-d H:i:s') ?? $this->now();

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write.
        $inserted = $wpdb->insert(
            SchemaManager::pageSourceSnapshotsTableName(),
            [
                'page_id' => $snapshot->pageId(),
                'snapshot_version' => $snapshot->snapshotVersion(),
                'source_revision_hash' => $snapshot->sourceRevisionHash(),
                'source_content_hash' => $snapshot->sourceContentHash(),
                'page_generation' => $snapshot->pageGeneration(),
                'source_json' => self::encodeJson(
                    $snapshot->source(),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ),
                'created_by' => $snapshot->createdBy(),
                'created_at' => $createdAt,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s'],
        );
        if ($inserted === false || (int) $wpdb->insert_id <= 0) {
            throw new \RuntimeException('Failed to insert the immutable page source snapshot.');
        }

        return PageSourceSnapshot::hydrate(
            id: (int) $wpdb->insert_id,
            pageId: $snapshot->pageId(),
            snapshotVersion: $snapshot->snapshotVersion(),
            sourceRevisionHash: $snapshot->sourceRevisionHash(),
            pageGeneration: $snapshot->pageGeneration(),
            source: $snapshot->source(),
            createdBy: $snapshot->createdBy(),
            createdAt: new \DateTimeImmutable($createdAt),
            storedSourceContentHash: $snapshot->sourceContentHash(),
        );
    }

    public function findForPage(int $pageId, int $snapshotId): ?PageSourceSnapshot
    {
        if ($pageId <= 0 || $snapshotId <= 0) {
            return null;
        }

        $this->ensureSchema();

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM %i
             WHERE page_id = %d AND id = %d
             LIMIT 1',
            SchemaManager::pageSourceSnapshotsTableName(),
            $pageId,
            $snapshotId,
        ));

        return is_object($row) ? $this->hydrate($row) : null;
    }

    public function pruneForPage(int $pageId): int
    {
        if ($pageId <= 0) {
            return 0;
        }

        $this->ensureSchema();

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
        $deleted = $wpdb->query($wpdb->prepare(
            'DELETE snapshots FROM %i AS snapshots
             LEFT JOIN %i AS state
               ON state.page_id = snapshots.page_id
              AND state.published_source_snapshot_id = snapshots.id
             LEFT JOIN %i AS artifacts
               ON artifacts.page_id = snapshots.page_id
              AND artifacts.source_snapshot_id = snapshots.id
             WHERE snapshots.page_id = %d
               AND state.page_id IS NULL
               AND artifacts.id IS NULL',
            SchemaManager::pageSourceSnapshotsTableName(),
            SchemaManager::pageStateTableName(),
            SchemaManager::pageArtifactsTableName(),
            $pageId,
        ));
        if ($deleted === false) {
            throw new \RuntimeException('Failed to prune old page source snapshots.');
        }

        return (int) $deleted;
    }

    public function deleteForPage(int $pageId): int
    {
        if ($pageId <= 0) {
            return 0;
        }

        $this->ensureSchema();

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
        $deleted = $wpdb->delete(
            SchemaManager::pageSourceSnapshotsTableName(),
            ['page_id' => $pageId],
            ['%d'],
        );
        if ($deleted === false) {
            throw new \RuntimeException('Failed to delete page source snapshots.');
        }

        return (int) $deleted;
    }

    private function hydrate(object $row): PageSourceSnapshot
    {
        try {
            $source = json_decode((string) $row->source_json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $invalidSnapshot = new \RuntimeException('Stored page source snapshot JSON is invalid.', 0, $exception);
            throw $invalidSnapshot;
        }
        if (!is_array($source) || ArrayShape::isList($source)) {
            throw new \RuntimeException('Stored page source snapshot must be an object.');
        }

        return PageSourceSnapshot::hydrate(
            id: (int) $row->id,
            pageId: (int) $row->page_id,
            snapshotVersion: (int) $row->snapshot_version,
            sourceRevisionHash: (string) $row->source_revision_hash,
            pageGeneration: (int) $row->page_generation,
            source: $source,
            createdBy: (int) $row->created_by,
            createdAt: new \DateTimeImmutable((string) $row->created_at),
            storedSourceContentHash: property_exists($row, 'source_content_hash')
                ? (string) $row->source_content_hash
                : null,
        );
    }

    private function now(): string
    {
        return function_exists('current_time')
            ? (string) current_time('mysql')
            : gmdate('Y-m-d H:i:s');
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
