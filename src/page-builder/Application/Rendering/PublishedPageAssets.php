<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

/**
 * Validated public URLs and font inputs from the installed plugin runtime.
 */
final class PublishedPageAssets
{
    /**
     * @param array<string, array{name: string, kind: string, path: string, url: string, sha256: string, reference: string}> $assets
     * @param list<array{family: string, weights: string}> $googleFonts
     * @param list<array{family: string, weight: string, url: string}> $customFonts
     */
    public function __construct(
        private readonly array $assets,
        private readonly array $googleFonts = [],
        private readonly array $customFonts = [],
    ) {
        foreach ($assets as $name => $asset) {
            if (!is_string($name) || $name === '' || ($asset['name'] ?? '') !== $name) {
                throw new \InvalidArgumentException('Published runtime asset names must be explicit.');
            }
            if (!in_array($asset['kind'] ?? '', ['style', 'script'], true)) {
                throw new \InvalidArgumentException('Published runtime asset kind is invalid.');
            }
            if (trim((string) ($asset['path'] ?? '')) === '' || trim((string) ($asset['url'] ?? '')) === '') {
                throw new \InvalidArgumentException('Published runtime asset paths are required.');
            }
            if (preg_match('/^[a-f0-9]{64}$/', (string) ($asset['sha256'] ?? '')) !== 1) {
                throw new \InvalidArgumentException('Published runtime asset hash is invalid.');
            }
        }
    }

    /**
     * @return array<string, array{name: string, kind: string, path: string, url: string, sha256: string, reference: string}>
     */
    public function all(): array
    {
        return $this->assets;
    }

    /**
     * @return array{name: string, kind: string, path: string, url: string, sha256: string, reference: string}|null
     */
    public function get(string $name): ?array
    {
        return $this->assets[$name] ?? null;
    }

    /** @return list<array{family: string, weights: string}> */
    public function googleFonts(): array
    {
        return $this->googleFonts;
    }

    /** @return list<array{family: string, weight: string, url: string}> */
    public function customFonts(): array
    {
        return $this->customFonts;
    }

    public function resolveReferences(string $content): string
    {
        $references = [];

        foreach ($this->assets as $asset) {
            $reference = (string) ($asset['reference'] ?? '');
            if ($reference !== '') {
                $url = (string) $asset['url'];
                $separator = str_contains($url, '?') ? '&' : '?';
                $references[$reference] = $url
                    . $separator
                    . 'ver='
                    . rawurlencode((string) $asset['sha256']);
            }
        }

        return $references === [] ? $content : strtr($content, $references);
    }
}
