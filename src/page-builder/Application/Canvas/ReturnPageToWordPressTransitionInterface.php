<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

/**
 * One serialized transition from Page Builder public handover to WordPress.
 */
interface ReturnPageToWordPressTransitionInterface
{
    /**
     * @return bool True when ownership changed; false when WordPress already owns the page.
     */
    public function returnToWordPress(int $pageId): bool;
}
