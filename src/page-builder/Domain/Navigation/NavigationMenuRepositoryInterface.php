<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Navigation;

interface NavigationMenuRepositoryInterface
{
    // Read operations.
    /**
     * @return NavigationLocation[]
     */
    public function listLocations(): array;

    /**
     * @return NavigationMenu[]
     */
    public function listMenus(): array;

    public function findMenuById(int $menuId): ?NavigationMenu;

    // Write operations.
    public function createMenu(string $name): NavigationMenu;

    public function saveMenuItem(int $menuId, NavigationMenuItem $item): NavigationMenu;

    /**
     * @param NavigationMenuItem[] $items
     */
    public function saveMenuTree(int $menuId, array $items): NavigationMenu;

    public function deleteMenuItem(int $menuId, int $itemId): NavigationMenu;

    public function assignMenuToLocation(string $locationSlug, int $menuId): void;
}
