<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Infrastructure\WordPress\AdminBrandingPage;

/**
 * Renders the site_logo dynamic source.
 *
 * Replaces the container's content with an <img> resolved from the site's
 * branding configuration (Engine option → Customizer → FSE site_logo).
 * Like wp_menu, this is self-rendering — no bind keys, no card template.
 */
final class SiteLogoRenderer implements SectionRendererInterface
{
    /**
     * @param string $cardTemplate Ignored — site_logo is self-rendering.
     * @param array<string, mixed> $args No query attributes for site_logo.
     * @return string Rendered <img> tag or empty string if no logo configured.
     */
    public function render(string $cardTemplate, array $args): string
    {
        $logoUrl = AdminBrandingPage::resolveLogoUrl();

        if ($logoUrl === '') {
            return '';
        }

        $siteName = esc_attr(get_bloginfo('name', 'display'));

        return sprintf(
            '<img src="%s" alt="%s" class="site-logo">',
            esc_url($logoUrl),
            $siteName,
        );
    }
}
