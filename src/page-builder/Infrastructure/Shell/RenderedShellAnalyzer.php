<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Shell;

use UncannyPageBuilder\Domain\Shell\ShellImportAnalysis;

/**
 * Pure DOMDocument/DOMXPath analysis of rendered shell HTML.
 * Extracts structural elements (logo, nav, CTA, social, copyright)
 * and detects unsupported patterns (mega-menus, Elementor widgets, etc.).
 *
 * No WordPress dependencies — extraction is pure DOM.
 */
final class RenderedShellAnalyzer
{
    private const MAX_DOM_NODES = 2000;

    private const SOCIAL_DOMAINS = [
        'facebook.com', 'twitter.com', 'x.com', 'instagram.com',
        'linkedin.com', 'youtube.com', 'github.com', 'tiktok.com',
        'pinterest.com', 'reddit.com', 'mastodon.social',
    ];

    private const WARNING_PATTERNS = [
        'mega-menu'         => ['mega-menu', 'megamenu'],
        'search-overlay'    => ['search-overlay', 'search-modal', 'search-popup'],
        'cart-widget'       => ['cart-widget', 'mini-cart', 'woocommerce-cart', 'shopping-cart'],
        'elementor-widget'  => ['elementor-widget', 'elementor-element'],
        'animations'        => ['wow ', 'aos-', 'data-aos', 'animate__'],
    ];

    private const MAX_NAV_LINKS = 8;

    public function analyzeHeader(string $html): ShellImportAnalysis
    {
        if (trim($html) === '') {
            return new ShellImportAnalysis(type: 'header');
        }

        $doc = $this->loadHtml($html);
        if ($doc === null) {
            return new ShellImportAnalysis(type: 'header');
        }

        $xpath = new \DOMXPath($doc);

        return new ShellImportAnalysis(
            type: 'header',
            logoUrl: $this->extractLogoUrl($xpath),
            logoText: $this->extractLogoText($xpath),
            navLinks: $this->extractNavLinks($xpath),
            ctaLinks: $this->extractCtaLinks($xpath),
            socialLinks: $this->extractSocialLinks($xpath),
            warnings: $this->detectWarnings($html),
        );
    }

    public function analyzeFooter(string $html): ShellImportAnalysis
    {
        if (trim($html) === '') {
            return new ShellImportAnalysis(type: 'footer');
        }

        $doc = $this->loadHtml($html);
        if ($doc === null) {
            return new ShellImportAnalysis(type: 'footer');
        }

        $xpath = new \DOMXPath($doc);

        return new ShellImportAnalysis(
            type: 'footer',
            logoUrl: $this->extractLogoUrl($xpath),
            logoText: $this->extractLogoText($xpath),
            navLinks: $this->extractNavLinks($xpath),
            ctaLinks: $this->extractCtaLinks($xpath),
            socialLinks: $this->extractSocialLinks($xpath),
            copyrightText: $this->extractCopyrightText($xpath),
            footerColumns: $this->countFooterColumns($xpath),
            warnings: $this->detectWarnings($html),
        );
    }

    private function loadHtml(string $html): ?\DOMDocument
    {
        $doc = new \DOMDocument();
        $wrapped = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$ok || $doc->getElementsByTagName('*')->length > self::MAX_DOM_NODES) {
            return null;
        }

        return $doc;
    }

    private function extractLogoUrl(\DOMXPath $xpath): ?string
    {
        // Look for img inside logo/brand/site-logo containers
        $patterns = [
            '//img[contains(@class, "logo")]',
            '//*[contains(@class, "logo")]//img',
            '//*[contains(@class, "brand")]//img',
            '//*[contains(@class, "site-logo")]//img',
            '//*[contains(@class, "custom-logo")]//img',
        ];

        foreach ($patterns as $pattern) {
            $nodes = $xpath->query($pattern);
            if ($nodes !== false && $nodes->length > 0) {
                $src = $nodes->item(0)->getAttribute('src');
                if ($src !== '') {
                    return $src;
                }
            }
        }

        return null;
    }

    private function extractLogoText(\DOMXPath $xpath): ?string
    {
        // Look for text in logo/brand/site-title containers
        $patterns = [
            '//a[contains(@class, "logo")]',
            '//a[contains(@class, "brand")]',
            '//a[contains(@class, "site-title")]',
            '//*[contains(@class, "site-title")]',
            '//*[contains(@class, "logo")]//a',
        ];

        foreach ($patterns as $pattern) {
            $nodes = $xpath->query($pattern);
            if ($nodes !== false && $nodes->length > 0) {
                $text = trim($nodes->item(0)->textContent);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }

    /** @return array<int, array{label: string, href: string}> */
    private function extractNavLinks(\DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//nav//a');
        if ($nodes === false || $nodes->length === 0) {
            return [];
        }

        $links = [];
        foreach ($nodes as $node) {
            if (count($links) >= self::MAX_NAV_LINKS) {
                break;
            }

            $label = trim($node->textContent);
            $href  = $node->getAttribute('href');

            if ($label !== '' && $href !== '') {
                $links[] = ['label' => $label, 'href' => $href];
            }
        }

        return $links;
    }

    /** @return array<int, array{label: string, href: string}> */
    private function extractCtaLinks(\DOMXPath $xpath): array
    {
        $patterns = [
            '//a[contains(@class, "btn")]',
            '//a[contains(@class, "button")]',
            '//a[contains(@class, "cta")]',
        ];

        $links = [];
        $seen  = [];

        foreach ($patterns as $pattern) {
            $nodes = $xpath->query($pattern);
            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                $label = trim($node->textContent);
                $href  = $node->getAttribute('href');

                if ($label !== '' && $href !== '' && !isset($seen[$href])) {
                    $links[] = ['label' => $label, 'href' => $href];
                    $seen[$href] = true;
                }
            }
        }

        return $links;
    }

    /** @return string[] */
    private function extractSocialLinks(\DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//a[@href]');
        if ($nodes === false) {
            return [];
        }

        $socials = [];

        foreach ($nodes as $node) {
            $href = $node->getAttribute('href');

            foreach (self::SOCIAL_DOMAINS as $domain) {
                if (str_contains($href, $domain)) {
                    $socials[] = $href;
                    break;
                }
            }
        }

        return array_values(array_unique($socials));
    }

    private function extractCopyrightText(\DOMXPath $xpath): ?string
    {
        // The © symbol (U+00A9) is decoded by DOMDocument from &copy;
        // Use the literal UTF-8 byte sequence in XPath.
        $copyright = "\xC2\xA9"; // UTF-8 for ©

        $nodes = $xpath->query(
            '//*[contains(text(), "' . $copyright . '") or contains(text(), "copyright") or contains(text(), "Copyright")]'
        );
        if ($nodes !== false && $nodes->length > 0) {
            $text = trim($nodes->item(0)->textContent);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function countFooterColumns(\DOMXPath $xpath): int
    {
        // Look for direct children of footer-like containers
        $patterns = [
            '//footer/*[contains(@class, "col")]',
            '//footer/div/div',
            '//*[contains(@class, "footer")]/*[contains(@class, "col")]',
            '//*[contains(@class, "footer-inner")]/div',
            '//*[contains(@class, "footer-widgets")]/div',
        ];

        foreach ($patterns as $pattern) {
            $nodes = $xpath->query($pattern);
            if ($nodes !== false && $nodes->length > 1) {
                return $nodes->length;
            }
        }

        return 0;
    }

    /** @return string[] */
    private function detectWarnings(string $html): array
    {
        $warnings = [];
        $htmlLower = strtolower($html);

        foreach (self::WARNING_PATTERNS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($htmlLower, strtolower($keyword))) {
                    $warnings[] = $category;
                    break;
                }
            }
        }

        return array_values(array_unique($warnings));
    }
}
