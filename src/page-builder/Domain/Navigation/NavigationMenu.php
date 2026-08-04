<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Navigation;

final class NavigationMenu
{
    // Menu snapshot.
    /** @var NavigationMenuItem[] */
    private array $items;

    /**
     * @param NavigationMenuItem[] $items
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        array $items = [],
    ) {
        $this->items = array_values($items);
        usort($this->items, static function (NavigationMenuItem $left, NavigationMenuItem $right): int {
            $position = $left->position() <=> $right->position();
            if ($position !== 0) {
                return $position;
            }

            return $left->id() <=> $right->id();
        });
    }

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }

    /**
     * @return NavigationMenuItem[]
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return NavigationMenuItem[]
     */
    public function rootItems(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (NavigationMenuItem $item): bool => $item->parentId() === 0,
        ));
    }

    /**
     * @return NavigationMenuItem[]
     */
    public function childrenOf(int $parentId): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (NavigationMenuItem $item): bool => $item->parentId() === $parentId,
        ));
    }

    public function findItemById(int $itemId): ?NavigationMenuItem
    {
        foreach ($this->items as $item) {
            if ($item->id() === $itemId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   items: array<int, array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'items' => array_map(
                static fn (NavigationMenuItem $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }
}
