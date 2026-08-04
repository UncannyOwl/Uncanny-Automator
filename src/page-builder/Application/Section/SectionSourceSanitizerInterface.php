<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Section;

/**
 * Application port for the environment-sensitive section persistence gate.
 */
interface SectionSourceSanitizerInterface
{
    public function sanitize(string $html, string $css): SanitizedSectionSource;
}
