<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Export;

use UncannyPageBuilder\Domain\Export\StaticPageExport;
use UncannyPageBuilder\Domain\Export\StaticExportPurpose;

/**
 * Builds one coherent static representation without persisting public state.
 */
interface StaticPageExportBuilderInterface
{
    public function buildForPage(
        int $pageId,
        ?string $documentTitle = null,
        ?string $documentPermalink = null,
        StaticExportPurpose $purpose = StaticExportPurpose::Portable,
    ): StaticPageExport;
}
