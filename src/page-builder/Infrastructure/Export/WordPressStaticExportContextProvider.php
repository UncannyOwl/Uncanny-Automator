<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Export;

use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Domain\Export\StaticExportContextProviderInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelectionRepositoryInterface;
use UncannyPageBuilder\Infrastructure\Persistence\WpPageGlobalPartSelectionRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpSettingsRepository;

/**
 * Adds WordPress-owned inputs to the saved artifact dependency fingerprint.
 */
final class WordPressStaticExportContextProvider implements StaticExportContextProviderInterface
{
    public function __construct(
        private readonly GlobalPartDefaultsService $defaults,
        private readonly ?PageGlobalPartSelectionRepositoryInterface $pageSelections = null,
        private readonly ?WpSettingsRepository $settings = null,
    ) {}

    public function contextForPage(int $pageId, array $sections, ?array $header, ?array $footer): array
    {
        $page = function_exists('get_post') ? get_post($pageId) : null;

        return [
            'page' => [
                'id' => $pageId,
                'title' => is_object($page) ? (string) ($page->post_title ?? '') : '',
                'status' => is_object($page) ? (string) ($page->post_status ?? '') : '',
            ],
            'shell_parts' => [
                'default_header_id' => $this->defaults->getDefaultId(GlobalPartType::Header),
                'default_footer_id' => $this->defaults->getDefaultId(GlobalPartType::Footer),
                'page_header_override_id' => $this->pagePartOverride($pageId, GlobalPartType::Header),
                'page_footer_override_id' => $this->pagePartOverride($pageId, GlobalPartType::Footer),
                'header_id' => is_array($header) ? (int) ($header['post_id'] ?? 0) : null,
                'footer_id' => is_array($footer) ? (int) ($footer['post_id'] ?? 0) : null,
            ],
            'menus' => $this->menuDependencies($sections, $header, $footer),
            'media_urls' => $this->mediaUrls($sections, $header, $footer),
            'font_assets' => $this->fontAssets(),
        ];
    }

    /**
     * Capture font delivery with the artifact instead of consulting mutable
     * brand settings during a public request.
     *
     * @return array{google: list<array{family: string, weights: string}>, custom: list<array{family: string, weight: string, url: string}>}
     */
    private function fontAssets(): array
    {
        if (!$this->settings instanceof WpSettingsRepository) {
            return ['google' => [], 'custom' => []];
        }

        $fonts = $this->settings->load()->brandStyles()->fonts();
        $google = array_map(
            static fn($font): array => $font->toArray(),
            $fonts->googleFonts(),
        );
        $custom = [];

        foreach ($fonts->customFonts() as $font) {
            $url = function_exists('wp_get_attachment_url')
                ? wp_get_attachment_url($font->attachmentId())
                : false;
            if (!is_string($url) || trim($url) === '') {
                continue;
            }

            $custom[] = [
                'family' => $font->family(),
                'weight' => $font->weight(),
                'url' => $url,
            ];
        }

        return [
            'google' => array_values($google),
            'custom' => $custom,
        ];
    }

    private function pagePartOverride(int $pageId, GlobalPartType $type): ?int
    {
        $selection = ($this->pageSelections ?? new WpPageGlobalPartSelectionRepository())->loadForPage($pageId);

        return match ($type) {
            GlobalPartType::Header => $selection->headerOverrideId(),
            GlobalPartType::Footer => $selection->footerOverrideId(),
            default => null,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     * @return array<int, array{location: string, id: int|null}>
     */
    private function menuDependencies(array $sections, ?array $header, ?array $footer): array
    {
        $locations = [];
        $menuIds = [];
        foreach ($this->allHtmlFragments($sections, $header, $footer) as $html) {
            foreach ($this->menuLocationsInHtml($html) as $location) {
                $locations[$location] = true;
            }
            foreach ($this->menuIdsInHtml($html) as $menuId) {
                $menuIds[$menuId] = true;
            }
        }

        if ($locations === [] && $menuIds === []) {
            return [];
        }

        $assigned = function_exists('get_nav_menu_locations') ? get_nav_menu_locations() : [];
        $assigned = is_array($assigned) ? $assigned : [];
        $dependencies = [];

        foreach (array_keys($locations) as $location) {
            $dependencies[] = [
                'location' => $location,
                'id' => isset($assigned[$location]) ? (int) $assigned[$location] : null,
            ];
        }
        foreach (array_keys($menuIds) as $menuId) {
            $dependencies[] = [
                'location' => '',
                'id' => (int) $menuId,
            ];
        }

        usort($dependencies, static function (array $a, array $b): int {
            $locationCompare = $a['location'] <=> $b['location'];
            if ($locationCompare !== 0) {
                return $locationCompare;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $dependencies;
    }

    /**
     * @return string[]
     */
    private function menuLocationsInHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $html . '</body>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $this->menuLocationsInHtmlWithRegex($html);
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//*[@data-ai-dynamic="wp_menu"]');
        if (!$nodes instanceof \DOMNodeList) {
            return [];
        }

        $locations = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $location = trim($node->getAttribute('data-menu-location'));
            if ($location !== '') {
                $locations[$location] = $location;
            }
        }

        return array_values($locations);
    }

    /**
     * @return int[]
     */
    private function menuIdsInHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $html . '</body>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $this->menuIdsInHtmlWithRegex($html);
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//*[@data-ai-dynamic="wp_menu"]');
        if (!$nodes instanceof \DOMNodeList) {
            return [];
        }

        $menuIds = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $menuId = (int) trim($node->getAttribute('data-menu-id'));
            if ($menuId > 0) {
                $menuIds[$menuId] = $menuId;
            }
        }

        return array_values($menuIds);
    }

    /**
     * @return string[]
     */
    private function menuLocationsInHtmlWithRegex(string $html): array
    {
        $count = preg_match_all(
            '/<[^>]*\bdata-ai-dynamic\s*=\s*(["\'])wp_menu\1[^>]*\bdata-menu-location\s*=\s*(["\'])(.*?)\2[^>]*>/i',
            $html,
            $matches,
        );

        if ($count === false || $count === 0) {
            return [];
        }

        $locations = [];
        foreach ($matches[3] as $location) {
            $location = trim((string) $location);
            if ($location !== '') {
                $locations[$location] = $location;
            }
        }

        return array_values($locations);
    }

    /**
     * @return int[]
     */
    private function menuIdsInHtmlWithRegex(string $html): array
    {
        $count = preg_match_all(
            '/<[^>]*\bdata-ai-dynamic\s*=\s*(["\'])wp_menu\1[^>]*\bdata-menu-id\s*=\s*(["\'])(\d+)\2[^>]*>/i',
            $html,
            $matches,
        );

        if ($count === false || $count === 0) {
            return [];
        }

        $menuIds = [];
        foreach ($matches[3] as $menuId) {
            $menuId = (int) $menuId;
            if ($menuId > 0) {
                $menuIds[$menuId] = $menuId;
            }
        }

        return array_values($menuIds);
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     * @return string[]
     */
    private function mediaUrls(array $sections, ?array $header, ?array $footer): array
    {
        $urls = [];
        foreach ($this->allHtmlFragments($sections, $header, $footer) as $html) {
            foreach ($this->urlsInHtml($html) as $url) {
                $urls[$url] = $url;
            }
        }

        foreach ($this->allCssFragments($sections, $header, $footer) as $css) {
            foreach ($this->urlsInCss($css) as $url) {
                $urls[$url] = $url;
            }
        }

        ksort($urls);

        return array_values($urls);
    }

    /**
     * @return string[]
     */
    private function urlsInHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $urls = [];
        $count = preg_match_all('/\s(?:src|href|poster)\s*=\s*(["\'])(.*?)\1/i', $html, $matches);
        if ($count !== false && $count > 0) {
            foreach ($matches[2] as $url) {
                $this->rememberUrl($urls, (string) $url);
            }
        }

        $count = preg_match_all('/\ssrcset\s*=\s*(["\'])(.*?)\1/i', $html, $matches);
        if ($count !== false && $count > 0) {
            foreach ($matches[2] as $srcset) {
                foreach (explode(',', (string) $srcset) as $candidate) {
                    $parts = preg_split('/\s+/', trim($candidate), 2) ?: [];
                    $this->rememberUrl($urls, (string) ($parts[0] ?? ''));
                }
            }
        }

        $count = preg_match_all('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $html, $matches);
        if ($count !== false && $count > 0) {
            foreach ($matches[2] as $css) {
                foreach ($this->urlsInCss((string) $css) as $url) {
                    $urls[$url] = $url;
                }
            }
        }

        return array_values($urls);
    }

    /**
     * @return string[]
     */
    private function urlsInCss(string $css): array
    {
        $urls = [];
        $count = preg_match_all('/url\(\s*(["\']?)(.*?)\1\s*\)/i', $css, $matches);
        if ($count === false || $count === 0) {
            return [];
        }

        foreach ($matches[2] as $url) {
            $this->rememberUrl($urls, (string) $url);
        }

        return array_values($urls);
    }

    /**
     * @param array<string, string> $urls
     */
    private function rememberUrl(array &$urls, string $url): void
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#')) {
            return;
        }

        $urls[$url] = $url;
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     * @return string[]
     */
    private function allHtmlFragments(array $sections, ?array $header, ?array $footer): array
    {
        $fragments = [];
        foreach ([$header, ['sections' => $sections], $footer] as $group) {
            foreach ($this->groupSections($group) as $section) {
                $fragments[] = (string) ($section['content']['html'] ?? '');
            }
        }

        return $fragments;
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     * @return string[]
     */
    private function allCssFragments(array $sections, ?array $header, ?array $footer): array
    {
        $fragments = [];
        foreach ([$header, ['sections' => $sections], $footer] as $group) {
            if (is_array($group)) {
                $fragments[] = (string) ($group['css'] ?? '');
            }
            foreach ($this->groupSections($group) as $section) {
                $fragments[] = (string) ($section['content']['css'] ?? '');
            }
        }

        return $fragments;
    }

    /**
     * @param array<string, mixed>|null $group
     * @return array<int, array<string, mixed>>
     */
    private function groupSections(?array $group): array
    {
        $sections = $group['sections'] ?? [];
        if (!is_array($sections)) {
            return [];
        }

        return array_values(array_filter($sections, 'is_array'));
    }
}
