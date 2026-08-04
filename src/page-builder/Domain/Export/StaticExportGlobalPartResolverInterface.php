<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

interface StaticExportGlobalPartResolverInterface
{
    /**
     * Resolve the Page Builder global part that belongs in a static export.
     *
     * Returns null when the page should not export an Uncanny-owned global part
     * for this type, such as pages using the theme shell or an explicit "none"
     * header/footer override.
     *
     * @return array<string, mixed>|null
     */
    public function resolveForPage(int $pageId, GlobalPartType $type): ?array;
}
