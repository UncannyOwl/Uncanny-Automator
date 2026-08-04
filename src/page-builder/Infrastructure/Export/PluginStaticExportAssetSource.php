<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Export;

use UncannyPageBuilder\Domain\Export\StaticExportAssetSourceInterface;

/**
 * Reads plugin-owned runtime assets for static exports.
 */
final class PluginStaticExportAssetSource implements StaticExportAssetSourceInterface
{
    public function __construct(
        private readonly string $pluginPath,
    ) {}

    public function read(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $path = rtrim($this->pluginPath, '/') . '/' . $relativePath;

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Static export asset "%s" was not found.', $relativePath));
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new \RuntimeException(sprintf('Static export asset "%s" could not be read.', $relativePath));
        }

        return $content;
    }
}
