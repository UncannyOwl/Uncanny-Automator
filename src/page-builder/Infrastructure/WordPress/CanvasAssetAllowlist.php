<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Enforces the asset boundary for canvas editor pages.
 *
 * Canvas pages are rendered through a WordPress request so core, plugins, and
 * themes can enqueue assets. The editor only permits explicit WordPress
 * editor/media handles and Page Builder-owned assets to avoid theme CSS/JS
 * leaking into the editing chrome.
 */
final class CanvasAssetAllowlist
{
    private const ALLOWED_STYLES = [
        'uncanny-page-builder-bridge',
        'admin-bar',
        'wp-emoji-styles',
        'dashicons',
        'buttons',
        'common',
        'forms',
        'wp-base-styles',
        'wp-components',
        'wp-block-editor',
        'wp-edit-blocks',
        'wp-block-library',
        'wp-mediaelement',
        'media-views',
        'imgareaselect',
        'thickbox',
    ];

    private const ALLOWED_SCRIPTS = [
        'uncanny-page-builder-design-lens',
        'uncanny-page-builder-bridge',
        'uncanny-page-builder-alpine',
        'uncanny-page-builder-lucide',
        'admin-bar',
        'react',
        'react-dom',
        'react-jsx-runtime',
        'regenerator-runtime',
        'wp-a11y',
        'wp-api-fetch',
        'wp-blob',
        'wp-block-editor',
        'wp-block-serialization-default-parser',
        'wp-blocks',
        'wp-commands',
        'wp-components',
        'wp-compose',
        'wp-data',
        'wp-date',
        'wp-deprecated',
        'wp-dom',
        'wp-element',
        'wp-escape-html',
        'wp-hooks',
        'wp-html-entities',
        'wp-i18n',
        'wp-is-shallow-equal',
        'wp-keyboard-shortcuts',
        'wp-keycodes',
        'wp-media-utils',
        'wp-notices',
        'wp-preferences',
        'wp-primitives',
        'wp-priority-queue',
        'wp-private-apis',
        'wp-rich-text',
        'wp-style-engine',
        'wp-theme',
        'wp-token-list',
        'wp-upload-media',
        'wp-url',
        'wp-warning',
        'media-upload',
        'media-editor',
        'media-views',
        'media-models',
        'media-audiovideo',
        'media-grid',
        'wp-mediaelement',
        'wp-plupload',
        'jquery',
        'jquery-core',
        'jquery-migrate',
        'jquery-ui-core',
        'jquery-ui-sortable',
        'underscore',
        'backbone',
        'wp-util',
        'wp-backbone',
        'wp-polyfill',
        'hoverintent-js',
        'heartbeat',
    ];

    public function registerPrintGuards(): void
    {
        add_action('wp_print_styles', [$this, 'enforceStyles'], PHP_INT_MAX);
        add_action('wp_print_scripts', [$this, 'enforceScripts'], PHP_INT_MAX);
        add_action('wp_print_footer_scripts', [$this, 'enforceScripts'], PHP_INT_MAX);
    }

    public function enforceStyles(): void
    {
        global $wp_styles;

        if (!$wp_styles instanceof \WP_Styles) {
            return;
        }

        $allowed = $this->allowedStyles();

        foreach ($wp_styles->queue as $handle) {
            if ($this->isAllowedAsset($handle, $wp_styles->registered[$handle] ?? null, $allowed)) {
                continue;
            }

            wp_dequeue_style($handle);
        }
    }

    public function enforceScripts(): void
    {
        global $wp_scripts;

        if (!$wp_scripts instanceof \WP_Scripts) {
            return;
        }

        $allowed = $this->allowedScripts();

        foreach ($wp_scripts->queue as $handle) {
            if ($this->isAllowedAsset($handle, $wp_scripts->registered[$handle] ?? null, $allowed)) {
                continue;
            }

            wp_dequeue_script($handle);
        }
    }

    /**
     * @return list<string>
     */
    private function allowedStyles(): array
    {
        $allowed = apply_filters('uncanny_page_builder_canvas_allowed_styles', self::ALLOWED_STYLES);

        return is_array($allowed) ? array_values(array_unique(array_filter($allowed, 'is_string'))) : self::ALLOWED_STYLES;
    }

    /**
     * @return list<string>
     */
    private function allowedScripts(): array
    {
        $allowed = apply_filters('uncanny_page_builder_canvas_allowed_scripts', self::ALLOWED_SCRIPTS);

        return is_array($allowed) ? array_values(array_unique(array_filter($allowed, 'is_string'))) : self::ALLOWED_SCRIPTS;
    }

    /**
     * @param list<string> $allowed
     */
    private function isAllowedAsset(string $handle, mixed $dependency, array $allowed): bool
    {
        if (in_array($handle, $allowed, true)) {
            return true;
        }

        return $this->isPluginAsset($dependency);
    }

    private function isPluginAsset(mixed $dependency): bool
    {
        if (!is_object($dependency) || !isset($dependency->src) || !is_string($dependency->src)) {
            return false;
        }

        $base = $this->pluginAssetBase();
        $src = $this->normalizeAssetSource(trim($dependency->src), $base);

        return $src !== '' && str_starts_with($src, $base);
    }

    private function pluginAssetBase(): string
    {
        $base = \defined('UNCANNY_PB_URL')
            ? (string) \constant('UNCANNY_PB_URL')
            : '/wp-content/plugins/uncanny-page-builder/';

        return rtrim($base, '/') . '/';
    }

    private function normalizeAssetSource(string $src, string $base): string
    {
        if ($src === '') {
            return '';
        }

        if (str_starts_with($src, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME);
            return ($scheme ?: 'https') . ':' . $src;
        }

        if (str_starts_with($src, '/') && preg_match('#^https?://#i', $base) === 1) {
            $scheme = parse_url($base, PHP_URL_SCHEME);
            $host = parse_url($base, PHP_URL_HOST);
            if (!is_string($scheme) || !is_string($host)) {
                return $src;
            }

            $port = parse_url($base, PHP_URL_PORT);
            $origin = $scheme . '://' . $host . (is_int($port) ? ':' . $port : '');
            return $origin . $src;
        }

        return $src;
    }
}
