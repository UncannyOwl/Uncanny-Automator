<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

interface LucideIconCatalogInterface
{
    public function contains(string $normalizedName): bool;

    /**
     * @return string[]
     */
    public function allNames(): array;
}
