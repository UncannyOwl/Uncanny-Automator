<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

/**
 * A navigation write failed and was undone, except for items that another
 * writer changed while it ran. Those items keep their current values, which
 * can include a position from the failed request, so other items may not
 * return to their exact earlier order.
 */
final class NavigationChangedConcurrentlyException extends \RuntimeException
{
    /**
     * @param int[] $itemIds items changed by another writer and kept
     * @param int[] $unrestoredPositionItemIds items whose earlier position could not be restored
     */
    public function __construct(
        private readonly int $menuId,
        private readonly array $itemIds,
        private readonly array $unrestoredPositionItemIds = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Navigation menu %d items changed concurrently and were preserved: %s.',
                $menuId,
                implode(', ', $itemIds),
            ),
            0,
            $previous,
        );
    }

    public function menuId(): int
    {
        return $this->menuId;
    }

    /** @return int[] */
    public function itemIds(): array
    {
        return $this->itemIds;
    }

    /** @return int[] */
    public function unrestoredPositionItemIds(): array
    {
        return $this->unrestoredPositionItemIds;
    }
}
