<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

/**
 * Application boundary for the nonce-protected WordPress page trash action.
 */
interface PageTrashUrlPortInterface
{
    public function forPage(int $pageId): ?string;
}
