<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

interface PageSourceSnapshotRepositoryInterface
{
    public function insert(PageSourceSnapshot $snapshot): PageSourceSnapshot;

    public function findForPage(int $pageId, int $snapshotId): ?PageSourceSnapshot;

    /**
     * Delete unreferenced history while retaining every snapshot selected by
     * page state or a retained immutable artifact.
     */
    public function pruneForPage(int $pageId): int;

    public function deleteForPage(int $pageId): int;
}
