<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Inward-facing port for removing editor transport artifacts from stored HTML.
 */
interface SectionHtmlCleanerInterface
{
    public function clean(string $html): string;
}
