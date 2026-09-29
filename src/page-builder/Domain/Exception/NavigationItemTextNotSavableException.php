<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

/**
 * WordPress would rewrite text on navigation items that the request did not
 * change, because the current user cannot save that markup. Nothing is saved.
 */
final class NavigationItemTextNotSavableException extends \RuntimeException
{
    /** @param int[] $itemIds */
    public function __construct(
        private readonly int $menuId,
        private readonly array $itemIds,
    ) {
        parent::__construct(sprintf(
            'Navigation menu %d has items whose text this account cannot save unchanged: %s.',
            $menuId,
            implode(', ', $itemIds),
        ));
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
}
