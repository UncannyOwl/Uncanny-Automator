<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Export;

use UncannyPageBuilder\Domain\Export\StaticExportHtmlRendererInterface;
use UncannyPageBuilder\Domain\Export\StaticExportPageIdentity;
use UncannyPageBuilder\Infrastructure\Rendering\CanvasRenderer;

/**
 * Renders static export HTML through the same section renderer used by pages.
 */
final class CanvasStaticExportHtmlRenderer implements StaticExportHtmlRendererInterface
{
    public function __construct(
        private readonly CanvasRenderer $renderer,
    ) {}

    public function renderSection(
        array $section,
        int $pageId,
        ?StaticExportPageIdentity $pageIdentity = null,
    ): string {
        return $this->renderer->renderSectionHtml(
            (string) ($section['content']['html'] ?? ''),
            isset($section['id']) ? (int) $section['id'] : null,
            [],
            $pageIdentity,
        );
    }
}
