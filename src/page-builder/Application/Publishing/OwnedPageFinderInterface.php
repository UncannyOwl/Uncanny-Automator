<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

interface OwnedPageFinderInterface
{
    /**
     * @return int[]
     */
    public function ownedPageIds(int $limit = 500, int $afterPageId = 0): array;
}
