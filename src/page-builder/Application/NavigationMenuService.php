<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Domain\Exception\NavigationMenuNotFoundException;
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
            throw new \InvalidArgumentException('name is required.');
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
     * @return array{id: int, name: string, items: array<int, array<string, mixed>>}
     */
    public function addItem(int $menuId, array $input): array
    {
        $menu = $this->requireMenu($menuId);

        $item = $this->hydrateMenuItem(
            id: 0,
            input: $input,
            fallback: null,
            position: count($menu->items()) + 1,
        );

        return $this->repository->saveMenuItem($menuId, $item)->toArray();
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
            throw new \InvalidArgumentException('item_id is required.');
        }

        $menu = $this->requireMenu($menuId);
        $existing = $menu->findItemById($itemId);
        if ($existing === null) {
            throw new \InvalidArgumentException('item_id was not found in this menu.');
        }

        $item = $this->hydrateMenuItem(
            id: $itemId,
            input: $input,
            fallback: $existing,
            position: $existing->position(),
        );

        return $this->repository->saveMenuItem($menuId, $item)->toArray();
    }

    /**
     * @return array{id: int, name: string, items: array<int, array<string, mixed>>}
     */
    public function deleteItem(int $menuId, int $itemId): array
    {
        if ($itemId <= 0) {
            throw new \InvalidArgumentException('item_id is required.');
        }

        $menu = $this->requireMenu($menuId);
        if ($menu->findItemById($itemId) === null) {
            throw new \InvalidArgumentException('item_id was not found in this menu.');
        }

        return $this->repository->deleteMenuItem($menuId, $itemId)->toArray();
    }

    /**
     * @return array{id: int, name: string, items: array<int, array<string, mixed>>}
     */
    public function moveItem(int $menuId, int $itemId, int $parentId, int $position): array
    {
        if ($itemId <= 0) {
            throw new \InvalidArgumentException('item_id is required.');
        }

        $menu = $this->requireMenu($menuId);
        $item = $menu->findItemById($itemId);
        if ($item === null) {
            throw new \InvalidArgumentException('item_id was not found in this menu.');
        }

        if ($parentId > 0) {
            $parent = $menu->findItemById($parentId);
            if ($parent === null) {
                throw new \InvalidArgumentException('parent_id was not found in this menu.');
            }
            if ($parentId === $itemId || $this->isDescendantParent($menu, $itemId, $parentId)) {
                throw new \InvalidArgumentException('parent_id cannot move an item into itself or its descendant.');
            }
        }

        $updatedItems = $this->reorderItems($menu, $itemId, $parentId, $position);

        return $this->repository->saveMenuTree($menuId, $updatedItems)->toArray();
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
    public function replaceTree(int $menuId, array $items): array
    {
        $menu = $this->requireMenu($menuId);
        $prepared = $this->prepareTreeReplacementItems($menu, $items);

        return $this->repository->saveMenuTree($menuId, $prepared)->toArray();
    }

    /**
     * @return array{slug: string, label: string, assigned_menu_id: int}
     */
    public function assignLocation(string $locationSlug, int $menuId): array
    {
        $slug = trim($locationSlug);
        if ($slug === '') {
            throw new \InvalidArgumentException('location_slug is required.');
        }

        $this->requireMenu($menuId);
        $location = $this->requireLocation($slug);

        $this->repository->assignMenuToLocation($slug, $menuId);

        $location['assigned_menu_id'] = $menuId;

        return $location;
    }

    private function requireMenu(int $menuId): NavigationMenu
    {
        if ($menuId <= 0) {
            throw new \InvalidArgumentException('menu_id is required.');
        }

        $menu = $this->repository->findMenuById($menuId);
        if ($menu === null) {
            throw new NavigationMenuNotFoundException($menuId);
        }

        return $menu;
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

        throw new \InvalidArgumentException('location_slug was not found.');
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
        $groups[$newParentId] ??= [];
        usort($groups[$newParentId], static fn (NavigationMenuItem $left, NavigationMenuItem $right): int => $left->position() <=> $right->position());

        $insertAt = max(0, min(count($groups[$newParentId]), $newPosition - 1));
        array_splice($groups[$newParentId], $insertAt, 0, [$this->copyItem($moved, parentId: $newParentId, position: 0)]);

        $updated = [];
        foreach ($groups as $parentId => $siblings) {
            usort($siblings, static fn (NavigationMenuItem $left, NavigationMenuItem $right): int => $left->position() <=> $right->position());
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
            throw new \InvalidArgumentException('items is required for replace_tree.');
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
                throw new \InvalidArgumentException('items must contain objects.');
            }

            $itemId = (int) ($itemInput['item_id'] ?? 0);
            if ($existingMenuHasItems && $itemId <= 0) {
                throw new \InvalidArgumentException('replace_tree requires explicit item_id values for existing menus.');
            }
            if ($itemId > 0) {
                if (isset($seenIds[$itemId])) {
                    throw new \InvalidArgumentException('replace_tree cannot repeat item_id values.');
                }
                if (!isset($existingById[$itemId])) {
                    throw new \InvalidArgumentException('replace_tree referenced an unknown item_id.');
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
                    throw new \InvalidArgumentException('replace_tree must include every existing item_id.');
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
                throw new \InvalidArgumentException('replace_tree referenced a missing parent_id.');
            }
            if ($parent->id() === $item->id() || $this->isPreparedDescendantParent($prepared, $item->id(), $parent->id())) {
                throw new \InvalidArgumentException('replace_tree cannot move an item into itself or its descendant.');
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
        while ($queue !== []) {
            $current = array_shift($queue);
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
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($items as $item) {
                if ($item->parentId() !== (int) $current) {
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
            throw new \InvalidArgumentException('type must be custom, post_type, or taxonomy.');
        }

        $label = trim((string) ($input['label'] ?? $fallback?->label() ?? ''));
        if ($label === '') {
            throw new \InvalidArgumentException('label is required.');
        }

        $objectType = trim((string) ($input['object_type'] ?? $fallback?->objectType() ?? ''));
        $objectId = (int) ($input['object_id'] ?? $fallback?->objectId() ?? 0);
        $url = trim((string) ($input['url'] ?? $fallback?->url() ?? ''));

        if ($type === 'custom') {
            if (!$this->isValidCustomUrl($url)) {
                throw new \InvalidArgumentException('url must be a valid custom link.');
            }
            $objectType = 'custom';
            $objectId = 0;
        } else {
            if ($objectType === '') {
                throw new \InvalidArgumentException('object_type is required for non-custom items.');
            }
            if ($objectId <= 0) {
                throw new \InvalidArgumentException('object_id is required for non-custom items.');
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
