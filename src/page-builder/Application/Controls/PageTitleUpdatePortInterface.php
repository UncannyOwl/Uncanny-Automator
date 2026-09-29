<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

/**
 * Updates a working page title without accepting a caller-owned slug value.
 */
interface PageTitleUpdatePortInterface
{
    public function updateTitle(int $pageId, string $title, int $updatedBy): PageDetails;
}
