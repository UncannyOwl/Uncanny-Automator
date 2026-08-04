<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

interface PageSourceArchiveDownloadUrlInterface
{
    public function forPage(int $pageId, string $artifactToken): string;
}
