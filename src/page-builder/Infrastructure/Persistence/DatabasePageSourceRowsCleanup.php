<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Application\SourcePackage\PageSourceRowsCleanupInterface;

/**
 * Removes page-scoped source and operation history after permanent deletion.
 */
final class DatabasePageSourceRowsCleanup implements PageSourceRowsCleanupInterface
{
    public function deleteForPage(int $pageId): void
    {
        if ($pageId <= 0) {
            return;
        }

        global $wpdb;
        $operations = SchemaManager::operationsTableName();
        $sections = SchemaManager::tableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
        $deletedOperations = $wpdb->query($wpdb->prepare(
            'DELETE FROM %i WHERE scope_type = %s AND scope_id = %d',
            $operations,
            'page',
            $pageId,
        ));
        if ($deletedOperations === false) {
            throw new \RuntimeException('Could not delete Page Builder operation history.');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
        $deletedSections = $wpdb->query($wpdb->prepare(
            'DELETE FROM %i WHERE page_id = %d',
            $sections,
            $pageId,
        ));
        if ($deletedSections === false) {
            throw new \RuntimeException('Could not delete Page Builder section source.');
        }
    }
}
