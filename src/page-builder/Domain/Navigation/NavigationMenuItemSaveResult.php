<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Navigation;

final class NavigationMenuItemSaveResult
{
    public function __construct(
        private readonly NavigationMenu $menu,
        private readonly int $itemId,
    ) {}

    public function menu(): NavigationMenu
    {
        return $this->menu;
    }

    public function itemId(): int
    {
        return $this->itemId;
    }
}
