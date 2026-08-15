<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionBindingContract;
use UncannyPageBuilder\Domain\Section\SectionBindingContractInspectorInterface;
use UncannyPageBuilder\Domain\Section\SectionManifestExtractorInterface;

/**
 * Derives section-local binding contracts from the canonical manifest extractor.
 */
final class DomSectionBindingContractInspector implements SectionBindingContractInspectorInterface
{
    public function __construct(
        private readonly SectionManifestExtractorInterface $manifestExtractor,
        private readonly BindingRegistry $registry,
    ) {}

    public function inspect(Section $section): array
    {
        $manifest = $this->manifestExtractor->extract($section)->toArray();
        $contracts = [];

        foreach ($manifest['dynamic_regions'] ?? [] as $region) {
            if (!is_array($region)) {
                continue;
            }

            $source = (string) ($region['source'] ?? '');

            // wp_menu uses wp_nav_menu() at runtime — the stored card template
            // is never rendered, so binding replacement is not meaningful.
            if ($source === 'wp_menu') {
                continue;
            }
            $path = (string) ($region['path'] ?? '');

            $queryAttributes = $this->extractQueryAttributes($region);
            $bindKeys = array_values(array_map('strval', $region['bind_keys'] ?? []));
            $bindings = is_array($region['bindings'] ?? null) ? $region['bindings'] : [];
            $templateHtml = (string) ($region['card_template_html'] ?? '');

            // Sort for deterministic hashing regardless of DOM traversal order.
            sort($bindKeys);
            usort($bindings, static fn(array $a, array $b): int =>
                ($a['key'] ?? '') <=> ($b['key'] ?? ''));
            ksort($queryAttributes);

            $bindingId = $source . ':' . $path;
            $contractHash = sha1(self::encodeJson([
                'source' => $source,
                'path' => $path,
                'query_attributes' => $queryAttributes,
                'bind_keys' => $bindKeys,
                'bindings' => $bindings,
                'template_html' => $templateHtml,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $contracts[] = new SectionBindingContract(
                bindingId: $bindingId,
                source: $source,
                path: $path,
                queryAttributes: $queryAttributes,
                bindKeys: $bindKeys,
                bindings: $bindings,
                templateHtml: $templateHtml,
                contractHash: $contractHash,
            );
        }

        return $contracts;
    }

    /**
     * @param array<string, mixed> $region
     * @return array<string, bool|int|string>
     */
    private function extractQueryAttributes(array $region): array
    {
        $source = (string) ($region['source'] ?? '');

        // Registry stores declaration attribute names (e.g. "data-post-type").
        // The manifest extractor normalises them to snake_case keys (e.g. "post_type").
        // Apply the same conversion so we can look up region values.
        $normalizedKeys = [];
        foreach ($this->registry->queryAttributesForSource($source) as $attr) {
            $normalizedKeys[] = str_replace('-', '_', preg_replace('/^data-/', '', $attr) ?? $attr);
        }

        $queryAttributes = [];
        foreach ($normalizedKeys as $key) {
            if (array_key_exists($key, $region) && $region[$key] !== null && $region[$key] !== '') {
                $queryAttributes[$key] = is_bool($region[$key]) || is_int($region[$key])
                    ? $region[$key]
                    : (string) $region[$key];
            }
        }

        return $queryAttributes;
    }

    private static function encodeJson(mixed $value, int $flags = 0): string
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($value, $flags);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone section tests run without WordPress functions.
        return json_encode($value, $flags);
    }
}
