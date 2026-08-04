<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

interface StaticExportAssetSourceInterface
{
    /**
     * Read a required plugin-owned asset.
     */
    public function read(string $relativePath): string;
}
