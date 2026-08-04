<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Export;

interface PageJavaScriptExportRendererInterface
{
    /**
     * @param array<string, mixed>|null $headerData
     * @param array<string, mixed>|null $footerData
     */
    public function renderExportScripts(int $pageId, ?array $headerData = null, ?array $footerData = null): string;

    /** @return list<array{name: string, export_path: string, plugin_path: string, mime_type: string}> */
    public function approvedLibraryAssets(string $javascript = ''): array;
}
