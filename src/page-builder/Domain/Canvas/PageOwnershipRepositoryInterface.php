<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Canvas;

/**
 * Stores which editor currently manages a WordPress page body.
 */
interface PageOwnershipRepositoryInterface
{
    public function isOwned(int $pageId): bool;

    public function markOwned(int $pageId): void;

    public function markWordPressManaged(int $pageId): void;
}
