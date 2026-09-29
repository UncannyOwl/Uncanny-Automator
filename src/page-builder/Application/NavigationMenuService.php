<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Domain\Exception\InvalidNavigationRequestException;
use UncannyPageBuilder\Domain\Exception\NavigationMenuNotFoundException;
use UncannyPageBuilder\Domain\Exception\StaleNavigationMenuException;
use UncannyPageBuilder\Domain\Navigation\NavigationLocation;
use UncannyPageBuilder\Domain\Navigation\NavigationMenu;
use UncannyPageBuilder\Domain\Navigation\NavigationMenuItem;
use UncannyPageBuilder\Domain\Navigation\NavigationMenuRepositoryInterface;

final class NavigationMenuService
{
    public function __construct(
        private readonly NavigationMenuRepositoryInterface $repository,
    ) {}

    /**
     * @return array<int, array{slug: string, label: string, assigned_menu_id: int}>
     */
    public function listLocations(): array
    {
        return array_map(
            static fn (NavigationLocation $location): array => $location->toArray(),
            $this->repository->listLocations(),
        );
    }

    /**
     * @return array<int, array{id: int, name: string, items: array<int, array<string, mixed>>}>
     */
    public function listMenus(): array
    {
        return array_map(
            static fn (NavigationMenu $menu): array => $menu->toArray(),
            $this->repository->listMenus(),
        );
    }

    /**
     * @return array{id: int, name: string, items: array<int, array<string, mixed>>}|null
     */
    public function readMenu(int $menuId): ?array
    {
        $menu = $this->repository->findMenuById($menuId);
        if ($menu === null) {
            return null;
        }

        return $menu->toArray();
    }

    // Write operations.
    /**
     * @return array{id: int, name: string, items: array<int, array<string, mixed>>}
     */
    public function createMenu(string $name): array
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidNavigationRequestException('name is required.');
        }

        return $this->repository->createMenu($trimmed)->toArray();
    }

    /**
     * @param array{
     *   type: string,
     *   object_type?: string,
     *   object_id?: int,
     *   label?: string,
     *   url?: string,
     *   parent_id?: int,
     *   target?: string,
     *   classes?: string[]
     * } $input
     * @return array{
     *   menu: array{id: int, name: string, items: array<int, array<string, mixed>>},
     *   item_id: int
     * }
     */
    public function addItem(int $menuId, array $input): array
    {
        $menu = $this->requireMenu($menuId);
        $parentId = max(0, (int) ($input['parent_id'] ?? 0));

        $item = $this->hydrateMenuItem(
            id: 0,
            input: $input,
            fallback: null,
            position: count($menu->childrenOf($parentId)) + 1,
        );
        $this->assertValidParent($menu, $item->id(), $item->parentId());

        $saved = $this->repository->saveMenuItem($menuId, $item);

        return [
            'menu' => $saved->menu()->toArray(),
            'item_id' => $saved->itemId(),
        ];
    }

    /**
     * @param array{
     *   type?: string,
     *   object_type?: string,
     *   object_id?: int,
     *   label?: string,
     *   url?: string,
     *   parent_id?: int,
     *   target?: string,
     *   classes?: string[]
     * } $input
     * @return array{id: int, name: string, items: array<int, array<string, mixed>>}
     */
    public function updateItem(int $menuId, int $itemId, array $input): array
    {
        if ($itemId <= 0) {
            throw new InvalidNavigationRequestException('item_id is required.');
        }

        $menu = $this->requireMenu($menuId);
        $existing = $menu->findItemById($itemId);
        if ($existing === null) {
            throw new InvalidNavigationRequestException('item_id was not found in this menu.');
        }

        $parentId = max(0, (int) ($input['parent_id'] ?? $existing->parentId()));
        // An item that changes parent becomes the last child of its new parent.
        // Use move_item to place it at another sibling position.
        $position = $parentId === $existing->parentId()
            ? $existing->position()
            : count($menu->childrenOf($parentId)) + 1;

        $item = $this->hydrateMenuItem(
            id: $itemId,
            input: $input,
            fallback: $existing,
            position: $position,
        );
        // An unchanged parent needs no check. It can be an item that readers
        // do not see, such as a link to a trashed page.
        if ($parentId !== $existing->parentId()) {
            $this->assertValidParent($menu, $item->id(), $item->parentId());
        }

        return $this->repository->saveMenuItem($menuId, $item)->menu()->toArray();
    }

    /**
     * @return array{id: int, name: string, items: array<int, array<string, mixed>>}
     */
    public function deleteItem(int $menuId, int $itemId): array
    {
        if ($itemId <= 0) {
            throw new InvalidNavigationRequestException('item_id is required.');
        }

        $menu = $this->requireMenu($menuId);
        if ($menu->findItemById($itemId) === null) {
            throw new InvalidNavigationRequestException('item_id was not found in this menu.');
        }
        if ($menu->childrenOf($itemId) !== []) {
            throw new InvalidNavigationRequestException('item_id has child items. Move or delete its children first.');
        }

        return $this->repository->deleteMenuItem($menuId, $itemId)->toArray();
    }

    /**
     * @return array{id: int, name: string, items: array<int, array<string, mixed>>}
     */
    public function moveItem(
        int $menuId,
        int $itemId,
        int $parentId,
        int $position,
        ?array $expectedItemIds = null,
    ): array {
        if ($itemId <= 0) {
            throw new InvalidNavigationRequestException('item_id is required.');
        }

        $menu = $this->requireMenu($menuId);
        $authorizedItemIds = $this->authorizedItemIds($menuId, $menu, $expectedItemIds);
        $item = $menu->findItemById($itemId);
        if ($item === null) {
            throw new InvalidNavigationRequestException('item_id was not found in this menu.');
        }

        $this->assertValidParent($menu, $itemId, $parentId);

        $updatedItems = $this->reorderItems($menu, $itemId, $parentId, $position);

        return $this->repository->saveMenuTree($menuId, $updatedItems, $authorizedItemIds)->toArray();
    }

    /**
     * @param array<int, array{
     *   item_id?: int,
     *   type?: string,
     *   object_type?: string,
     *   object_id?: int,
     *   label?: string,
     *   url?: string,
     *   parent_id?: int,
     *   position?: int,
     *   target?: string,
     *   classes?: string[]
     * }> $items
     * @return array{id: int, name: string, items: array<int, array<string, mixed>>}
     */
    public function replaceTree(int $menuId, array $items, ?array $expectedItemIds = null): array
    {
        $menu = $this->requireMenu($menuId);
        $authorizedItemIds = $this->authorizedItemIds($menuId, $menu, $expectedItemIds);
        $prepared = $this->prepareTreeReplacementItems($menu, $items);

        return $this->repository->saveMenuTree($menuId, $prepared, $authorizedItemIds)->toArray();
    }

    /**
     * @return array{slug: string, label: string, assigned_menu_id: int}
     */
    public function assignLocation(string $locationSlug, int $menuId): array
    {
        $slug = trim($locationSlug);
        if ($slug === '') {
            throw new InvalidNavigationRequestException('location_slug is required.');
        }

        $this->requireMenu($menuId);
        $location = $this->requireLocation($slug);

        $assignedMenuId = $this->repository->assignMenuToLocation($slug, $menuId);
        if ($assignedMenuId !== $menuId) {
            throw new \RuntimeException('The navigation location assignment did not persist as requested.');
        }

        $location['assigned_menu_id'] = $assignedMenuId;

        return $location;
    }

    private function requireMenu(int $menuId): NavigationMenu
    {
        if ($menuId <= 0) {
            throw new InvalidNavigationRequestException('menu_id is required.');
        }

        $menu = $this->repository->findMenuById($menuId);
        if ($menu === null) {
            throw new NavigationMenuNotFoundException($menuId);
        }

        return $menu;
    }

    /**
     * @param int[]|null $expectedItemIds
     * @return int[]
     */
    private function authorizedItemIds(
        int $menuId,
        NavigationMenu $menu,
        ?array $expectedItemIds,
    ): array {
        $currentItemIds = $this->normalizedItemIds(array_map(
            static fn (NavigationMenuItem $item): int => $item->id(),
            $menu->items(),
        ));
        if ($expectedItemIds === null) {
            return $currentItemIds;
        }

        $expectedItemIds = $this->normalizedItemIds($expectedItemIds);
        if ($expectedItemIds !== $currentItemIds) {
            throw new StaleNavigationMenuException($menuId);
        }

        return $expectedItemIds;
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
     * @return array{slug: string, label: string, assigned_menu_id: int}
     */
    private function requireLocation(string $slug): array
    {
        foreach ($this->listLocations() as $location) {
            if (($location['slug'] ?? '') === $slug) {
                return $location;
            }
        }

        throw new InvalidNavigationRequestException('location_slug was not found.');
    }

    private function assertValidParent(NavigationMenu $menu, int $itemId, int $parentId): void
    {
        if ($parentId === 0) {
            return;
        }

        if ($menu->findItemById($parentId) === null) {
            throw new InvalidNavigationRequestException('parent_id was not found in this menu.');
        }

        if ($itemId > 0 && ($parentId === $itemId || $this->isDescendantParent($menu, $itemId, $parentId))) {
            throw new InvalidNavigationRequestException('parent_id cannot move an item into itself or its descendant.');
        }
    }

    /**
     * @return NavigationMenuItem[]
     */
    private function reorderItems(NavigationMenu $menu, int $movedItemId, int $newParentId, int $newPosition): array
    {
        $groups = [];
        $byId = [];

        foreach ($menu->items() as $item) {
            $itemId = $item->id();
            $byId[$itemId] = $item;

            if ($itemId === $movedItemId) {
                continue;
            }

            $groups[$item->parentId()][] = $item;
        }

        $moved = $byId[$movedItemId];
        foreach (array_keys($groups) as $parentId) {
            usort($groups[$parentId], static function (NavigationMenuItem $left, NavigationMenuItem $right): int {
                $position = $left->position() <=> $right->position();
                if ($position !== 0) {
                    return $position;
                }

                return $left->id() <=> $right->id();
            });
        }

        $groups[$newParentId] ??= [];

        $insertAt = max(0, min(count($groups[$newParentId]), $newPosition - 1));
        array_splice($groups[$newParentId], $insertAt, 0, [$this->copyItem($moved, parentId: $newParentId, position: 0)]);

        $updated = [];
        foreach ($groups as $parentId => $siblings) {
            foreach (array_values($siblings) as $index => $sibling) {
                $updated[] = $this->copyItem($sibling, parentId: (int) $parentId, position: $index + 1);
            }
        }

        usort($updated, static fn (NavigationMenuItem $left, NavigationMenuItem $right): int => $left->position() <=> $right->position());

        return $updated;
    }

    /**
     * @param array<int, array{
     *   item_id?: int,
     *   type?: string,
     *   object_type?: string,
     *   object_id?: int,
     *   label?: string,
     *   url?: string,
     *   parent_id?: int,
     *   position?: int,
     *   target?: string,
     *   classes?: string[]
     * }> $items
     * @return NavigationMenuItem[]
     */
    private function prepareTreeReplacementItems(NavigationMenu $menu, array $items): array
    {
        if ($items === []) {
            throw new InvalidNavigationRequestException('items is required for replace_tree.');
        }

        $existingById = [];
        foreach ($menu->items() as $item) {
            $existingById[$item->id()] = $item;
        }

        $seenIds = [];
        $prepared = [];
        $existingMenuHasItems = $existingById !== [];

        foreach ($items as $index => $itemInput) {
            if (!is_array($itemInput)) {
                throw new InvalidNavigationRequestException('items must contain objects.');
            }

            $itemId = (int) ($itemInput['item_id'] ?? 0);
            if ($existingMenuHasItems && $itemId <= 0) {
                throw new InvalidNavigationRequestException('replace_tree requires explicit item_id values for existing menus.');
            }
            if ($itemId > 0) {
                if (isset($seenIds[$itemId])) {
                    throw new InvalidNavigationRequestException('replace_tree cannot repeat item_id values.');
                }
                if (!isset($existingById[$itemId])) {
                    throw new InvalidNavigationRequestException('replace_tree referenced an unknown item_id.');
                }
                $seenIds[$itemId] = true;
            }

            $fallback = $itemId > 0 ? $existingById[$itemId] : null;
            $prepared[] = $this->hydrateMenuItem(
                id: $itemId,
                input: $itemInput,
                fallback: $fallback,
                position: max(1, (int) ($itemInput['position'] ?? ($index + 1))),
            );
        }

        if ($existingMenuHasItems) {
            foreach (array_keys($existingById) as $existingId) {
                if (!isset($seenIds[$existingId])) {
                    throw new InvalidNavigationRequestException('replace_tree must include every existing item_id.');
                }
            }
        }

        foreach ($prepared as $item) {
            $parentId = $item->parentId();
            if ($parentId === 0) {
                continue;
            }
            $parent = $this->findPreparedItemById($prepared, $parentId);
            if ($parent === null) {
                throw new InvalidNavigationRequestException('replace_tree referenced a missing parent_id.');
            }
            if ($parent->id() === $item->id() || $this->isPreparedDescendantParent($prepared, $item->id(), $parent->id())) {
                throw new InvalidNavigationRequestException('replace_tree cannot move an item into itself or its descendant.');
            }
        }

        return $this->normalizePreparedTreePositions($prepared);
    }

    /**
     * @param NavigationMenuItem[] $items
     * @return NavigationMenuItem[]
     */
    private function normalizePreparedTreePositions(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$item->parentId()][] = $item;
        }

        $normalized = [];
        foreach ($groups as $parentId => $siblings) {
            usort($siblings, static function (NavigationMenuItem $left, NavigationMenuItem $right): int {
                $position = $left->position() <=> $right->position();
                if ($position !== 0) {
                    return $position;
                }

                return $left->id() <=> $right->id();
            });

            foreach (array_values($siblings) as $index => $sibling) {
                $normalized[] = $this->copyItem($sibling, parentId: (int) $parentId, position: $index + 1);
            }
        }

        usort($normalized, static fn (NavigationMenuItem $left, NavigationMenuItem $right): int => $left->position() <=> $right->position());

        return $normalized;
    }

    private function findPreparedItemById(array $items, int $itemId): ?NavigationMenuItem
    {
        foreach ($items as $item) {
            if ($item->id() === $itemId) {
                return $item;
            }
        }

        return null;
    }

    private function isDescendantParent(NavigationMenu $menu, int $itemId, int $candidateParentId): bool
    {
        $queue = [$itemId];
        $visited = [];
        while ($queue !== []) {
            $current = (int) array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            foreach ($menu->childrenOf((int) $current) as $child) {
                if ($child->id() === $candidateParentId) {
                    return true;
                }
                $queue[] = $child->id();
            }
        }

        return false;
    }

    /**
     * @param NavigationMenuItem[] $items
     */
    private function isPreparedDescendantParent(array $items, int $itemId, int $candidateParentId): bool
    {
        $queue = [$itemId];
        $visited = [];
        while ($queue !== []) {
            $current = (int) array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            foreach ($items as $item) {
                if ($item->parentId() !== $current) {
                    continue;
                }
                if ($item->id() === $candidateParentId) {
                    return true;
                }
                $queue[] = $item->id();
            }
        }

        return false;
    }

    /**
     * @param array{
     *   type?: string,
     *   object_type?: string,
     *   object_id?: int,
     *   label?: string,
     *   url?: string,
     *   parent_id?: int,
     *   target?: string,
     *   classes?: string[]
     * } $input
     */
    private function hydrateMenuItem(int $id, array $input, ?NavigationMenuItem $fallback, int $position): NavigationMenuItem
    {
        $type = strtolower(trim((string) ($input['type'] ?? $fallback?->type() ?? '')));
        if (!in_array($type, ['custom', 'post_type', 'taxonomy'], true)) {
            throw new InvalidNavigationRequestException('type must be custom, post_type, or taxonomy.');
        }

        $label = trim((string) ($input['label'] ?? $fallback?->label() ?? ''));
        // A linked item without a label follows its linked object's title.
        if ($label === '' && $type === 'custom') {
            throw new InvalidNavigationRequestException('label is required.');
        }

        $objectType = trim((string) ($input['object_type'] ?? $fallback?->objectType() ?? ''));
        $objectId = (int) ($input['object_id'] ?? $fallback?->objectId() ?? 0);
        $url = trim((string) ($input['url'] ?? $fallback?->url() ?? ''));

        if ($type === 'custom') {
            if (!$this->isValidCustomUrl($url)) {
                throw new InvalidNavigationRequestException('url must be a valid custom link.');
            }
            $objectType = 'custom';
            $objectId = 0;
        } else {
            if ($objectType === '') {
                throw new InvalidNavigationRequestException('object_type is required for non-custom items.');
            }
            if ($objectId <= 0) {
                throw new InvalidNavigationRequestException('object_id is required for non-custom items.');
            }
            if (!$this->repository->isValidItemReference($type, $objectType, $objectId)) {
                throw new InvalidNavigationRequestException('The referenced WordPress object was not found for this item type.');
            }
        }

        $parentId = max(0, (int) ($input['parent_id'] ?? $fallback?->parentId() ?? 0));
        $target = trim((string) ($input['target'] ?? $fallback?->target() ?? ''));
        $classes = $this->normalizeClasses($input['classes'] ?? $fallback?->classes() ?? []);

        return new NavigationMenuItem(
            id: $id,
            label: $label,
            type: $type,
            objectType: $objectType,
            objectId: $objectId,
            url: $url,
            parentId: $parentId,
            position: $position,
            target: $target,
            classes: $classes,
            description: $fallback?->description() ?? '',
            titleAttribute: $fallback?->titleAttribute() ?? '',
            xfn: $fallback?->xfn() ?? '',
        );
    }

    private function copyItem(NavigationMenuItem $item, int $parentId, int $position): NavigationMenuItem
    {
        return new NavigationMenuItem(
            id: $item->id(),
            label: $item->label(),
            type: $item->type(),
            objectType: $item->objectType(),
            objectId: $item->objectId(),
            url: $item->url(),
            parentId: $parentId,
            position: $position,
            target: $item->target(),
            classes: $item->classes(),
            description: $item->description(),
            titleAttribute: $item->titleAttribute(),
            xfn: $item->xfn(),
        );
    }

    /**
     * @param mixed $classes
     * @return string[]
     */
    private function normalizeClasses(mixed $classes): array
    {
        if (!is_array($classes)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $classes),
            static fn (string $value): bool => $value !== '',
        ));
    }

    private function isValidCustomUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if ($url[0] === '/' || $url[0] === '#' || $url[0] === '?') {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
