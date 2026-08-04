<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

interface WorkingCanvasRefreshQueueInterface
{
    /**
     * @param int[] $pageIds
     */
    public function enqueuePages(array $pageIds): void;
}
