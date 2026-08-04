<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

interface PageSourceArchiveWriterInterface
{
    /**
     * @param array<string, mixed> $pageSource
     * @param list<PageSourceImage> $images
     * @param list<string> $warnings
     */
    public function write(int $pageId, array $pageSource, array $images, array $warnings): PageSourceArchiveArtifact;
}
