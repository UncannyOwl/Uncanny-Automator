<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\Navigation\NavigationLocation;
use UncannyPageBuilder\Domain\Navigation\NavigationMenu;
use UncannyPageBuilder\Domain\Navigation\NavigationMenuItem;
use UncannyPageBuilder\Domain\Navigation\NavigationMenuItemSaveResult;
use UncannyPageBuilder\Domain\Navigation\NavigationMenuRepositoryInterface;
use UncannyPageBuilder\Domain\Exception\NavigationChangedConcurrentlyException;
use UncannyPageBuilder\Domain\Exception\NavigationItemTextNotSavableException;
use UncannyPageBuilder\Domain\Exception\StaleNavigationMenuException;

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

    public function isValidItemReference(string $type, string $objectType, int $objectId): bool
    {
        if ($objectType === '' || $objectId <= 0) {
            return false;
        }

        if ($type === 'post_type') {
            if (!$this->canCall('get_post_type_object') || !$this->canCall('get_post')) {
                return false;
            }

            $registeredType = get_post_type_object($objectType);
            $post = get_post($objectId);

            return is_object($registeredType)
                && is_object($post)
                && (int) ($post->ID ?? 0) === $objectId
                && (string) ($post->post_type ?? '') === $objectType
                && (string) ($post->post_status ?? '') !== 'trash';
        }

        if ($type === 'taxonomy') {
            if (!$this->canCall('taxonomy_exists') || !$this->canCall('get_term') || !taxonomy_exists($objectType)) {
                return false;
            }

            $term = get_term($objectId, $objectType);

            return !$this->isWpError($term)
                && is_object($term)
                && (int) ($term->term_id ?? 0) === $objectId
                && (string) ($term->taxonomy ?? '') === $objectType;
        }

        return false;
    }

    // Write operations.
    public function createMenu(string $name): NavigationMenu
    {
        if (!$this->canCall('wp_create_nav_menu')) {
            throw new \RuntimeException('WordPress navigation menu creation API is unavailable.');
        }

        $menuId = wp_create_nav_menu(WordPressSlashing::slash($name));
        if ($this->isWpError($menuId) || (int) $menuId <= 0) {
            throw new \RuntimeException('Could not create the navigation menu.');
        }

        $menu = $this->mutationMenu((int) $menuId);
        if (!$menu instanceof NavigationMenu) {
            throw new \RuntimeException('The navigation menu could not be reloaded after creation.');
        }

        return $menu;
    }

    public function saveMenuItem(int $menuId, NavigationMenuItem $item): NavigationMenuItemSaveResult
    {
        if (!$this->canCall('wp_update_nav_menu_item')) {
            throw new \RuntimeException('WordPress navigation menu item API is unavailable.');
        }

        $creationMarker = '';
        $createdItemId = 0;
        $createdItem = null;
        $before = $this->mutationMenu($menuId);
        if (!$before instanceof NavigationMenu) {
            throw new \RuntimeException('The navigation menu could not be loaded before saving an item.');
        }

        $beforeItem = null;
        $attemptedItems = [];
        if ($item->id() <= 0) {
            $creationMarker = 'uncanny-pb-write-' . bin2hex(random_bytes(16));
            $wordPressPosition = 0;
        } else {
            $beforeItem = $before->findItemById($item->id());
            if (!$beforeItem instanceof NavigationMenuItem) {
                throw new \RuntimeException('The navigation menu item could not be loaded before saving it.');
            }
            $this->assertUntouchedTextSurvives($menuId, $before, [$item]);
            $storedOrder = $this->storedMenuOrder($item->id());

            // WordPress orders siblings by the menu-wide menu_order. An item
            // that keeps its old order under a new parent lands at an unrelated
            // sibling position. Move it after every stored item instead, so it
            // is the last child of its new parent without rewriting siblings.
            $wordPressPosition = $beforeItem->parentId() === $item->parentId()
                ? $storedOrder
                : $this->lastMenuOrder($menuId, $before) + 1;
            $attemptedItems[$item->id()] = [
                'before' => $beforeItem,
                'target' => $this->storedFormOf($item),
                'wordPressPosition' => $storedOrder,
            ];
        }

        try {
            $result = $item->id() <= 0
                ? $this->writeNewMenuItemWithMarker($menuId, $item, $creationMarker, $wordPressPosition)
                : $this->writeMenuItem($menuId, $item, $wordPressPosition);

            if ($this->isWpError($result) || (int) $result <= 0) {
                throw new \RuntimeException('Could not save the navigation menu item.');
            }
            $createdItemId = $creationMarker !== '' ? (int) $result : 0;

            $menu = $this->mutationMenu($menuId);
            if ($menu === null) {
                throw new \RuntimeException('The navigation menu could not be reloaded after saving an item.');
            }

            $expected = $this->storedFormOf($this->copyMenuItem($item, (int) $result, $item->classes()));
            $saved = $menu->findItemById((int) $result);
            if ($createdItemId > 0 && $saved instanceof NavigationMenuItem) {
                $createdItem = $saved;
            }
            if ($beforeItem instanceof NavigationMenuItem && $saved instanceof NavigationMenuItem) {
                $attemptedItems[$item->id()]['target'] = $saved;
            }
            if (!$saved instanceof NavigationMenuItem || !$this->menuItemsMatch($expected, $saved)) {
                throw new \RuntimeException('Could not confirm the saved navigation menu item.');
            }

            if ($creationMarker !== '') {
                $this->clearCreationMarker((int) $result, $creationMarker);

                // Confirm the public item again after the private marker is
                // gone. This closes the small interval between verification
                // and marker cleanup without trusting a cached post object.
                $menu = $this->mutationMenu($menuId);
                $saved = $menu?->findItemById((int) $result);
                if (!$saved instanceof NavigationMenuItem || !$this->menuItemsMatch($expected, $saved)) {
                    throw new \RuntimeException('Could not confirm the saved navigation menu item.');
                }
            }

            return new NavigationMenuItemSaveResult($menu, (int) $result);
        } catch (\Throwable $failure) {
            if ($attemptedItems !== []) {
                // An existing item has no creation marker. Restore its earlier
                // stored value only while it still holds this write.
                $preservedItemIds = [];
                $unrestoredPositionItemIds = [];
                $rollbackFailures = $this->restoreMenuTree(
                    $menuId,
                    [],
                    [],
                    [],
                    $attemptedItems,
                    $preservedItemIds,
                    $unrestoredPositionItemIds,
                );
                $this->throwIfOnlyConcurrentChangesRemain(
                    $menuId,
                    $rollbackFailures,
                    $preservedItemIds,
                    $unrestoredPositionItemIds,
                    $failure,
                );
                if ($rollbackFailures !== []) {
                    throw new \RuntimeException(
                        'Could not save the navigation menu item, and its previous value could not be fully restored: '
                        . implode('; ', $rollbackFailures),
                        0,
                        $failure,
                    );
                }

                throw $failure;
            }
            if ($creationMarker === '') {
                throw $failure;
            }

            $cleanupFailures = [];
            try {
                $createdItemIds = $this->findMarkedCreationIds($before, $creationMarker);
            } catch (\Throwable $discoveryFailure) {
                $createdItemIds = [];
                $cleanupFailures[] = 'new navigation item could not be inspected (' . $discoveryFailure->getMessage() . ')';
            }

            if ($createdItemId > 0 && !in_array($createdItemId, $createdItemIds, true)) {
                try {
                    $currentMenu = $this->mutationMenu($menuId);
                    $currentItem = $currentMenu?->findItemById($createdItemId);
                    if (
                        $currentItem instanceof NavigationMenuItem
                        && $createdItem instanceof NavigationMenuItem
                        && $this->menuItemsMatch($createdItem, $currentItem)
                    ) {
                        $createdItemIds[] = $createdItemId;
                    } elseif ($currentItem instanceof NavigationMenuItem) {
                        $cleanupFailures[] = "new navigation item {$createdItemId} changed concurrently and was preserved";
                    }
                } catch (\Throwable $inspectionFailure) {
                    $cleanupFailures[] = "new navigation item {$createdItemId} could not be inspected ("
                        . $inspectionFailure->getMessage() . ')';
                }
            }
            $cleanupFailures = [...$cleanupFailures, ...$this->removeCreatedMenuItems($createdItemIds)];
            if ($cleanupFailures !== []) {
                throw new \RuntimeException(
                    'Could not save the navigation menu item, and its incomplete creation could not be fully removed: '
                    . implode('; ', $cleanupFailures),
                    0,
                    $failure,
                );
            }

            throw $failure;
        }
    }

    /**
     * @param NavigationMenuItem[] $items
     * @param int[]|null $expectedItemIds
     */
    public function saveMenuTree(int $menuId, array $items, ?array $expectedItemIds = null): NavigationMenu
    {
        if (!$this->canCall('wp_update_nav_menu_item')) {
            throw new \RuntimeException('WordPress navigation menu item API is unavailable.');
        }

        $before = $this->mutationMenu($menuId);
        if (!$before instanceof NavigationMenu) {
            throw new \RuntimeException('The navigation menu could not be loaded before saving the tree.');
        }
        if (
            $expectedItemIds !== null
            && $this->normalizedItemIds($expectedItemIds) !== $this->normalizedItemIds(array_map(
                static fn (NavigationMenuItem $item): int => $item->id(),
                $before->items(),
            ))
        ) {
            throw new StaleNavigationMenuException($menuId);
        }
        $this->assertUntouchedTextSurvives($menuId, $before, $items);

        $createdItemIds = [];
        $createdItems = [];
        $expectedItems = [];
        $attemptedItems = [];
        $creationMarker = 'uncanny-pb-write-' . bin2hex(random_bytes(16));
        $wordPressPositions = $this->wordPressTreePositions($items);

        try {
            foreach ($items as $item) {
                if ($item->id() > 0) {
                    $beforeItem = $before->findItemById($item->id());
                    if ($beforeItem instanceof NavigationMenuItem) {
                        $attemptedItems[$item->id()] = [
                            'before' => $beforeItem,
                            'target' => $this->storedFormOf($item),
                            'wordPressPosition' => $this->storedMenuOrder($item->id()),
                        ];
                    }
                }

                $wordPressPosition = $wordPressPositions[spl_object_id($item)];
                $result = $item->id() <= 0
                    ? $this->writeNewMenuItemWithMarker($menuId, $item, $creationMarker, $wordPressPosition)
                    : $this->writeMenuItem($menuId, $item, $wordPressPosition);

                if ($this->isWpError($result) || (int) $result <= 0) {
                    throw new \RuntimeException('Could not save the navigation menu tree.');
                }

                $writtenMenu = $this->mutationMenu($menuId);
                $writtenItem = $writtenMenu?->findItemById((int) $result);

                // A later write in this tree moves earlier siblings. Keep each
                // rollback target at the state this operation last left, so a
                // failed tree restores items it moved instead of preserving
                // them as concurrent changes.
                // Adopt a reread only when it differs from what this operation
                // wrote by sibling position alone. Any other difference is a
                // concurrent change, which rollback must preserve and report.
                foreach ($attemptedItems as $attemptedId => $attempt) {
                    $current = $writtenMenu?->findItemById($attemptedId);
                    if (
                        $current instanceof NavigationMenuItem
                        && $this->menuItemsMatchExceptPosition($attempt['target'], $current)
                    ) {
                        $attemptedItems[$attemptedId]['target'] = $current;
                    }
                }
                foreach ($createdItems as $createdId => $created) {
                    $current = $writtenMenu?->findItemById($createdId);
                    if (
                        $current instanceof NavigationMenuItem
                        && $this->menuItemsMatchExceptPosition($created, $current)
                    ) {
                        $createdItems[$createdId] = $current;
                    }
                }
                if ($item->id() <= 0) {
                    $createdItemId = (int) $result;
                    $createdItemIds[] = $createdItemId;
                    $expectedItem = $this->storedFormOf($this->copyMenuItem($item, $createdItemId, $item->classes()));
                    $createdItem = $writtenItem instanceof NavigationMenuItem
                        ? $writtenItem
                        : $expectedItem;
                    $createdItems[$createdItemId] = $createdItem;
                    $expectedItems[] = $expectedItem;
                } else {
                    if ($writtenItem instanceof NavigationMenuItem) {
                        $attemptedItems[$item->id()]['target'] = $writtenItem;
                    }
                    $expectedItems[] = $this->storedFormOf($item);
                }
            }

            $menu = $this->mutationMenu($menuId);
            if ($menu === null) {
                throw new \RuntimeException('The navigation menu could not be reloaded after saving the tree.');
            }
            if (!$this->menuTreeMatches($expectedItems, $menu)) {
                throw new \RuntimeException('Could not confirm the saved navigation menu tree.');
            }

            foreach ($createdItems as $createdItem) {
                $this->clearCreationMarker($createdItem->id(), $creationMarker);
            }

            if ($createdItems !== []) {
                $menu = $this->mutationMenu($menuId);
                if ($menu === null || !$this->menuTreeMatches($expectedItems, $menu)) {
                    throw new \RuntimeException('Could not confirm the saved navigation menu tree.');
                }
            }

            return $menu;
        } catch (\Throwable $failure) {
            $discoveryFailures = [];
            $markedCreationIds = [];
            try {
                $markedCreationIds = $this->findMarkedCreationIds($before, $creationMarker);
                $createdItemIds = array_values(array_unique([
                    ...$createdItemIds,
                    ...$markedCreationIds,
                ]));
            } catch (\Throwable $discoveryFailure) {
                $discoveryFailures[] = 'new navigation items could not be inspected (' . $discoveryFailure->getMessage() . ')';
            }
            $preservedItemIds = [];
            $unrestoredPositionItemIds = [];
            $rollbackFailures = $this->restoreMenuTree(
                $menuId,
                $createdItemIds,
                $createdItems,
                $markedCreationIds,
                $attemptedItems,
                $preservedItemIds,
                $unrestoredPositionItemIds,
            );
            $rollbackFailures = [...$discoveryFailures, ...$rollbackFailures];
            $this->throwIfOnlyConcurrentChangesRemain(
                $menuId,
                $rollbackFailures,
                $preservedItemIds,
                $unrestoredPositionItemIds,
                $failure,
            );
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

        $deleteFailure = null;
        try {
            $deleted = wp_delete_post($itemId, true);
        } catch (\Throwable $failure) {
            $deleted = null;
            $deleteFailure = $failure;
        }

        try {
            $remaining = $this->storedPostExists($itemId);
        } catch (\Throwable $inspectionFailure) {
            throw new \RuntimeException(
                'Could not inspect the navigation menu item after deletion.',
                0,
                $deleteFailure ?? $inspectionFailure,
            );
        }
        if ($remaining) {
            if ($deleted === false || $this->isWpError($deleted)) {
                throw new \RuntimeException('Could not delete the navigation menu item.', 0, $deleteFailure);
            }

            throw new \RuntimeException('Could not confirm deletion of the navigation menu item post.', 0, $deleteFailure);
        }

        $menu = $this->mutationMenu($menuId);
        if ($menu === null) {
            throw new \RuntimeException('The navigation menu could not be reloaded after deleting an item.');
        }
        if ($menu->findItemById($itemId) !== null) {
            throw new \RuntimeException('Could not confirm deletion of the navigation menu item.');
        }

        return $menu;
    }

    public function assignMenuToLocation(string $locationSlug, int $menuId): int
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

        $savedLocations = get_nav_menu_locations();

        return is_array($savedLocations) ? (int) ($savedLocations[$locationSlug] ?? 0) : 0;
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

        $loaded = [];
        foreach ($items as $item) {
            if (!is_object($item) || !isset($item->ID)) {
                continue;
            }

            $type = isset($item->type) ? (string) $item->type : '';
            $hasRawPostFields = property_exists($item, 'post_title')
                && property_exists($item, 'post_content')
                && property_exists($item, 'post_excerpt');
            $rawLabel = $hasRawPostFields ? (string) $item->post_title : (string) ($item->title ?? '');
            $label = $rawLabel;
            if ($rawLabel === '' && $type !== 'custom') {
                // A synced item stores no title. Report the raw linked title,
                // not the display filtered one, so writing the item back keeps
                // it synced instead of saving a fixed, texturized label.
                $label = $this->originalLinkedTitle(
                    $type,
                    isset($item->object) ? (string) $item->object : '',
                    (int) ($item->object_id ?? 0),
                ) ?? (string) ($item->title ?? '');
            }
            $description = $hasRawPostFields
                ? (string) $item->post_content
                : (string) ($item->description ?? '');
            if ($type !== 'custom' && $description === ' ') {
                $description = '';
            }
            $classes = [];
            $storedClasses = $hasRawPostFields && $this->canCall('get_post_meta')
                ? get_post_meta((int) $item->ID, '_menu_item_classes', true)
                : ($item->classes ?? null);
            if (is_array($storedClasses)) {
                $classes = array_values(array_filter(
                    array_map(static fn (mixed $value): string => trim((string) $value), $storedClasses),
                    static fn (string $value): bool => $value !== '',
                ));
            }
            $storedXfn = $hasRawPostFields && $this->canCall('get_post_meta')
                ? get_post_meta((int) $item->ID, '_menu_item_xfn', true)
                : ($item->xfn ?? '');

            $loaded[] = new NavigationMenuItem(
                id: (int) $item->ID,
                label: $label,
                type: $type,
                objectType: isset($item->object) ? (string) $item->object : '',
                objectId: $type === 'custom' ? 0 : (int) ($item->object_id ?? 0),
                url: isset($item->url) ? (string) $item->url : '',
                parentId: (int) ($item->menu_item_parent ?? 0),
                position: (int) ($item->menu_order ?? 0),
                target: isset($item->target) ? (string) $item->target : '',
                classes: $classes,
                description: $description,
                titleAttribute: $hasRawPostFields ? (string) $item->post_excerpt : (string) ($item->attr_title ?? ''),
                xfn: is_scalar($storedXfn) ? (string) $storedXfn : '',
            );
        }

        return $this->normalizeSiblingPositions($loaded);
    }

    /**
     * Read the durable menu state for a write decision.
     *
     * Normal menu reads must use the WordPress APIs. Those APIs apply theme
     * and plugin filters that are part of the visitor-facing presentation.
     * A mutation cannot use that filtered view as proof that a write worked.
     * A filter can hide a saved item, expose a stale cached value, or create a
     * synthetic value that was never stored.
     *
     * This method therefore reads the Page Builder write boundary directly
     * from the WordPress tables. Its use is deliberately limited to mutation
     * preconditions, write verification, and compensation. It must not replace
     * the normal filtered read path used to present a menu.
     *
     * The direct read also gives one coherent source for the item post, menu
     * relationship, and menu-item metadata. Page Builder can then compare the
     * requested change with the values that a later request will actually use.
     *
     * Security: every value is bound through wpdb::prepare(). The table names
     * come only from WordPress' wpdb object. This method does not accept a raw
     * table name, SQL fragment, or metadata key from the request. Bypassing
     * filters does not bypass authentication or capability checks; those gates
     * run before the repository receives a mutation.
     *
     * Performance: one bounded query loads the items and the eight metadata
     * keys that this contract compares. The query is limited by the menu term
     * relationship and uses WordPress' post, relationship, and metadata index
     * paths. Linked post or term labels need a small direct lookup only when
     * WordPress stored an empty item title. This cost is limited to mutation
     * verification and is not paid by visitor rendering.
     */
    private function mutationMenu(int $menuId): ?NavigationMenu
    {
        // Real WordPress requests must fail closed when durable storage is not
        // available. The fallback exists only for isolated unit tests, where
        // the namespace stubs model WordPress without loading wpdb.
        $database = $this->navigationDatabase();
        if ($database === null) {
            if (defined('ABSPATH')) {
                throw new \RuntimeException('The durable navigation storage API is unavailable.');
            }

            return $this->findMenuById($menuId);
        }

        // Confirm the exact nav_menu term before loading any items. A missing
        // term means that the requested menu does not exist in durable state,
        // even if a WordPress filter could manufacture a menu object for it.
        $nameQuery = $database->prepare(
            "SELECT t.name
            FROM {$database->terms} t
            INNER JOIN {$database->term_taxonomy} tt ON tt.term_id = t.term_id
            WHERE tt.term_id = %d AND tt.taxonomy = %s
            LIMIT 1",
            $menuId,
            'nav_menu',
        );
        $name = is_string($nameQuery) ? $database->get_var($nameQuery) : null;
        if (!is_scalar($name) || (string) $name === '') {
            return null;
        }

        // Load the menu relationship and all compared metadata in one query.
        // WordPress reads the earliest meta_id when duplicate rows exist. The
        // NOT EXISTS condition selects that same row. MAX(CASE...) then turns
        // each selected single value into a column without calling the
        // filterable get_post_meta() API. Unknown keys remain outside this
        // write contract and do not affect verification.
        $itemsQuery = $database->prepare(
            "SELECT
                p.ID,
                p.post_title,
                p.post_content,
                p.post_excerpt,
                p.menu_order,
                p.post_content_filtered,
                MAX(CASE WHEN pm.meta_key = '_menu_item_type' THEN pm.meta_value END) AS item_type,
                MAX(CASE WHEN pm.meta_key = '_menu_item_object' THEN pm.meta_value END) AS item_object,
                MAX(CASE WHEN pm.meta_key = '_menu_item_object_id' THEN pm.meta_value END) AS item_object_id,
                MAX(CASE WHEN pm.meta_key = '_menu_item_menu_item_parent' THEN pm.meta_value END) AS item_parent_id,
                MAX(CASE WHEN pm.meta_key = '_menu_item_target' THEN pm.meta_value END) AS item_target,
                MAX(CASE WHEN pm.meta_key = '_menu_item_classes' THEN pm.meta_value END) AS item_classes,
                MAX(CASE WHEN pm.meta_key = '_menu_item_xfn' THEN pm.meta_value END) AS item_xfn,
                MAX(CASE WHEN pm.meta_key = '_menu_item_url' THEN pm.meta_value END) AS item_url
            FROM {$database->posts} p
            INNER JOIN {$database->term_relationships} tr ON tr.object_id = p.ID
            INNER JOIN {$database->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$database->postmeta} pm
                ON pm.post_id = p.ID
                AND pm.meta_key IN (
                    '_menu_item_type',
                    '_menu_item_object',
                    '_menu_item_object_id',
                    '_menu_item_menu_item_parent',
                    '_menu_item_target',
                    '_menu_item_classes',
                    '_menu_item_xfn',
                    '_menu_item_url'
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$database->postmeta} earlier_pm
                    WHERE earlier_pm.post_id = pm.post_id
                        AND earlier_pm.meta_key = pm.meta_key
                        AND earlier_pm.meta_id < pm.meta_id
                )
            WHERE tt.term_id = %d
                AND tt.taxonomy = %s
                AND p.post_type = %s
                AND p.post_status = %s
            GROUP BY
                p.ID,
                p.post_title,
                p.post_content,
                p.post_excerpt,
                p.menu_order,
                p.post_content_filtered
            ORDER BY p.menu_order ASC, p.ID ASC",
            $menuId,
            'nav_menu',
            'nav_menu_item',
            'publish',
        );
        $rows = is_string($itemsQuery) ? $database->get_results($itemsQuery) : null;
        if (!is_array($rows)) {
            throw new \RuntimeException('The durable navigation item read failed.');
        }
        $rows = array_values(array_filter(
            $rows,
            static fn (mixed $row): bool => is_object($row) && (int) ($row->ID ?? 0) > 0,
        ));
        $linked = $this->linkedObjects($database, $rows);

        // Rebuild the same domain object used by the normal adapter. This keeps
        // comparison rules in one language while changing only the source of
        // evidence from a filtered view to stored rows.
        $items = [];
        foreach ($rows as $row) {
            $type = (string) ($row->item_type ?? '');
            $objectType = (string) ($row->item_object ?? '');
            $objectId = $type === 'custom' ? 0 : (int) ($row->item_object_id ?? 0);
            $label = (string) ($row->post_title ?? '');

            // A REST reader gets the menu from wp_get_nav_menu_items(), which
            // returns published items and, outside wp-admin, removes items whose
            // linked object is gone. Writes decide against that same item set,
            // rebuilt here from stored rows instead of the filtered read.
            if (!$this->isVisibleMenuItem($type, $objectType, $objectId, $linked)) {
                continue;
            }

            // WordPress may store an empty navigation title for a post or term.
            // In that case the visible label is derived from the linked object,
            // so read that source row directly instead of accepting a filtered
            // menu-item title as persistence evidence.
            if ($label === '' && $type !== 'custom') {
                $label = $this->linkedObjectLabel($type, $objectType, $objectId, $linked);
            }
            $description = (string) ($row->post_content ?? '');

            // Core can store one space as the empty description sentinel for a
            // linked item. Normalize it to the public contract's empty string.
            if ($type !== 'custom' && $description === ' ') {
                $description = '';
            }

            $items[] = new NavigationMenuItem(
                id: (int) $row->ID,
                label: $label,
                type: $type,
                objectType: $objectType,
                objectId: $objectId,
                // A custom URL is owned by the menu-item metadata and must match
                // exactly. WordPress derives linked-object URLs at read time, so
                // they are presentation values and are not persistence proof.
                url: $type === 'custom'
                    ? (string) ($row->item_url ?? '')
                    : $this->linkedObjectUrl($type, $objectType, $objectId),
                parentId: (int) ($row->item_parent_id ?? 0),
                position: (int) ($row->menu_order ?? 0),
                target: (string) ($row->item_target ?? ''),
                classes: $this->storedClasses($row->item_classes ?? ''),
                description: $description,
                titleAttribute: (string) ($row->post_excerpt ?? ''),
                xfn: (string) ($row->item_xfn ?? ''),
            );
        }

        // WordPress stores menu_order as one global sequence. The Page Builder
        // contract exposes a 1-based position within each sibling group. Apply
        // that translation before the mutation code compares expected state.
        return new NavigationMenu(
            id: $menuId,
            name: (string) $name,
            items: $this->normalizeSiblingPositions($items),
        );
    }

    /** @return NavigationMenuItem[] */
    private function normalizeSiblingPositions(array $loaded): array
    {
        usort($loaded, static function (NavigationMenuItem $left, NavigationMenuItem $right): int {
            $position = $left->position() <=> $right->position();
            if ($position !== 0) {
                return $position;
            }

            return $left->id() <=> $right->id();
        });

        $positions = [];
        $result = [];
        foreach ($loaded as $item) {
            $parentId = $item->parentId();
            $positions[$parentId] = ($positions[$parentId] ?? 0) + 1;
            $result[] = $this->copyMenuItemWithPosition($item, $positions[$parentId]);
        }

        return $result;
    }

    private function navigationDatabase(): ?object
    {
        $database = $GLOBALS['wpdb'] ?? null;
        if (!is_object($database)) {
            return null;
        }

        foreach (['posts', 'postmeta', 'terms', 'term_relationships', 'term_taxonomy'] as $tableProperty) {
            if (!is_string($database->{$tableProperty} ?? null) || $database->{$tableProperty} === '') {
                return null;
            }
        }
        foreach (['prepare', 'get_var', 'get_results', 'get_col', 'update'] as $method) {
            if (!is_callable([$database, $method])) {
                return null;
            }
        }

        return $database;
    }

    /**
     * Load every linked post and term for one menu read in two bounded
     * queries, instead of one query per item.
     *
     * @param object[] $rows
     * @return array{posts: array<int, array{title: string, status: string}>, terms: array<string, string>}
     */
    private function linkedObjects(object $database, array $rows): array
    {
        $postIds = [];
        $termIds = [];
        foreach ($rows as $row) {
            $objectId = (int) ($row->item_object_id ?? 0);
            if ($objectId <= 0) {
                continue;
            }

            $type = (string) ($row->item_type ?? '');
            if ($type === 'post_type') {
                $postIds[$objectId] = $objectId;
            } elseif ($type === 'taxonomy') {
                $termIds[$objectId] = $objectId;
            }
        }

        $linked = ['posts' => [], 'terms' => []];
        if ($postIds !== []) {
            $query = $database->prepare(
                "SELECT ID, post_title, post_status FROM {$database->posts}
                WHERE ID IN (" . implode(', ', array_fill(0, count($postIds), '%d')) . ')',
                ...array_values($postIds),
            );
            $posts = is_string($query) ? $database->get_results($query) : null;
            if (!is_array($posts)) {
                throw new \RuntimeException('The durable navigation link read failed.');
            }
            foreach ($posts as $post) {
                if (is_object($post)) {
                    $linked['posts'][(int) ($post->ID ?? 0)] = [
                        'title' => (string) ($post->post_title ?? ''),
                        'status' => (string) ($post->post_status ?? ''),
                    ];
                }
            }
        }
        if ($termIds !== []) {
            $query = $database->prepare(
                "SELECT tt.term_id, tt.taxonomy, t.name
                FROM {$database->terms} t
                INNER JOIN {$database->term_taxonomy} tt ON tt.term_id = t.term_id
                WHERE tt.term_id IN (" . implode(', ', array_fill(0, count($termIds), '%d')) . ')',
                ...array_values($termIds),
            );
            $terms = is_string($query) ? $database->get_results($query) : null;
            if (!is_array($terms)) {
                throw new \RuntimeException('The durable navigation link read failed.');
            }
            foreach ($terms as $term) {
                if (is_object($term)) {
                    $linked['terms'][(string) ($term->taxonomy ?? '') . ':' . (int) ($term->term_id ?? 0)]
                        = (string) ($term->name ?? '');
                }
            }
        }

        // Linked URLs come from WordPress' permalink API. Load their objects
        // into the cache once so each URL does not start another query.
        $this->primeLinkedObjectCaches(array_values($postIds), array_values($termIds));

        return $linked;
    }

    /**
     * @param int[] $postIds
     * @param int[] $termIds
     */
    private function primeLinkedObjectCaches(array $postIds, array $termIds): void
    {
        try {
            if ($postIds !== [] && $this->canCall('_prime_post_caches')) {
                _prime_post_caches($postIds, false, false);
            }
            if ($termIds !== [] && $this->canCall('_prime_term_caches')) {
                _prime_term_caches($termIds, false);
            }
        } catch (\Throwable) {
            // Priming only saves queries. URL reads still work without it.
        }
    }

    /**
     * Mirror the item set of wp_get_nav_menu_items() from stored rows. Core
     * marks an item invalid when its post type, taxonomy, post, or term is
     * missing, or when its post is in the trash. Core removes invalid items
     * only outside wp-admin, so this rule applies only there too.
     *
     * @param array{posts: array<int, array{title: string, status: string}>, terms: array<string, string>} $linked
     */
    private function isVisibleMenuItem(string $type, string $objectType, int $objectId, array $linked): bool
    {
        if ($this->canCall('is_admin') && is_admin()) {
            return true;
        }

        if ($type === 'post_type') {
            $post = $linked['posts'][$objectId] ?? null;

            return $this->canCall('post_type_exists')
                && post_type_exists($objectType)
                && $post !== null
                && $post['status'] !== 'trash';
        }
        if ($type === 'post_type_archive') {
            return $this->canCall('post_type_exists') && post_type_exists($objectType);
        }
        if ($type === 'taxonomy') {
            return $this->canCall('taxonomy_exists')
                && taxonomy_exists($objectType)
                && array_key_exists($objectType . ':' . $objectId, $linked['terms']);
        }

        return true;
    }

    /**
     * @param array{posts: array<int, array{title: string, status: string}>, terms: array<string, string>} $linked
     */
    private function linkedObjectLabel(string $type, string $objectType, int $objectId, array $linked): string
    {
        if ($type === 'post_type') {
            return $linked['posts'][$objectId]['title'] ?? '';
        }
        if ($type === 'taxonomy') {
            return $linked['terms'][$objectType . ':' . $objectId] ?? '';
        }
        if ($type === 'post_type_archive') {
            return $this->originalLinkedTitle($type, $objectType, $objectId) ?? '';
        }

        return '';
    }

    /**
     * The raw title that wp_update_nav_menu_item() compares with a requested
     * label. When they are equal, core stores an empty title and the item
     * follows later renames of its linked object.
     */
    private function originalLinkedTitle(string $type, string $objectType, int $objectId): ?string
    {
        try {
            if ($type === 'taxonomy' && $this->canCall('get_term') && $this->canCall('get_term_field')) {
                $term = get_term($objectId, $objectType);
                if (!is_object($term) || $this->isWpError($term)) {
                    return null;
                }
                $name = get_term_field('name', $objectId, $objectType, 'raw');

                return is_string($name) ? $name : null;
            }
            if ($type === 'post_type' && $this->canCall('get_post')) {
                $post = get_post($objectId);

                return is_object($post) ? (string) ($post->post_title ?? '') : null;
            }
            if ($type === 'post_type_archive' && $this->canCall('get_post_type_object')) {
                $postType = get_post_type_object($objectType);
                $archives = is_object($postType) ? ($postType->labels->archives ?? null) : null;

                return is_string($archives) ? $archives : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function linkedObjectUrl(string $type, string $objectType, int $objectId): string
    {
        try {
            if ($type === 'post_type' && $this->canCall('get_permalink')) {
                $url = get_permalink($objectId);

                return is_string($url) ? $url : '';
            }
            if ($type === 'taxonomy' && $this->canCall('get_term_link')) {
                $url = get_term_link($objectId, $objectType);

                return !$this->isWpError($url) && is_string($url) ? $url : '';
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    /** @return string[] */
    private function storedClasses(mixed $stored): array
    {
        if (is_string($stored)) {
            // Never instantiate objects from stored metadata (PHP Object Injection).
            try {
                $stored = $this->canCall('is_serialized') && is_serialized($stored)
                    ? @unserialize(trim($stored), ['allowed_classes' => false])
                    : $stored;
            } catch (\Throwable) {
                return [];
            }
        }
        if (!is_array($stored)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $stored),
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * Persist one item through WordPress' navigation API.
     *
     * Page Builder positions are local to one sibling group. WordPress stores
     * one menu-wide sequence, so callers must pass the translated position.
     */
    private function writeMenuItem(
        int $menuId,
        NavigationMenuItem $item,
        int $wordPressPosition,
    ): mixed {
        return wp_update_nav_menu_item($menuId, max(0, $item->id()), [
            'menu-item-title' => WordPressSlashing::slash($item->label()),
            'menu-item-type' => $item->type(),
            'menu-item-object' => $item->objectType(),
            'menu-item-object-id' => $item->objectId(),
            'menu-item-url' => $item->url(),
            'menu-item-parent-id' => $item->parentId(),
            'menu-item-position' => max(0, $wordPressPosition),
            'menu-item-target' => $item->target(),
            'menu-item-classes' => implode(' ', $item->classes()),
            'menu-item-description' => WordPressSlashing::slash($item->description()),
            'menu-item-attr-title' => WordPressSlashing::slash($item->titleAttribute()),
            'menu-item-xfn' => $item->xfn(),
            'menu-item-status' => 'publish',
        ]);
    }

    private function writeNewMenuItemWithMarker(
        int $menuId,
        NavigationMenuItem $item,
        string $marker,
        int $wordPressPosition,
    ): mixed {
        if (!$this->canCall('add_filter') || !$this->canCall('remove_filter')) {
            throw new \RuntimeException('WordPress navigation item recovery hooks are unavailable.');
        }

        $active = true;
        $markInsert = static function ($data = null) use (&$active, $marker) {
            try {
                if ($active && is_array($data) && (string) ($data['post_type'] ?? '') === 'nav_menu_item') {
                    $data['post_content_filtered'] = $marker;
                    $active = false;
                }
            } catch (\Throwable) {
                // A hostile filter value must pass through unchanged.
            }

            return $data;
        };

        add_filter('wp_insert_post_data', $markInsert, PHP_INT_MAX, 4);
        try {
            return $this->writeMenuItem($menuId, $item, $wordPressPosition);
        } finally {
            $active = false;
            try {
                remove_filter('wp_insert_post_data', $markInsert, PHP_INT_MAX);
            } catch (\Throwable) {
                // The deactivated callback cannot mark another insert.
            }
        }
    }

    private function clearCreationMarker(int $itemId, string $marker): void
    {
        $database = $this->navigationDatabase();
        if ($database !== null) {
            if ($this->storedCreationMarker($database, $itemId) !== $marker) {
                throw new \RuntimeException('Could not confirm the navigation write marker.');
            }

            $updated = $database->update(
                $database->posts,
                ['post_content_filtered' => ''],
                ['ID' => $itemId, 'post_content_filtered' => $marker],
                ['%s'],
                ['%d', '%s'],
            );
            if ((int) $updated !== 1) {
                throw new \RuntimeException('Could not remove the navigation write marker.');
            }

            // clean_post_cache() is intentionally a no-op while WordPress bulk
            // cache invalidation is suspended. Delete the exact post-object
            // cache entry so a later wp_update_post() cannot write the stale
            // private marker back to durable storage.
            if (!$this->canCall('wp_cache_delete')) {
                throw new \RuntimeException('WordPress post cache invalidation is unavailable.');
            }
            try {
                wp_cache_delete($itemId, 'posts');
            } catch (\Throwable $failure) {
                throw new \RuntimeException('Could not invalidate the navigation item cache.', 0, $failure);
            }

            if ($this->storedCreationMarker($database, $itemId) !== '') {
                throw new \RuntimeException('Could not confirm removal of the navigation write marker.');
            }

            return;
        }
        if (defined('ABSPATH')) {
            throw new \RuntimeException('The durable navigation storage API is unavailable.');
        }

        if (!$this->canCall('get_post') || !$this->canCall('wp_update_post')) {
            throw new \RuntimeException('WordPress navigation item marker APIs are unavailable.');
        }

        $post = get_post($itemId);
        if (!is_object($post) || (string) ($post->post_content_filtered ?? '') !== $marker) {
            throw new \RuntimeException('Could not confirm the navigation write marker.');
        }

        $updated = wp_update_post([
            'ID' => $itemId,
            'post_content_filtered' => '',
        ], true);
        if ($this->isWpError($updated) || (int) $updated !== $itemId) {
            throw new \RuntimeException('Could not remove the navigation write marker.');
        }

        $saved = get_post($itemId);
        if (!is_object($saved) || (string) ($saved->post_content_filtered ?? '') !== '') {
            throw new \RuntimeException('Could not confirm removal of the navigation write marker.');
        }
    }

    /**
     * Find only new items marked by this save attempt. Concurrent writers do
     * not know the random marker and must not enter this operation's cleanup.
     *
     * @return int[]
     */
    private function findMarkedCreationIds(NavigationMenu $before, string $marker): array
    {
        $existingIds = [];
        foreach ($before->items() as $item) {
            $existingIds[$item->id()] = true;
        }

        $database = $this->navigationDatabase();
        if ($database !== null) {
            $query = $database->prepare(
                "SELECT ID
                FROM {$database->posts}
                WHERE post_type = %s AND post_content_filtered = %s
                ORDER BY ID DESC",
                'nav_menu_item',
                $marker,
            );
            $createdIds = is_string($query) ? $database->get_col($query) : null;
            if (!is_array($createdIds)) {
                throw new \RuntimeException('The durable navigation item discovery failed.');
            }

            return array_values(array_filter(
                array_map('intval', $createdIds),
                static fn (int $itemId): bool => $itemId > 0 && !isset($existingIds[$itemId]),
            ));
        }
        if (defined('ABSPATH')) {
            throw new \RuntimeException('The durable navigation storage API is unavailable.');
        }

        if (!$this->canCall('get_posts')) {
            throw new \RuntimeException('WordPress navigation item discovery API is unavailable.');
        }

        $markedPosts = get_posts([
            'post_type' => 'nav_menu_item',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'DESC',
            'suppress_filters' => true,
        ]);
        if (!is_array($markedPosts)) {
            throw new \RuntimeException('WordPress returned an invalid navigation item discovery result.');
        }

        $createdIds = [];
        foreach ($markedPosts as $post) {
            if (!is_object($post)) {
                continue;
            }

            $postId = (int) ($post->ID ?? 0);
            if (
                $postId > 0
                && !isset($existingIds[$postId])
                && (string) ($post->post_type ?? '') === 'nav_menu_item'
                && (string) ($post->post_content_filtered ?? '') === $marker
            ) {
                $createdIds[] = $postId;
            }
        }

        return $createdIds;
    }

    /**
     * @param string[] $classes
     */
    private function copyMenuItem(NavigationMenuItem $item, int $itemId, array $classes): NavigationMenuItem
    {
        return new NavigationMenuItem(
            id: $itemId,
            label: $item->label(),
            type: $item->type(),
            objectType: $item->objectType(),
            objectId: $item->objectId(),
            url: $item->url(),
            parentId: $item->parentId(),
            position: $item->position(),
            target: $item->target(),
            classes: $classes,
            description: $item->description(),
            titleAttribute: $item->titleAttribute(),
            xfn: $item->xfn(),
        );
    }

    /**
     * Return the item as WordPress stores it. wp_insert_post() runs the post
     * text fields through their save filters, which include kses for a user
     * without unfiltered_html, and core stores a custom URL through
     * sanitize_url(). Verification compares against this stored form, so a
     * valid write is not reported as a failure.
     */
    private function storedFormOf(NavigationMenuItem $item): NavigationMenuItem
    {
        $label = $this->storedPostText('post_title', $item->label());
        if ($item->type() !== 'custom') {
            // WordPress stores an empty title when the label is empty or equals
            // the linked object's raw title. The item then reads that title.
            $original = $this->originalLinkedTitle($item->type(), $item->objectType(), $item->objectId());
            if ($item->label() === '' || $item->label() === $original) {
                $label = $original ?? $item->label();
            }
        }

        return new NavigationMenuItem(
            id: $item->id(),
            label: $label,
            type: $item->type(),
            objectType: $item->objectType(),
            objectId: $item->objectId(),
            url: $item->type() === 'custom' ? $this->storedCustomUrl($item->url()) : $item->url(),
            parentId: $item->parentId(),
            position: $item->position(),
            target: $item->target(),
            classes: $item->classes(),
            description: $this->storedPostText('post_content', $item->description()),
            titleAttribute: $this->storedPostText('post_excerpt', $item->titleAttribute()),
            xfn: $item->xfn(),
        );
    }

    /**
     * Refuse a write before it starts when WordPress would rewrite text that
     * the request leaves unchanged. That happens when the current user cannot
     * save markup already stored on an item, such as icon markup in a label.
     * Surrounding whitespace is ignored because WordPress trims it on every
     * save.
     *
     * @param NavigationMenuItem[] $items
     */
    private function assertUntouchedTextSurvives(int $menuId, NavigationMenu $before, array $items): void
    {
        $itemIds = [];
        foreach ($items as $item) {
            $beforeItem = $item->id() > 0 ? $before->findItemById($item->id()) : null;
            if (!$beforeItem instanceof NavigationMenuItem) {
                continue;
            }

            $stored = $this->storedFormOf($item);
            $pairs = [
                [$beforeItem->label(), $item->label(), $stored->label()],
                [$beforeItem->description(), $item->description(), $stored->description()],
                [$beforeItem->titleAttribute(), $item->titleAttribute(), $stored->titleAttribute()],
            ];
            foreach ($pairs as [$previous, $requested, $saved]) {
                if ($requested === $previous && $this->visibleText($saved) !== $this->visibleText($requested)) {
                    $itemIds[] = $item->id();
                    break;
                }
            }
        }

        if ($itemIds !== []) {
            throw new NavigationItemTextNotSavableException($menuId, $itemIds);
        }
    }

    /**
     * Text as a visitor reads it. kses encodes a bare ampersand as &amp;,
     * which displays the same, so only a change that survives entity decoding
     * counts as rewritten text.
     */
    private function visibleText(string $value): string
    {
        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function storedPostText(string $field, string $value): string
    {
        if ($value === '' || !$this->canCall('sanitize_post_field') || !$this->canCall('wp_unslash')) {
            return $value;
        }

        try {
            // wp_insert_post() sanitizes slashed input in the db context, then
            // unslashes it. Apply the same sequence to the requested value.
            $stored = wp_unslash(sanitize_post_field($field, WordPressSlashing::slash($value), 0, 'db'));
        } catch (\Throwable) {
            return $value;
        }

        return is_string($stored) ? $stored : $value;
    }

    private function storedCustomUrl(string $url): string
    {
        if (!$this->canCall('sanitize_url')) {
            return $url;
        }

        try {
            $stored = sanitize_url(trim($url));
        } catch (\Throwable) {
            return $url;
        }

        return is_string($stored) ? $stored : $url;
    }

    private function copyMenuItemWithPosition(NavigationMenuItem $item, int $position): NavigationMenuItem
    {
        return new NavigationMenuItem(
            id: $item->id(),
            label: $item->label(),
            type: $item->type(),
            objectType: $item->objectType(),
            objectId: $item->objectId(),
            url: $item->url(),
            parentId: $item->parentId(),
            position: $position,
            target: $item->target(),
            classes: $item->classes(),
            description: $item->description(),
            titleAttribute: $item->titleAttribute(),
            xfn: $item->xfn(),
        );
    }

    /**
     * @param NavigationMenuItem[] $expectedItems
     */
    private function menuTreeMatches(array $expectedItems, NavigationMenu $savedMenu): bool
    {
        if (count($expectedItems) !== count($savedMenu->items())) {
            return false;
        }

        foreach ($expectedItems as $expectedItem) {
            $savedItem = $savedMenu->findItemById($expectedItem->id());
            if (!$savedItem instanceof NavigationMenuItem || !$this->menuItemsMatch($expectedItem, $savedItem)) {
                return false;
            }
        }

        return true;
    }

    private function menuItemsMatchExceptPosition(NavigationMenuItem $expected, NavigationMenuItem $saved): bool
    {
        return $this->menuItemsMatch(
            $this->copyMenuItemWithPosition($expected, 0),
            $this->copyMenuItemWithPosition($saved, 0),
        );
    }

    private function menuItemsMatch(NavigationMenuItem $expected, NavigationMenuItem $saved): bool
    {
        return $expected->id() === $saved->id()
            && $expected->label() === $saved->label()
            && $expected->type() === $saved->type()
            && $expected->objectType() === $saved->objectType()
            && $expected->objectId() === $saved->objectId()
            && ($expected->type() !== 'custom' || $expected->url() === $saved->url())
            && $expected->parentId() === $saved->parentId()
            && $expected->position() === $saved->position()
            && $this->canonicalTarget($expected->target()) === $saved->target()
            && $this->canonicalClasses($expected->classes()) === $saved->classes()
            && $expected->description() === $saved->description()
            && $expected->titleAttribute() === $saved->titleAttribute()
            && $this->canonicalXfn($expected->xfn()) === $saved->xfn();
    }

    private function canonicalTarget(string $target): string
    {
        if ($this->canCall('sanitize_key')) {
            try {
                $sanitized = sanitize_key($target);
                if (is_string($sanitized)) {
                    return $sanitized;
                }
            } catch (\Throwable) {
                // Use the WordPress default rule when a third-party filter fails.
            }
        }

        return strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $target));
    }

    /**
     * @param string[] $classes
     * @return string[]
     */
    private function canonicalClasses(array $classes): array
    {
        return array_values(array_filter(
            array_map(fn (string $class): string => $this->canonicalHtmlClass($class), $classes),
            static fn (string $class): bool => $class !== '',
        ));
    }

    private function canonicalXfn(string $xfn): string
    {
        return implode(' ', array_map(
            fn (string $value): string => $this->canonicalHtmlClass($value),
            explode(' ', $xfn),
        ));
    }

    private function canonicalHtmlClass(string $value): string
    {
        if ($this->canCall('sanitize_html_class')) {
            try {
                $sanitized = sanitize_html_class($value);
                if (is_string($sanitized)) {
                    return $sanitized;
                }
            } catch (\Throwable) {
                // Use the WordPress default rule when a third-party filter fails.
            }
        }

        $value = (string) preg_replace('/%[a-fA-F0-9]{2}/', '', $value);

        return (string) preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    }

    /**
     * @param int[] $itemIds
     * @return int[]
     */
    private function normalizedItemIds(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(
            array_map('intval', $itemIds),
            static fn (int $itemId): bool => $itemId > 0,
        )));
        sort($itemIds, SORT_NUMERIC);

        return $itemIds;
    }

    /**
     * The highest stored menu_order in the menu, including draft and invalid
     * items that the visible menu omits, so a new last position sorts after
     * every stored item.
     */
    private function lastMenuOrder(int $menuId, NavigationMenu $visibleMenu): int
    {
        $database = $this->navigationDatabase();
        if ($database === null) {
            if (defined('ABSPATH')) {
                throw new \RuntimeException('The durable navigation storage API is unavailable.');
            }

            return array_reduce(
                $visibleMenu->items(),
                fn (int $last, NavigationMenuItem $item): int => max($last, $this->storedMenuOrder($item->id())),
                0,
            );
        }

        $query = $database->prepare(
            "SELECT MAX(p.menu_order)
            FROM {$database->posts} p
            INNER JOIN {$database->term_relationships} tr ON tr.object_id = p.ID
            INNER JOIN {$database->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.term_id = %d AND tt.taxonomy = %s AND p.post_type = %s",
            $menuId,
            'nav_menu',
            'nav_menu_item',
        );
        $last = is_string($query) ? $database->get_var($query) : null;
        if ($last !== null && !is_numeric($last)) {
            throw new \RuntimeException('The durable navigation order read failed.');
        }

        return (int) $last;
    }

    private function storedMenuOrder(int $itemId): int
    {
        if ($itemId <= 0 || !$this->canCall('get_post')) {
            throw new \RuntimeException('The stored navigation menu position is unavailable.');
        }

        try {
            $stored = get_post($itemId);
        } catch (\Throwable $failure) {
            throw new \RuntimeException(
                'The stored navigation menu position could not be read.',
                0,
                $failure,
            );
        }

        $position = is_object($stored) ? (int) ($stored->menu_order ?? 0) : 0;
        if ($position <= 0) {
            throw new \RuntimeException('The stored navigation menu position is unavailable.');
        }

        return $position;
    }

    /**
     * Translate sibling positions into unique WordPress menu-wide positions.
     *
     * @param NavigationMenuItem[] $items
     * @return array<int, int> positions keyed by object ID
     */
    private function wordPressTreePositions(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$item->parentId()][] = $item;
        }
        ksort($groups, SORT_NUMERIC);

        $positions = [];
        $position = 0;
        foreach ($groups as $siblings) {
            usort($siblings, static function (NavigationMenuItem $left, NavigationMenuItem $right): int {
                $siblingPosition = $left->position() <=> $right->position();
                if ($siblingPosition !== 0) {
                    return $siblingPosition;
                }

                return $left->id() <=> $right->id();
            });

            foreach ($siblings as $item) {
                $positions[spl_object_id($item)] = ++$position;
            }
        }

        return $positions;
    }

    /**
     * Compensate for a failed multi-item write. Restore or remove a value only
     * when it still matches this operation. Preserve a concurrent value.
     *
     * @param int[] $createdItemIds
     * @param array<int, NavigationMenuItem> $createdItems
     * @param int[] $markedCreationIds
     * @param array<int, array{before: NavigationMenuItem, target: NavigationMenuItem, wordPressPosition: int}> $attemptedItems
     * @return string[] rollback failures that require operator attention
     */
    private function restoreMenuTree(
        int $menuId,
        array $createdItemIds,
        array $createdItems,
        array $markedCreationIds,
        array $attemptedItems,
        array &$preservedItemIds = [],
        array &$unrestoredPositionItemIds = [],
    ): array {
        $failures = [];
        $markedCreationIds = array_fill_keys($markedCreationIds, true);
        $safeCreatedItemIds = [];
        $current = $this->mutationMenu($menuId);
        if (!$current instanceof NavigationMenu) {
            $failures[] = 'the navigation menu could not be inspected during rollback';
        }

        foreach ($createdItemIds as $createdItemId) {
            if (isset($markedCreationIds[$createdItemId])) {
                $safeCreatedItemIds[] = $createdItemId;
                continue;
            }

            $currentItem = $current?->findItemById($createdItemId);
            if (!$currentItem instanceof NavigationMenuItem) {
                continue;
            }

            $createdItem = $createdItems[$createdItemId] ?? null;
            if (
                $createdItem instanceof NavigationMenuItem
                && $this->menuItemsMatch($createdItem, $currentItem)
            ) {
                $safeCreatedItemIds[] = $createdItemId;
                continue;
            }

            $failures[] = "created item {$createdItemId} changed concurrently and was preserved";
        }

        $failures = [...$failures, ...$this->removeCreatedMenuItems($safeCreatedItemIds)];

        // Restoring one item shifts the sibling positions of others. Decide and
        // confirm each item on everything except its sibling position, then
        // rewrite its original stored order and check the exact result once.
        $restoredItemIds = [];
        foreach ($attemptedItems as $itemId => $attempt) {
            $current = $this->mutationMenu($menuId);
            $currentItem = $current?->findItemById($itemId);
            if (!$currentItem instanceof NavigationMenuItem) {
                $failures[] = "existing item {$itemId} could not be inspected during rollback";
                continue;
            }
            try {
                $orderRestored = $this->storedMenuOrder($itemId) === $attempt['wordPressPosition'];
            } catch (\Throwable) {
                $orderRestored = false;
            }
            if ($orderRestored && $this->menuItemsMatch($attempt['before'], $currentItem)) {
                $restoredItemIds[] = $itemId;
                continue;
            }
            if (
                !$this->menuItemsMatchExceptPosition($attempt['before'], $currentItem)
                && !$this->menuItemsMatchExceptPosition($attempt['target'], $currentItem)
            ) {
                $failures[] = "existing item {$itemId} changed concurrently and was preserved";
                $preservedItemIds[] = $itemId;
                continue;
            }

            try {
                $restored = $this->writeMenuItem(
                    $menuId,
                    $attempt['before'],
                    $attempt['wordPressPosition'],
                );
                if ($this->isWpError($restored) || (int) $restored <= 0) {
                    $failures[] = "existing item {$itemId} could not be restored";
                    continue;
                }

                $restoredMenu = $this->mutationMenu($menuId);
                $restoredItem = $restoredMenu?->findItemById($itemId);
                if (
                    !$restoredItem instanceof NavigationMenuItem
                    || !$this->menuItemsMatchExceptPosition($attempt['before'], $restoredItem)
                ) {
                    $failures[] = "existing item {$itemId} restoration could not be confirmed";
                    continue;
                }
                $restoredItemIds[] = $itemId;
            } catch (\Throwable $rollbackFailure) {
                $failures[] = "existing item {$itemId} could not be restored ({$rollbackFailure->getMessage()})";
            }
        }

        if ($restoredItemIds !== []) {
            $finalMenu = $this->mutationMenu($menuId);
            foreach ($restoredItemIds as $itemId) {
                $finalItem = $finalMenu?->findItemById($itemId);
                if (
                    !$finalItem instanceof NavigationMenuItem
                    || !$this->menuItemsMatch($attemptedItems[$itemId]['before'], $finalItem)
                ) {
                    $failures[] = "existing item {$itemId} position could not be restored";
                    $unrestoredPositionItemIds[] = $itemId;
                }
            }
        }

        return $failures;
    }

    /**
     * Report a definite result when rollback undid this request except for
     * existing items that another writer changed while it ran. Any other
     * rollback failure leaves the result uncertain and is reported as such.
     *
     * @param string[] $rollbackFailures
     * @param int[] $preservedItemIds
     * @param int[] $unrestoredPositionItemIds
     */
    private function throwIfOnlyConcurrentChangesRemain(
        int $menuId,
        array $rollbackFailures,
        array $preservedItemIds,
        array $unrestoredPositionItemIds,
        \Throwable $failure,
    ): void {
        if (
            $preservedItemIds !== []
            && count($rollbackFailures) === count($preservedItemIds) + count($unrestoredPositionItemIds)
        ) {
            throw new NavigationChangedConcurrentlyException(
                $menuId,
                $preservedItemIds,
                $unrestoredPositionItemIds,
                $failure,
            );
        }
    }

    /**
     * @param int[] $createdItemIds
     * @return string[]
     */
    private function removeCreatedMenuItems(array $createdItemIds): array
    {
        $failures = [];

        foreach (array_reverse($createdItemIds) as $createdItemId) {
            try {
                $deleted = $this->canCall('wp_delete_post')
                    ? wp_delete_post($createdItemId, true)
                    : false;
                $remaining = $this->storedPostExists($createdItemId);

                if (
                    ($deleted === false || $this->isWpError($deleted))
                    && $remaining
                ) {
                    $failures[] = "created item {$createdItemId} could not be removed";
                } elseif ($remaining) {
                    $failures[] = "created item {$createdItemId} still exists after cleanup";
                }
            } catch (\Throwable $rollbackFailure) {
                $failures[] = "created item {$createdItemId} could not be removed ({$rollbackFailure->getMessage()})";
            }
        }

        return $failures;
    }

    private function storedCreationMarker(object $database, int $itemId): ?string
    {
        $query = $database->prepare(
            "SELECT post_content_filtered
            FROM {$database->posts}
            WHERE ID = %d AND post_type = %s
            LIMIT 1",
            $itemId,
            'nav_menu_item',
        );
        $rows = is_string($query) ? $database->get_results($query) : null;
        if (!is_array($rows) || count($rows) !== 1 || !is_object($rows[0])) {
            return null;
        }

        return is_string($rows[0]->post_content_filtered ?? null)
            ? $rows[0]->post_content_filtered
            : null;
    }

    private function storedPostExists(int $postId): bool
    {
        $database = $this->navigationDatabase();
        if ($database !== null) {
            $query = $database->prepare(
                "SELECT ID FROM {$database->posts} WHERE ID = %d LIMIT 1",
                $postId,
            );
            if (!is_string($query)) {
                throw new \RuntimeException('The durable navigation item inspection failed.');
            }

            return (int) $database->get_var($query) === $postId;
        }
        if (defined('ABSPATH')) {
            throw new \RuntimeException('The durable navigation storage API is unavailable.');
        }
        if (!$this->canCall('get_post')) {
            throw new \RuntimeException('WordPress navigation item readback API is unavailable.');
        }

        return is_object(get_post($postId));
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
