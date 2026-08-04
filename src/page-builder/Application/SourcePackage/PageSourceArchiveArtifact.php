<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

/**
 * Temporary server-side archive ready to stream to the requesting browser.
 */
final class PageSourceArchiveArtifact
{
    public function __construct(
        private readonly string $path,
        private readonly string $filename,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function filename(): string
    {
        return $this->filename;
    }
}
