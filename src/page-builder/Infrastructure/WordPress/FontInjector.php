<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;

/**
 * Keeps working fonts on authenticated canvases and exact artifact fonts on
 * public pages. The two lanes are deliberately selected by separate methods.
 */
final class FontInjector
{
    public function __construct(
        private readonly PublicPageRenderPolicy $publicPageRenderPolicy,
        private readonly WordPressFontSettings $workingFonts,
    ) {}

    /**
     * Exact-pointer font lane. Register only with the Phase 5 cutover.
     */
    public function injectPublished(): void
    {
        if (is_admin() || !is_singular() || is_singular('upb_global_part')) {
            return;
        }

        try {
            $postId = WordPressPostId::fromCurrentQuery(get_queried_object_id());
            if ($postId === null) {
                return;
            }

            $publishedPage = $this->publicPageRenderPolicy->publishedPage($postId);
            if ($publishedPage === null) {
                return;
            }

            $this->injectGoogleFonts($publishedPage->assets()->googleFonts());
            $this->injectCustomFonts($publishedPage->assets()->customFonts());
        } catch (\Throwable $failure) {
            // wp_head is a shared WordPress surface. A Page Builder failure
            // must not terminate the visitor request.
            error_log('[Uncanny Page Builder] Published font injection failed (' . $failure::class . ')');
        }
    }

    /**
     * Working font lane for page editors and reusable canvases.
     */
    public function injectWorking(): void
    {
        if (!is_admin() && !is_singular('upb_global_part')) {
            return;
        }

        try {
            $this->injectGoogleFonts($this->workingFonts->googleFonts());
            $this->injectCustomFonts($this->workingFonts->renderableCustomFonts());
        } catch (\Throwable $failure) {
            // wp_head is a shared WordPress surface. A Page Builder failure
            // must not terminate the editor request.
            error_log('[Uncanny Page Builder] Working font injection failed (' . $failure::class . ')');
        }
    }

    /** @param list<array{family: string, weights: string}> $families */
    private function injectGoogleFonts(array $families): void
    {
        if ($families === []) {
            return;
        }

        $params = [];
        foreach ($families as $entry) {
            $family = $entry['family'] ?? '';
            if ($family === '') {
                continue;
            }
            $weights = self::sanitizeGoogleFontWeights((string) ($entry['weights'] ?? ''));
            $params[] = 'family=' . rawurlencode($family) . ':wght@' . $weights;
        }

        if ($params === []) {
            return;
        }

        $url = 'https://fonts.googleapis.com/css2?' . implode('&', $params) . '&display=swap';

        $filteredUrl = apply_filters('uncanny_page_builder_google_fonts_url', $url, $families);
        if (is_string($filteredUrl)) {
            $url = $filteredUrl;
        }

        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="stylesheet" href="' . esc_url($url) . '">' . "\n";
    }

    /** @param list<array{family: string, weight: string, url: string}> $fonts */
    private function injectCustomFonts(array $fonts): void
    {
        if ($fonts === []) {
            return;
        }

        $css = '';
        foreach ($fonts as $font) {
            $family = self::sanitizeCustomFontFamily((string) ($font['family'] ?? ''));
            $weight = self::sanitizeCustomFontWeight($font['weight'] ?? '400');
            $url = trim((string) ($font['url'] ?? ''));

            if ($family === '' || $url === '') {
                continue;
            }

            $format = self::detectFontFormat($url);
            $safeUrl = self::escapeCssString((string) esc_url_raw($url));

            $css .= '@font-face{';
            $css .= "font-family:'" . $family . "';";
            $css .= 'font-weight:' . $weight . ';';
            $css .= 'font-style:normal;';
            $css .= 'font-display:swap;';
            $css .= "src:url('" . $safeUrl . "') format('" . $format . "');";
            $css .= '}';
        }

        if ($css !== '') {
            $filteredCss = apply_filters('uncanny_page_builder_custom_fonts_css', $css, $fonts);
            if (is_string($filteredCss)) {
                $css = $filteredCss;
            }

            echo '<style id="uncanny-page-builder-custom-fonts">' . $css . "</style>\n";
        }
    }

    private static function detectFontFormat(string $url): string
    {
        $ext = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return match ($ext) {
            'woff2' => 'woff2',
            'woff'  => 'woff',
            'ttf'   => 'truetype',
            'otf'   => 'opentype',
            default => 'woff2',
        };
    }

    /**
     * Weights format is semicolon-separated integers (e.g. "400;700");
     * anything else falls back to the rendered default.
     */
    public static function sanitizeGoogleFontWeights(string $weights): string
    {
        return preg_match('/^[0-9;]+$/', $weights) === 1 ? $weights : '400;700';
    }

    public static function sanitizeCustomFontFamily(string $family): string
    {
        $family = preg_replace('/[\x00-\x1F\x7F]/', ' ', $family) ?? '';
        $family = preg_replace('/[;{}"\'\\\\]/', '', $family) ?? '';
        $family = preg_replace('/\s+/', ' ', trim($family)) ?? '';

        return $family;
    }

    public static function sanitizeCustomFontWeight(string|int|float $weight): string
    {
        $weight = preg_replace('/\s+/', ' ', trim((string) $weight)) ?? '';

        return preg_match('/^[1-9]00(?: [1-9]00)?$/', $weight) === 1 ? $weight : '400';
    }

    private static function escapeCssString(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
