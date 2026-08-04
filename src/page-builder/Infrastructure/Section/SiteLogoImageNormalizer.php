<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Infrastructure\WordPress\AdminBrandingPage;

/**
 * Save-time guard: rewrite hardcoded site-logo <img> tags into the
 * site_logo dynamic binding region.
 *
 * Agents (and humans) that inspect the media library tend to bake the
 * resolved logo URL into static HTML. That freezes the logo per page, so a
 * branding change stops propagating. Instead of asking writers to know the
 * binding in advance, every section/global-part write is normalized here:
 * any <img> whose src resolves to the configured logo attachment (original
 * or any size variant) becomes an empty <span data-ai-dynamic="site_logo">,
 * which SiteLogoRenderer resolves fresh on every render.
 *
 * Stored source stays pure binding markup: site_logo regions hold no
 * placeholder children, so the code editor shows the binding instead of a
 * resolved URL. Existing regions are emptied on save for the same reason.
 *
 * Matching compares URL paths only, so scheme/host variants of the same
 * attachment still match. Images inside other data-ai-dynamic regions are
 * left alone.
 */
final class SiteLogoImageNormalizer
{
    /**
     * Agent-facing warning attached to the write response when a rewrite
     * happened, so tool callers learn the binding exists.
     */
    public const REWRITE_WARNING = 'Hardcoded site logo <img> was rewritten to the site_logo dynamic binding. Author logos as <span data-ai-dynamic="site_logo"> regions so logo changes propagate to every page automatically.';

    /**
     * Rewrite hardcoded logo images using the currently configured logo.
     *
     * @param int|null $rewrites Receives the number of images rewritten.
     */
    public static function normalize(string $html, ?int &$rewrites = null): string
    {
        $rewrites = 0;

        if ($html === '') {
            return $html;
        }

        // The code editor displays bare regions as
        // <!-- upb:bindings:dynamic_data:{id} --> tokens; accept that form
        // from any writer and restore canonical region markup before the
        // logo pass runs.
        $html = DynamicRegionToken::decode($html);

        return self::rewriteWithPaths($html, self::logoUrlPaths(), $rewrites);
    }

    /**
     * Pure transform: rewrite <img> tags whose src path matches one of the
     * given logo URL paths. Separated from WordPress lookups for testability.
     *
     * @param string[] $logoPaths URL paths (e.g. "/wp-content/uploads/2026/06/logo.png").
     * @param int|null $rewrites  Receives the number of images rewritten.
     */
    public static function rewriteWithPaths(string $html, array $logoPaths, ?int &$rewrites = null): string
    {
        $rewrites = 0;

        if ($html === '') {
            return $html;
        }

        // Cheap bail: only pay the DOM round-trip when there is something to
        // normalize — a hardcoded logo URL or a site_logo region that may
        // still carry placeholder children.
        $hasLogoImage = $logoPaths !== []
            && stripos($html, '<img') !== false
            && self::containsAnyPath($html, $logoPaths);
        // Tolerant of quoting style and whitespace — authored HTML is not
        // guaranteed to match DOMDocument's double-quoted serialization.
        $hasLogoRegion = (bool) preg_match(
            '/data-ai-dynamic\s*=\s*(?:"site_logo"|\'site_logo\'|site_logo(?=[\s\/>]))/i',
            $html,
        );

        if (!$hasLogoImage && !$hasLogoRegion) {
            return $html;
        }

        // Encode Alpine @ shorthand attributes before DOMDocument parsing.
        // DOMDocument's HTML4 parser strips @-prefixed attribute names.
        $encoded = (string) preg_replace('/\s@([\w.:+-]+)=/', ' data-x-on-$1=', $html);

        $doc = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="__upb_logo_root">' . $encoded . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $root = $doc->getElementById('__upb_logo_root');
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $changed = false;

        // Collect into array — DOM mutations during iteration invalidate DOMNodeList.
        $images = [];
        foreach ($xpath->query('//img[@src]') as $img) {
            if ($img instanceof \DOMElement) {
                $images[] = $img;
            }
        }

        foreach ($images as $img) {
            if (!self::matchesLogoPath($img->getAttribute('src'), $logoPaths)) {
                continue;
            }
            if (self::insideDynamicRegion($img)) {
                continue;
            }

            $region = $doc->createElement('span');
            $region->setAttribute('data-ai-dynamic', 'site_logo');
            $img->parentNode?->replaceChild($region, $img);
            $rewrites++;
            $changed = true;
        }

        // site_logo regions are self-rendering with no template: any children
        // are stale placeholders (often a resolved logo URL) that the
        // renderer discards anyway. Empty them so the stored source — and the
        // code editor — shows the binding, not rendered HTML.
        foreach ($xpath->query('//*[@data-ai-dynamic="site_logo"]') as $regionNode) {
            if (!$regionNode instanceof \DOMElement) {
                continue;
            }
            while ($regionNode->firstChild) {
                $regionNode->removeChild($regionNode->firstChild);
                $changed = true;
            }
        }

        if (!$changed) {
            return $html;
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        // Decode Alpine @ shorthand back from placeholders.
        return trim((string) preg_replace('/\sdata-x-on-([\w.:+-]+)=/', ' @$1=', $output));
    }

    /**
     * URL paths of the configured logo attachment: the original file plus
     * every registered size variant.
     *
     * @return string[]
     */
    private static function logoUrlPaths(): array
    {
        if (!self::hasWpRuntime()) {
            return [];
        }

        $logoId = AdminBrandingPage::detectLogoId();
        if ($logoId <= 0) {
            return [];
        }

        $fullUrl = wp_get_attachment_url($logoId);
        if (!is_string($fullUrl) || $fullUrl === '') {
            return [];
        }

        $fullPath = parse_url($fullUrl, PHP_URL_PATH);
        if (!is_string($fullPath) || $fullPath === '') {
            return [];
        }

        $paths = [$fullPath];

        $hasMetadata = \function_exists('wp_get_attachment_metadata')
            || \function_exists(__NAMESPACE__ . '\\wp_get_attachment_metadata');
        $metadata = $hasMetadata ? wp_get_attachment_metadata($logoId) : false;

        if (is_array($metadata) && is_array($metadata['sizes'] ?? null)) {
            $dir = rtrim(dirname($fullPath), '/');
            foreach ($metadata['sizes'] as $size) {
                $file = is_array($size) ? ($size['file'] ?? null) : null;
                if (is_string($file) && $file !== '') {
                    $paths[] = $dir . '/' . $file;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * The lookups below resolve in two namespaces: get_option/get_theme_mod
     * inside AdminBrandingPage's, wp_get_attachment_url in this one. Unit
     * tests stub them per-namespace; production falls back to the globals.
     */
    private static function hasWpRuntime(): bool
    {
        $brandingNs = 'UncannyPageBuilder\\Infrastructure\\WordPress\\';

        return (\function_exists('get_option') || \function_exists($brandingNs . 'get_option'))
            && (\function_exists('get_theme_mod') || \function_exists($brandingNs . 'get_theme_mod'))
            && (\function_exists('wp_get_attachment_url') || \function_exists(__NAMESPACE__ . '\\wp_get_attachment_url'));
    }

    /**
     * @param string[] $paths
     */
    private static function containsAnyPath(string $html, array $paths): bool
    {
        foreach ($paths as $path) {
            if ($path !== '' && str_contains($html, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $logoPaths
     */
    private static function matchesLogoPath(string $src, array $logoPaths): bool
    {
        $path = parse_url(trim($src), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }

        return in_array($path, $logoPaths, true)
            || in_array(rawurldecode($path), $logoPaths, true);
    }

    /**
     * An image that is itself a dynamic region (e.g. a url-shaped binding)
     * or sits inside one (e.g. a site_logo placeholder) must not be wrapped
     * again — re-saving would otherwise nest regions indefinitely.
     */
    private static function insideDynamicRegion(\DOMElement $img): bool
    {
        for ($node = $img; $node instanceof \DOMElement; $node = $node->parentNode) {
            if ($node->hasAttribute('data-ai-dynamic')) {
                return true;
            }
        }

        return false;
    }
}
