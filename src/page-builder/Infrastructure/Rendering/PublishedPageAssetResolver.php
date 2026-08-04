<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Application\Rendering\PublicRuntimeAssetCatalog;
use UncannyPageBuilder\Application\Rendering\PublishedPageAssetResolverInterface;
use UncannyPageBuilder\Application\Rendering\PublishedPageAssets;
use UncannyPageBuilder\Application\Rendering\PublishedPageRuntimeUnavailable;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifact;

/**
 * Maps an artifact's approved asset requirements to the installed plugin files.
 *
 * Page artifacts preserve published content. Runtime CSS and JavaScript follow
 * the installed plugin release so an asset update is deployed once, in place.
 */
final class PublishedPageAssetResolver implements PublishedPageAssetResolverInterface
{
    public function __construct(
        private readonly string $pluginPath,
        private readonly string $pluginUrl,
    ) {}

    public function resolve(PublishedPageArtifact $artifact): PublishedPageAssets
    {
        $manifest = $artifact->assetsManifest();
        $records = $manifest['assets'] ?? null;

        if (!is_array($records) || $records === [] || array_is_list($records)) {
            throw $this->unavailable('runtime_manifest_invalid', 'The published artifact runtime manifest is invalid.');
        }

        foreach (PublicRuntimeAssetCatalog::required() as $name => $_specification) {
            if (!isset($records[$name])) {
                throw $this->unavailable('runtime_asset_missing', 'A required published runtime asset is missing.');
            }
        }

        $assets = [];
        foreach ($records as $name => $record) {
            $specification = is_string($name) ? PublicRuntimeAssetCatalog::get($name) : null;
            if ($specification === null || !is_array($record)) {
                throw $this->unavailable('runtime_asset_unknown', 'The published artifact requests an unknown runtime asset.');
            }

            /*
             * The artifact authorizes a catalog name, never a release path.
             * Legacy record fields are intentionally ignored: installed files
             * and references always come from the current plugin release.
             */
            $path = rtrim($this->pluginPath, '/') . '/' . $specification['plugin_path'];
            if (!is_file($path)) {
                throw $this->unavailable('runtime_asset_unavailable', 'A required plugin runtime asset is unavailable.');
            }

            $hash = hash_file('sha256', $path);
            if (!is_string($hash)) {
                throw $this->unavailable('runtime_asset_unreadable', 'A required plugin runtime asset cannot be read.');
            }

            $assets[$name] = [
                'name' => $name,
                'kind' => $specification['kind'],
                'path' => $specification['plugin_path'],
                'url' => rtrim($this->pluginUrl, '/') . '/' . $specification['plugin_path'],
                'sha256' => $hash,
                'reference' => $specification['reference'],
            ];
        }

        [$googleFonts, $customFonts] = $this->fonts($manifest['fonts'] ?? []);

        return new PublishedPageAssets(
            assets: $assets,
            googleFonts: $googleFonts,
            customFonts: $customFonts,
        );
    }

    /**
     * @param mixed $manifest
     * @return array{0: list<array{family: string, weights: string}>, 1: list<array{family: string, weight: string, url: string}>}
     */
    private function fonts(mixed $manifest): array
    {
        if (!is_array($manifest) || ($manifest !== [] && array_is_list($manifest))) {
            throw $this->unavailable('font_manifest_invalid', 'The published font manifest is invalid.');
        }

        $google = [];
        foreach (($manifest['google'] ?? []) as $font) {
            if (!is_array($font)) {
                throw $this->unavailable('font_manifest_invalid', 'A published Google Font entry is invalid.');
            }
            $family = $this->fontFamily((string) ($font['family'] ?? ''));
            if ($family === '') {
                continue;
            }
            $weights = trim((string) ($font['weights'] ?? ''));
            $google[] = [
                'family' => $family,
                'weights' => preg_match('/^[0-9;]+$/', $weights) === 1 ? $weights : '400;700',
            ];
        }

        $custom = [];
        foreach (($manifest['custom'] ?? []) as $font) {
            if (!is_array($font)) {
                throw $this->unavailable('font_manifest_invalid', 'A published custom font entry is invalid.');
            }
            $family = $this->fontFamily((string) ($font['family'] ?? ''));
            $url = trim((string) ($font['url'] ?? ''));
            if ($family === '' || !$this->isPublicUrl($url)) {
                continue;
            }
            $weight = preg_replace('/\s+/', ' ', trim((string) ($font['weight'] ?? '400'))) ?? '';
            $custom[] = [
                'family' => $family,
                'weight' => preg_match('/^[1-9]00(?: [1-9]00)?$/', $weight) === 1 ? $weight : '400',
                'url' => $url,
            ];
        }

        return [$google, $custom];
    }

    private function fontFamily(string $family): string
    {
        $family = preg_replace('/[\x00-\x1F\x7F]/', ' ', $family) ?? '';
        $family = preg_replace('/[;{}"\'\\\\]/', '', $family) ?? '';

        return preg_replace('/\s+/', ' ', trim($family)) ?? '';
    }

    private function isPublicUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20]/', $url) === 1) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function unavailable(string $reasonCode, string $message): PublishedPageRuntimeUnavailable
    {
        return new PublishedPageRuntimeUnavailable($reasonCode, $message);
    }
}
