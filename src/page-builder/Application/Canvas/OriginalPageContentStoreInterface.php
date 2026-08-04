<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

/**
 * Preserves and restores the WordPress-owned page body during editor changes.
 */
interface OriginalPageContentStoreInterface
{
    public function preserve(int $pageId): string;

    public function restore(int $pageId): void;

    public function discardBackup(int $pageId): void;
}
