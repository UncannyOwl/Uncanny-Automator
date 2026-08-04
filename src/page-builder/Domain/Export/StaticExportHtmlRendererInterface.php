<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

interface StaticExportHtmlRendererInterface
{
    /**
     * Render one stored section into final static HTML.
     *
     * @param array<string, mixed> $section
     */
    public function renderSection(
        array $section,
        int $pageId,
        ?StaticExportPageIdentity $pageIdentity = null,
    ): string;
}
