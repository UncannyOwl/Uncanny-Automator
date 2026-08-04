<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

/**
 * Refreshes editor-derived canvas state without creating or selecting public output.
 */
interface WorkingCanvasRefresherInterface
{
    public function refresh(int $pageId): void;
}
