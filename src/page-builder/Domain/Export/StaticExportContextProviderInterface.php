<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

/**
 * Supplies runtime-specific dependency context for a static export.
 */
interface StaticExportContextProviderInterface
{
    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     * @return array<string, mixed>
     */
    public function contextForPage(int $pageId, array $sections, ?array $header, ?array $footer): array;
}
