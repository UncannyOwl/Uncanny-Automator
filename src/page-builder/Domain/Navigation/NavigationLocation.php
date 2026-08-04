<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Navigation;

final class NavigationLocation
{
    public function __construct(
        private readonly string $slug,
        private readonly string $label,
        private readonly int $assignedMenuId = 0,
    ) {}

    public function slug(): string { return $this->slug; }
    public function label(): string { return $this->label; }
    public function assignedMenuId(): int { return $this->assignedMenuId; }

    /**
     * @return array{slug: string, label: string, assigned_menu_id: int}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'assigned_menu_id' => $this->assignedMenuId,
        ];
    }
}
