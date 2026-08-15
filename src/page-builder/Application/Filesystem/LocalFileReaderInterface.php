<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Filesystem;

/**
 * Application boundary for reading local files.
 *
 * Keeping this separate from the mutating filesystem port lets non-WordPress
 * commands provide the one capability they need without booting WordPress.
 */
interface LocalFileReaderInterface
{
    public function read(string $path): string|false;
}
