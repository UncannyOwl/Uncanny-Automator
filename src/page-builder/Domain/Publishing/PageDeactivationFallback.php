<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

use UncannyPageBuilder\Domain\Exception\DeactivationFallbackCompilationFailed;

/**
 * Immutable output that WordPress can retain for plugin deactivation.
 *
 * The HTML contains no server-rendered binding regions. CSS, approved client
 * JavaScript, and the runtime asset manifest remain pinned to the publication.
 */
final class PageDeactivationFallback
{
    public const FORMAT_VERSION = 1;

    public const DEPENDENCY_HASH_KEY = 'deactivation_fallback_sha256';

    /** @param array<string, mixed> $assetsManifest */
    public function __construct(
        private readonly string $html,
        private readonly string $css,
        private readonly string $customJavaScript,
        private readonly array $assetsManifest,
        private readonly array $omissions,
    ) {
        if ($assetsManifest !== [] && array_is_list($assetsManifest)) {
            throw new \InvalidArgumentException('Page deactivation fallback asset manifest must be an associative array.');
        }
        $this->assertSafeHtml($html);
        if (!array_is_list($omissions)) {
            throw new \InvalidArgumentException('Page deactivation fallback omission report must be a list.');
        }
        foreach ($omissions as $record) {
            if (
                !is_array($record)
                || !in_array($record['source'] ?? null, ['header', 'page', 'footer'], true)
                || !is_int($record['section_id'] ?? null)
                || !is_string($record['binding_id'] ?? null)
            ) {
                throw new \InvalidArgumentException('Page deactivation fallback omission report is invalid.');
            }
        }
    }

    public function html(): string
    {
        return $this->html;
    }

    public function formatVersion(): int
    {
        return self::FORMAT_VERSION;
    }

    public function css(): string
    {
        return $this->css;
    }

    public function customJavaScript(): string
    {
        return $this->customJavaScript;
    }

    /** @return array<string, mixed> */
    public function assetsManifest(): array
    {
        return $this->assetsManifest;
    }

    public function contentHash(): string
    {
        $assetsManifest = $this->assetsManifest;
        $this->sortRecursively($assetsManifest);

        return hash('sha256', self::encodeJson(
            [
                'format_version' => self::FORMAT_VERSION,
                'html' => $this->html,
                'css' => $this->css,
                'custom_javascript' => $this->customJavaScript,
                'assets_manifest' => $assetsManifest,
                'omission_report' => $this->omissionReport(),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /** @return list<array{source: string, section_id: int, binding_id: string}> */
    public function omissions(): array
    {
        return $this->omissions;
    }

    /**
     * @return array{detected_count: int, removed_count: int, regions: list<array{source: string, section_id: int, binding_id: string}>}
     */
    public function omissionReport(): array
    {
        $count = count($this->omissions);

        return [
            'detected_count' => $count,
            'removed_count' => $count,
            'regions' => $this->omissions,
        ];
    }

    /**
     * @return array{format_version: int, html: string, css: string, custom_javascript: string, assets_manifest: array<string, mixed>, omission_report: array{detected_count: int, removed_count: int, regions: list<array{source: string, section_id: int, binding_id: string}>}, sha256: string}
     */
    public function toArray(): array
    {
        return [
            'format_version' => self::FORMAT_VERSION,
            'html' => $this->html,
            'css' => $this->css,
            'custom_javascript' => $this->customJavaScript,
            'assets_manifest' => $this->assetsManifest,
            'omission_report' => $this->omissionReport(),
            'sha256' => $this->contentHash(),
        ];
    }

    /** @param mixed $value */
    private function sortRecursively(&$value): void
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as &$child) {
            $this->sortRecursively($child);
        }
        unset($child);

        if (!array_is_list($value)) {
            ksort($value);
        }
    }

    private function assertSafeHtml(string $html): void
    {
        if (str_contains($html, "\0")) {
            throw new DeactivationFallbackCompilationFailed('Page deactivation fallback contains invalid HTML bytes.');
        }
        if (stripos($html, 'data-ai-dynamic') !== false) {
            throw new DeactivationFallbackCompilationFailed('Page deactivation fallback contains dynamic binding markers.');
        }
        if (preg_match('/<script\b/i', $html) === 1) {
            throw new DeactivationFallbackCompilationFailed(
                'Page deactivation fallback contains a script outside the approved JavaScript lane.',
            );
        }

        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        if (!$loaded) {
            throw new DeactivationFallbackCompilationFailed('Page deactivation fallback HTML could not be parsed.');
        }

        $xpath = new \DOMXPath($document);
        $outer = $xpath->query('//*[@id="uncanny-pb-canvas-root"]');
        $inner = $xpath->query('//*[@id="uncanny-pb-canvas"]');
        if (
            $outer === false
            || $inner === false
            || $outer->length !== 1
            || $inner->length !== 1
            || !$outer->item(0) instanceof \DOMElement
            || !$inner->item(0) instanceof \DOMElement
            || !$this->isDescendantOf($inner->item(0), $outer->item(0))
        ) {
            throw new DeactivationFallbackCompilationFailed('Page deactivation fallback HTML structure is invalid.');
        }
    }

    private function isDescendantOf(\DOMNode $node, \DOMNode $ancestor): bool
    {
        for ($parent = $node->parentNode; $parent instanceof \DOMNode; $parent = $parent->parentNode) {
            if ($parent === $ancestor) {
                return true;
            }
        }

        return false;
    }

    private static function encodeJson(mixed $value, int $flags = 0): string|false
    {
        // Exact JSON bytes are part of the domain hash; wp_json_encode() may repair invalid UTF-8 and change the digest.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This deterministic language operation is not a WordPress capability.
        return json_encode($value, $flags);
    }
}
