<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

/**
 * Queues derived working-canvas refreshes without touching publication state.
 *
 * Shared source uses one global generation, so every owned page is a valid
 * refresh candidate after a shared change. The queue is operational recovery
 * only: it cannot build a publication artifact or move a published pointer.
 */
final class WorkingCanvasRefreshScheduler
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly OwnedPageFinderInterface $ownedPages,
        private readonly WorkingCanvasRefreshQueueInterface $queue,
    ) {}

    public function enqueueAll(): int
    {
        $queued = 0;
        $afterPageId = 0;

        do {
            $pageIds = array_values(array_unique(array_filter(
                array_map('intval', $this->ownedPages->ownedPageIds(self::BATCH_SIZE, $afterPageId)),
                static fn(int $pageId): bool => $pageId > 0,
            )));
            $this->enqueuePages($pageIds);
            $queued += count($pageIds);

            foreach ($pageIds as $pageId) {
                $afterPageId = max($afterPageId, $pageId);
            }
        } while (count($pageIds) === self::BATCH_SIZE);

        return $queued;
    }

    /** @param int[] $pageIds */
    public function enqueuePages(array $pageIds): void
    {
        $pageIds = array_values(array_unique(array_filter(
            array_map('intval', $pageIds),
            static fn(int $pageId): bool => $pageId > 0,
        )));
        if ($pageIds !== []) {
            $this->queue->enqueuePages($pageIds);
        }
    }
}
