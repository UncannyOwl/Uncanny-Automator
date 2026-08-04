<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Domain\Section\SectionHtmlCleanerInterface;

final class HtmlBridgeArtifactCleanerAdapter implements SectionHtmlCleanerInterface
{
    public function clean(string $html): string
    {
        return HtmlBridgeArtifactCleaner::clean($html);
    }
}
