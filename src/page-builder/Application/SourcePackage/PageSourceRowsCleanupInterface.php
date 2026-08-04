<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

interface PageSourceRowsCleanupInterface
{
    public function deleteForPage(int $pageId): void;
}
