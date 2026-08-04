<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

/**
 * Reports whether WordPress must render its password form for a public page.
 */
interface PagePasswordProtectionInterface
{
    public function isPasswordRequired(int $pageId): bool;
}
