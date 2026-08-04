<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

/**
 * Human-authorized entry point for moving one page's public pointer.
 */
interface PublishPageInterface
{
    public function publish(
        int $pageId,
        int $userId,
        ?int $expectedPageGeneration = null,
    ): PublishPageResult;
}
