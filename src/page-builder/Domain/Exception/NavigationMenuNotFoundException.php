<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class NavigationMenuNotFoundException extends \RuntimeException
{
    public function __construct(
        private readonly int $menuId,
    ) {
        parent::__construct(sprintf('Navigation menu %d was not found.', $menuId));
    }

    public function menuId(): int
    {
        return $this->menuId;
    }
}
