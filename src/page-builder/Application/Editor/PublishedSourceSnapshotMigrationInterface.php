<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editor;

/**
 * Lazily creates the first trustworthy editable source for a legacy artifact.
 */
interface PublishedSourceSnapshotMigrationInterface
{
    /**
     * Returns true only when this call safely linked a new snapshot.
     */
    public function migrateIfSafe(int $pageId): bool;
}
