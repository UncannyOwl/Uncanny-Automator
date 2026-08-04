<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

/**
 * Release-owned catalog for every runtime asset a published page may request.
 *
 * Artifacts persist catalog names only. Paths belong to the installed plugin
 * release so a file rename or runtime update never requires artifact migration.
 */
final class PublicRuntimeAssetCatalog
{
    /**
     * @var array<string, array{
     *     kind: string,
     *     plugin_path: string,
     *     export_source_path: string,
     *     reference: string,
     *     mime_type: string,
     *     required: bool,
     *     library_slug: string
     * }>
     */
    private const ASSETS = [
        'bootstrap' => [
            'kind' => 'style',
            'plugin_path' => 'assets/css/bootstrap-scoped.min.css',
            'export_source_path' => 'assets/css/bootstrap.min.css',
            'reference' => 'assets/bootstrap.min.css',
            'mime_type' => 'text/css; charset=utf-8',
            'required' => true,
            'library_slug' => '',
        ],
        'bootstrap_spacing' => [
            'kind' => 'style',
            'plugin_path' => 'assets/css/bootstrap-extended-spacing-scoped.css',
            'export_source_path' => 'assets/css/bootstrap-extended-spacing.css',
            'reference' => 'assets/bootstrap-extended-spacing.css',
            'mime_type' => 'text/css; charset=utf-8',
            'required' => true,
            'library_slug' => '',
        ],
        'lucide' => [
            'kind' => 'script',
            'plugin_path' => 'assets/js/lucide.min.js',
            'export_source_path' => 'assets/js/lucide.min.js',
            'reference' => 'assets/lucide.min.js',
            'mime_type' => 'application/javascript',
            'required' => true,
            'library_slug' => '',
        ],
        'alpine' => [
            'kind' => 'script',
            'plugin_path' => 'assets/js/alpine.min.js',
            'export_source_path' => 'assets/js/alpine.min.js',
            'reference' => 'assets/alpine.min.js',
            'mime_type' => 'application/javascript',
            'required' => true,
            'library_slug' => '',
        ],
        'anime' => [
            'kind' => 'script',
            'plugin_path' => 'assets/js/anime.min.js',
            'export_source_path' => 'assets/js/anime.min.js',
            'reference' => 'assets/anime.min.js',
            'mime_type' => 'application/javascript',
            'required' => false,
            'library_slug' => 'anime',
        ],
        'swiper' => [
            'kind' => 'script',
            'plugin_path' => 'assets/js/swiper-bundle.min.js',
            'export_source_path' => 'assets/js/swiper-bundle.min.js',
            'reference' => 'assets/swiper-bundle.min.js',
            'mime_type' => 'application/javascript',
            'required' => false,
            'library_slug' => 'swiper',
        ],
        'swiper_styles' => [
            'kind' => 'style',
            'plugin_path' => 'assets/css/swiper-bundle.min.css',
            'export_source_path' => 'assets/css/swiper-bundle.min.css',
            'reference' => 'assets/swiper-bundle.min.css',
            'mime_type' => 'text/css; charset=utf-8',
            'required' => false,
            'library_slug' => 'swiper',
        ],
    ];

    /** @return array<string, array{kind: string, plugin_path: string, export_source_path: string, reference: string, mime_type: string, required: bool, library_slug: string}> */
    public static function all(): array
    {
        return self::ASSETS;
    }

    /** @return array{kind: string, plugin_path: string, export_source_path: string, reference: string, mime_type: string, required: bool, library_slug: string}|null */
    public static function get(string $name): ?array
    {
        return self::ASSETS[$name] ?? null;
    }

    /** @return array<string, array{kind: string, plugin_path: string, export_source_path: string, reference: string, mime_type: string, required: bool, library_slug: string}> */
    public static function required(): array
    {
        return array_filter(
            self::ASSETS,
            static fn(array $asset): bool => $asset['required'],
        );
    }

    /** @return array<string, array{kind: string, plugin_path: string, export_source_path: string, reference: string, mime_type: string, required: bool, library_slug: string}> */
    public static function forLibrary(string $librarySlug): array
    {
        return array_filter(
            self::ASSETS,
            static fn(array $asset): bool => $asset['library_slug'] === $librarySlug,
        );
    }
}
