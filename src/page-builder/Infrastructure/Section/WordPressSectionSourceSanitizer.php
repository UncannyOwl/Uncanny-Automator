<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Application\Section\SanitizedSectionSource;
use UncannyPageBuilder\Application\Section\SectionSourceSanitizerInterface;
use UncannyPageBuilder\Infrastructure\WordPress\CssSanitizationGate;
use UncannyPageBuilder\Infrastructure\WordPress\HtmlSanitizationGate;

/**
 * WordPress adapter for environment-sensitive section source persistence.
 */
final class WordPressSectionSourceSanitizer implements SectionSourceSanitizerInterface
{
    public function sanitize(string $html, string $css): SanitizedSectionSource
    {
        $logoRewrites = 0;
        $html = HtmlSanitizationGate::filter(
            SiteLogoImageNormalizer::normalize($html, $logoRewrites),
        );

        return new SanitizedSectionSource(
            $html,
            CssSanitizationGate::filter($css),
            $logoRewrites > 0 ? [SiteLogoImageNormalizer::REWRITE_WARNING] : [],
        );
    }
}
