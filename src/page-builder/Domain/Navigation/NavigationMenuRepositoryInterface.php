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

    public function isValidItemReference(string $type, string $objectType, int $objectId): bool;

    // Write operations.
    public function createMenu(string $name): NavigationMenu;

    public function saveMenuItem(int $menuId, NavigationMenuItem $item): NavigationMenuItemSaveResult;

    /**
     * @param NavigationMenuItem[] $items
     * @param int[]|null $expectedItemIds
     */
    public function saveMenuTree(int $menuId, array $items, ?array $expectedItemIds = null): NavigationMenu;

    public function deleteMenuItem(int $menuId, int $itemId): NavigationMenu;

    public function assignMenuToLocation(string $locationSlug, int $menuId): int;
}
