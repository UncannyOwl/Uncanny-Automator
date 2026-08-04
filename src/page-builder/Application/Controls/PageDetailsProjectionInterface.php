<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

/**
 * Read-only boundary for adopting public page identity and presenting a draft
 * permalink. Implementations must not write WordPress page fields.
 */
interface PageDetailsProjectionInterface
{
    public function readPublicPage(int $pageId): ?PageDetails;

    public function projectDraft(int $pageId, string $title, string $slug): ?PageDetails;
}
