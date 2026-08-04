<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\Navigation\NavigationLocation;
use UncannyPageBuilder\Domain\Navigation\NavigationMenu;
use UncannyPageBuilder\Domain\Navigation\NavigationMenuItem;
use UncannyPageBuilder\Domain\Navigation\NavigationMenuRepositoryInterface;

final class WordPressNavigationMenuRepository implements NavigationMenuRepositoryInterface
{
    // Read operations.
    public function listLocations(): array
    {
        $labels = $this->canCall('get_registered_nav_menus') ? get_registered_nav_menus() : [];
        $assigned = $this->canCall('get_nav_menu_locations') ? get_nav_menu_locations() : [];

        $locations = [];
        foreach ($labels as $slug => $label) {
            if (!is_string($slug)) {
                continue;
            }

            $locations[] = new NavigationLocation(
                slug: $slug,
                label: is_string($label) ? $label : '',
                assignedMenuId: (int) ($assigned[$slug] ?? 0),
            );
        }

        return $locations;
    }

    public function listMenus(): array
    {
        if (!$this->canCall('wp_get_nav_menus')) {
            return [];
        }

        $menus = wp_get_nav_menus();
        if (!is_array($menus)) {
            return [];
        }

        $result = [];
        foreach ($menus as $menu) {
            $hydrated = $this->hydrateMenu($menu);
            if ($hydrated !== null) {
                $result[] = $hydrated;
            }
        }

        return $result;
    }

    public function findMenuById(int $menuId): ?NavigationMenu
    {
        if ($menuId <= 0 || !$this->canCall('wp_get_nav_menus')) {
            return null;
        }

        foreach ($this->listMenus() as $menu) {
            if ($menu->id() === $menuId) {
                return $menu;
            }
        }

        return null;
    }

    // Write operations.
    public function createMenu(string $name): NavigationMenu
    {
        if (!$this->canCall('wp_create_nav_menu')) {
            throw new \RuntimeException('WordPress navigation menu creation API is unavailable.');
        }

        $menuId = wp_create_nav_menu($name);
        if ($this->isWpError($menuId) || (int) $menuId <= 0) {
            throw new \RuntimeException('Could not create the navigation menu.');
        }

        return $this->findMenuById((int) $menuId) ?? new NavigationMenu(
            id: (int) $menuId,
            name: $name,
            items: [],
        );
    }

    public function saveMenuItem(int $menuId, NavigationMenuItem $item): NavigationMenu
    {
        if (!$this->canCall('wp_update_nav_menu_item')) {
            throw new \RuntimeException('WordPress navigation menu item API is unavailable.');
        }

        $result = $this->writeMenuItem($menuId, $item);

        if ($this->isWpError($result) || (int) $result <= 0) {
            throw new \RuntimeException('Could not save the navigation menu item.');
        }

        $menu = $this->findMenuById($menuId);
        if ($menu === null) {
            throw new \RuntimeException('The navigation menu could not be reloaded after saving an item.');
        }

        return $menu;
    }

    /**
     * @param NavigationMenuItem[] $items
     */
    public function saveMenuTree(int $menuId, array $items): NavigationMenu
    {
        if (!$this->canCall('wp_update_nav_menu_item')) {
            throw new \RuntimeException('WordPress navigation menu item API is unavailable.');
        }

        $before = $this->findMenuById($menuId);
        if (!$before instanceof NavigationMenu) {
            throw new \RuntimeException('The navigation menu could not be loaded before saving the tree.');
        }

        $createdItemIds = [];

        try {
            foreach ($items as $item) {
                $result = $this->writeMenuItem($menuId, $item);

                if ($this->isWpError($result) || (int) $result <= 0) {
                    throw new \RuntimeException('Could not save the navigation menu tree.');
                }

                if ($item->id() <= 0) {
                    $createdItemIds[] = (int) $result;
                }
            }

            $menu = $this->findMenuById($menuId);
            if ($menu === null) {
                throw new \RuntimeException('The navigation menu could not be reloaded after saving the tree.');
            }

            return $menu;
        } catch (\Throwable $failure) {
            $rollbackFailures = $this->restoreMenuTree($menuId, $before, $createdItemIds);
            if ($rollbackFailures !== []) {
                throw new \RuntimeException(
                    'Could not save the navigation menu tree, and the previous menu could not be fully restored: '
                    . implode('; ', $rollbackFailures),
                    0,
                    $failure,
                );
            }

            throw $failure;
        }
    }

    public function deleteMenuItem(int $menuId, int $itemId): NavigationMenu
    {
        if (!$this->canCall('wp_delete_post')) {
            throw new \RuntimeException('WordPress navigation menu delete API is unavailable.');
        }

        $deleted = wp_delete_post($itemId, true);
        if ($deleted === false || $this->isWpError($deleted)) {
            throw new \RuntimeException('Could not delete the navigation menu item.');
        }

        $menu = $this->findMenuById($menuId);
        if ($menu === null) {
            throw new \RuntimeException('The navigation menu could not be reloaded after deleting an item.');
        }

        return $menu;
    }

    public function assignMenuToLocation(string $locationSlug, int $menuId): void
    {
        if (!$this->canCall('get_nav_menu_locations') || !$this->canCall('set_theme_mod')) {
            throw new \RuntimeException('WordPress navigation location APIs are unavailable.');
        }

        $locations = get_nav_menu_locations();
        if (!is_array($locations)) {
            $locations = [];
        }

        $locations[$locationSlug] = $menuId;

        set_theme_mod('nav_menu_locations', $locations);
    }

    private function hydrateMenu(mixed $menu): ?NavigationMenu
    {
        if (!is_object($menu) || !isset($menu->term_id)) {
            return null;
        }

        $menuId = (int) $menu->term_id;
        if ($menuId <= 0) {
            return null;
        }

        $items = $this->loadMenuItems($menuId);

        return new NavigationMenu(
            id: $menuId,
            name: isset($menu->name) ? (string) $menu->name : '',
            items: $items,
        );
    }

    /**
     * @return NavigationMenuItem[]
     */
    private function loadMenuItems(int $menuId): array
    {
        if (!$this->canCall('wp_get_nav_menu_items')) {
            return [];
        }

        $items = wp_get_nav_menu_items($menuId);
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_object($item) || !isset($item->ID)) {
                continue;
            }

            $classes = [];
            if (is_array($item->classes ?? null)) {
                $classes = array_values(array_filter(
                    array_map(static fn (mixed $value): string => trim((string) $value), $item->classes),
                    static fn (string $value): bool => $value !== '',
                ));
            }

            $result[] = new NavigationMenuItem(
                id: (int) $item->ID,
                label: isset($item->title) ? (string) $item->title : '',
                type: isset($item->type) ? (string) $item->type : '',
                objectType: isset($item->object) ? (string) $item->object : '',
                objectId: (int) ($item->object_id ?? 0),
                url: isset($item->url) ? (string) $item->url : '',
                parentId: (int) ($item->menu_item_parent ?? 0),
                position: (int) ($item->menu_order ?? 0),
                target: isset($item->target) ? (string) $item->target : '',
                classes: $classes,
            );
        }

        return $result;
    }

    /**
     * Persist one item through WordPress' navigation API.
     */
    private function writeMenuItem(int $menuId, NavigationMenuItem $item): mixed
    {
        return wp_update_nav_menu_item($menuId, max(0, $item->id()), [
            'menu-item-title' => $item->label(),
            'menu-item-type' => $item->type(),
            'menu-item-object' => $item->objectType(),
            'menu-item-object-id' => $item->objectId(),
            'menu-item-url' => $item->url(),
            'menu-item-parent-id' => $item->parentId(),
            'menu-item-position' => $item->position(),
            'menu-item-target' => $item->target(),
            'menu-item-classes' => implode(' ', $item->classes()),
            'menu-item-status' => 'publish',
        ]);
    }

    /**
     * Compensate for a failed multi-item write. WordPress does not expose a
     * transaction for menu APIs, so every pre-existing item is restored and
     * every item created by this attempt is removed before the error escapes.
     *
     * @param int[] $createdItemIds
     * @return string[] rollback failures that require operator attention
     */
    private function restoreMenuTree(int $menuId, NavigationMenu $before, array $createdItemIds): array
    {
        $failures = [];

        foreach (array_reverse($createdItemIds) as $createdItemId) {
            try {
                $deleted = $this->canCall('wp_delete_post')
                    ? wp_delete_post($createdItemId, true)
                    : false;

                if ($deleted === false || $this->isWpError($deleted)) {
                    $failures[] = "created item {$createdItemId} could not be removed";
                }
            } catch (\Throwable $rollbackFailure) {
                $failures[] = "created item {$createdItemId} could not be removed ({$rollbackFailure->getMessage()})";
            }
        }

        foreach ($before->items() as $item) {
            try {
                $restored = $this->writeMenuItem($menuId, $item);
                if ($this->isWpError($restored) || (int) $restored <= 0) {
                    $failures[] = "existing item {$item->id()} could not be restored";
                }
            } catch (\Throwable $rollbackFailure) {
                $failures[] = "existing item {$item->id()} could not be restored ({$rollbackFailure->getMessage()})";
            }
        }

        return $failures;
    }

    private function canCall(string $function): bool
    {
        return function_exists(__NAMESPACE__ . '\\' . $function) || function_exists($function);
    }

    private function isWpError(mixed $value): bool
    {
        if (\class_exists('\WP_Error') && $value instanceof \WP_Error) {
            return true;
        }

        return $this->canCall('is_wp_error') && is_wp_error($value);
    }
}
