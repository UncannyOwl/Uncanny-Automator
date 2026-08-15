<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Rendering\LucideRuntimeInitializer;
use UncannyPageBuilder\Application\Rendering\PublicRuntimeAssetCatalog;
use UncannyPageBuilder\Application\Rendering\PublishedPageAssets;
use UncannyPageBuilder\Domain\Publishing\PageDeactivationFallback;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifact;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\Rendering\StyleElementCss;

/**
 * Appends or replaces one checksummed deactivation fallback suffix.
 */
final class WordPressPublishedFallbackComposer
{
    public function __construct(
        private readonly WordPressPublishedFallbackParser $parser = new WordPressPublishedFallbackParser(),
    ) {}

    public function compose(
        string $activePostContent,
        string $originalPostContent,
        PublishedPageArtifact $storedArtifact,
        PageDeactivationFallback $fallback,
        PublishedPageAssets $resolvedAssets,
    ): string {
        $artifactId = $storedArtifact->id();
        if ($artifactId === null || $artifactId <= 0) {
            throw new \InvalidArgumentException('A stored artifact identity is required to compose the fallback.');
        }

        $this->assertAssetsMatchFallback($fallback, $resolvedAssets);

        $legacyArtifact = $this->parser->isLegacyArtifact($activePostContent);
        $existing = $legacyArtifact ? null : $this->parser->parse($activePostContent);
        if ($existing instanceof WordPressPublishedFallbackContent) {
            if ($existing->originalContent() !== $originalPostContent) {
                throw new InvalidPublishedFallbackContent('The WordPress-owned content prefix changed after adoption.');
            }
        } elseif (!$legacyArtifact && $activePostContent !== $originalPostContent) {
            throw new InvalidPublishedFallbackContent('The WordPress page body changed after adoption.');
        }

        $payload = $this->payload($fallback, $resolvedAssets);
        $signingContent = self::signingContent(
            $fallback->formatVersion(),
            $artifactId,
            $storedArtifact->contentHash(),
            $fallback->contentHash(),
            $storedArtifact->shellMode(),
            $payload,
        );
        $suffixHash = hash('sha256', $signingContent);
        $block = '<!-- wp:html -->' . "\n"
            . self::openingTag(
                $fallback->formatVersion(),
                $artifactId,
                $storedArtifact->contentHash(),
                $fallback->contentHash(),
                $storedArtifact->shellMode(),
                $suffixHash,
            )
            . $payload
            . '</div>' . "\n"
            . '<!-- /wp:html -->';

        $content = $originalPostContent === ''
            ? $block
            : $originalPostContent . WordPressPublishedFallbackParser::SEPARATOR . $block;

        $parsed = $this->parser->parse($content);
        if (
            !$parsed instanceof WordPressPublishedFallbackContent
            || $parsed->originalContent() !== $originalPostContent
            || $parsed->artifactId() !== $artifactId
            || !hash_equals($parsed->artifactHash(), $storedArtifact->contentHash())
            || !hash_equals($parsed->fallbackHash(), $fallback->contentHash())
            || $parsed->shellMode() !== $storedArtifact->shellMode()
        ) {
            throw new InvalidPublishedFallbackContent('The composed Page Builder fallback could not be verified.');
        }

        return $content;
    }

    public static function signingContent(
        int $version,
        int $artifactId,
        string $artifactHash,
        string $fallbackHash,
        ShellMode $shellMode,
        string $payload,
    ): string {
        return self::openingTag(
            $version,
            $artifactId,
            $artifactHash,
            $fallbackHash,
            $shellMode,
            '',
        ) . $payload . '</div>';
    }

    private static function openingTag(
        int $version,
        int $artifactId,
        string $artifactHash,
        string $fallbackHash,
        ShellMode $shellMode,
        string $suffixHash,
    ): string {
        return '<div data-uncanny-page-builder-artifact="1"'
            . ' data-upb-fallback-version="' . $version . '"'
            . ' data-upb-artifact-id="' . $artifactId . '"'
            . ' data-upb-artifact-hash="' . $artifactHash . '"'
            . ' data-upb-fallback-hash="' . $fallbackHash . '"'
            . ' data-upb-shell-mode="' . $shellMode->value . '"'
            . ' data-upb-fallback-suffix-hash="' . $suffixHash . '">';
    }

    private function payload(PageDeactivationFallback $fallback, PublishedPageAssets $assets): string
    {
        $styles = '';
        $scripts = '';

        foreach ($assets->all() as $asset) {
            $url = $this->versionedUrl($asset['url'], $asset['sha256']);
            if ($asset['kind'] === 'style') {
                $styles .= "\n<link rel=\"stylesheet\" data-upb-fallback-asset=\""
                    . self::escapeAttribute($asset['name'])
                    . "\" href=\"" . self::escapeAttribute($url) . '">';
                continue;
            }

            // Optional libraries are loaded by the exact published JavaScript
            // bundle. Only the two base runtimes belong directly in the shell.
            if (in_array($asset['name'], ['lucide', 'alpine'], true)) {
                $defer = $asset['name'] === 'alpine' ? ' defer' : '';
                $scripts .= "\n<script data-upb-fallback-asset=\""
                    . self::escapeAttribute($asset['name'])
                    . '" src="' . self::escapeAttribute($url) . '"' . $defer . '></script>';
            }
        }

        $styles .= $this->fontMarkup($assets);
        if ($fallback->css() !== '') {
            $styles .= "\n<style data-upb-fallback-page-css=\"1\">"
                . StyleElementCss::escape($fallback->css())
                . '</style>';
        }

        if ($assets->get('lucide') !== null) {
            $scripts .= "\n<script data-upb-fallback-lucide-init=\"1\">"
                . LucideRuntimeInitializer::script()
                . '</script>';
        }

        $customJavaScript = $assets->resolveReferences($fallback->customJavaScript());
        if ($customJavaScript !== '') {
            $scripts .= "\n" . $customJavaScript;
        }

        return $styles . "\n" . $fallback->html() . $scripts . "\n";
    }

    private function assertAssetsMatchFallback(
        PageDeactivationFallback $fallback,
        PublishedPageAssets $assets,
    ): void {
        $manifest = $fallback->assetsManifest();
        $requested = $manifest['assets'] ?? null;
        if (!is_array($requested) || $requested === [] || array_is_list($requested)) {
            throw new InvalidPublishedFallbackContent('The Page Builder fallback asset manifest is invalid.');
        }

        $requestedNames = array_keys($requested);
        $resolved = $assets->all();
        $resolvedNames = array_keys($resolved);
        sort($requestedNames);
        sort($resolvedNames);
        if ($requestedNames !== $resolvedNames) {
            throw new InvalidPublishedFallbackContent('The resolved Page Builder fallback assets do not match the publication.');
        }

        foreach ($resolved as $name => $asset) {
            $specification = PublicRuntimeAssetCatalog::get($name);
            $url = trim((string) ($asset['url'] ?? ''));
            $scheme = strtolower((string) self::parseUrl($url, PHP_URL_SCHEME));
            if (
                $specification === null
                || ($asset['kind'] ?? null) !== $specification['kind']
                || !in_array($scheme, ['http', 'https'], true)
            ) {
                throw new InvalidPublishedFallbackContent('The Page Builder fallback contains an unapproved runtime asset.');
            }
        }
    }

    private function fontMarkup(PublishedPageAssets $assets): string
    {
        $markup = '';
        $families = [];
        foreach ($assets->googleFonts() as $font) {
            $family = trim((string) ($font['family'] ?? ''));
            if ($family === '') {
                continue;
            }
            $families[] = 'family=' . rawurlencode($family) . ':wght@' . (string) ($font['weights'] ?? '400;700');
        }
        if ($families !== []) {
            $url = 'https://fonts.googleapis.com/css2?' . implode('&', $families) . '&display=swap';
            $markup .= "\n<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">"
                . "\n<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>"
                . "\n<link rel=\"stylesheet\" data-upb-fallback-fonts=\"google\" href=\""
                . self::escapeAttribute($url) . '">';
        }

        $fontCss = '';
        foreach ($assets->customFonts() as $font) {
            $family = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) ($font['family'] ?? ''));
            $url = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) ($font['url'] ?? ''));
            if ($family === '' || $url === '') {
                continue;
            }
            $fontCss .= "@font-face{font-family:'{$family}';font-weight:"
                . (string) ($font['weight'] ?? '400')
                . ";font-style:normal;font-display:swap;src:url('{$url}');}";
        }
        if ($fontCss !== '') {
            $markup .= "\n<style data-upb-fallback-fonts=\"custom\">" . $fontCss . '</style>';
        }

        return $markup;
    }

    private function versionedUrl(string $url, string $hash): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'ver=' . rawurlencode($hash);
    }

    private static function parseUrl(string $url, int $component = -1): array|string|int|false|null
    {
        if (function_exists('wp_parse_url')) {
            return wp_parse_url($url, $component);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Standalone fallback tests run without WordPress functions.
        return parse_url($url, $component);
    }

    private static function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
