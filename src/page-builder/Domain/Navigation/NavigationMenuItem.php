<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Navigation;

final class NavigationMenuItem
{
    // Core menu item fields.
    /**
     * @param string[] $classes
     */
    public function __construct(
        private readonly int $id,
        private readonly string $label,
        private readonly string $type,
        private readonly string $objectType,
        private readonly int $objectId,
        private readonly string $url,
        private readonly int $parentId = 0,
        private readonly int $position = 0,
        private readonly string $target = '',
        private readonly array $classes = [],
    ) {}

    public function id(): int { return $this->id; }
    public function label(): string { return $this->label; }
    public function type(): string { return $this->type; }
    public function objectType(): string { return $this->objectType; }
    public function objectId(): int { return $this->objectId; }
    public function url(): string { return $this->url; }
    public function parentId(): int { return $this->parentId; }
    public function position(): int { return $this->position; }
    public function target(): string { return $this->target; }

    /**
     * @return string[]
     */
    public function classes(): array
    {
        return $this->classes;
    }

    /**
     * @return array{
     *   id: int,
     *   label: string,
     *   type: string,
     *   object_type: string,
     *   object_id: int,
     *   url: string,
     *   parent_id: int,
     *   position: int,
     *   target: string,
     *   classes: string[]
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'object_type' => $this->objectType,
            'object_id' => $this->objectId,
            'url' => $this->url,
            'parent_id' => $this->parentId,
            'position' => $this->position,
            'target' => $this->target,
            'classes' => $this->classes,
        ];
    }
}
