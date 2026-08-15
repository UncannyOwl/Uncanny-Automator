<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Filesystem;

use UncannyPageBuilder\Application\Filesystem\LocalFileReaderInterface;

/**
 * Local-file reader for commands that intentionally run without WordPress.
 */
final class NativeLocalFileReader implements LocalFileReaderInterface
{
    public function read(string $path): string|false
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- This adapter exists for CLI commands that run without WordPress.
        return file_get_contents($path);
    }
}
