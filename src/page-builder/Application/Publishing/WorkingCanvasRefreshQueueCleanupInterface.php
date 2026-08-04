<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

/**
 * Removes refresh work that no longer has an owning page.
 */
interface WorkingCanvasRefreshQueueCleanupInterface
{
    public function removePage(int $pageId): void;
}
