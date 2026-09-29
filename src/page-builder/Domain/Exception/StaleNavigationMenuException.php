<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class StaleNavigationMenuException extends \RuntimeException
{
    public function __construct(int $menuId)
    {
        parent::__construct(sprintf('Navigation menu %d changed after authorization.', $menuId));
    }
}
