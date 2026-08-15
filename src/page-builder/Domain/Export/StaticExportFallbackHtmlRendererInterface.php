<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

interface StaticExportFallbackHtmlRendererInterface
{
    /**
     * Render one stored section with every dynamic region omitted.
     *
     * @param array<string, mixed> $section
     * @param list<string>|null $omittedBindingIds
     */
    public function renderFallbackSection(
        array $section,
        int $pageId,
        ?StaticExportPageIdentity $pageIdentity = null,
        ?array &$omittedBindingIds = null,
    ): string;
}
