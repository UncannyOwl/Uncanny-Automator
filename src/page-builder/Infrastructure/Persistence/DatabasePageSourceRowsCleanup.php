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

        $deletedOperations = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$operations} WHERE scope_type = %s AND scope_id = %d",
            'page',
            $pageId,
        ));
        if ($deletedOperations === false) {
            throw new \RuntimeException('Could not delete Page Builder operation history.');
        }

        $deletedSections = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$sections} WHERE page_id = %d",
            $pageId,
        ));
        if ($deletedSections === false) {
            throw new \RuntimeException('Could not delete Page Builder section source.');
        }
    }
}
