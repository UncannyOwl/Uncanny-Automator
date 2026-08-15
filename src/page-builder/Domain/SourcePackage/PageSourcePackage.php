<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\SourcePackage;

use UncannyPageBuilder\Domain\DesignStandards\PageDesignOverrides;
use UncannyPageBuilder\Domain\DesignStyles\ElementStyleRule;

/**
 * Portable Page Builder source package for a page.
 *
 * The package is intentionally Page Builder-owned source only. WordPress post
 * title/status and global-part relationships need separate conflict policies
 * before they can safely move between sites.
 */
final class PageSourcePackage
{
    public const SCHEMA_VERSION          = 'upb.page_source.v1';
    public const PACKAGE_TYPE            = 'page';
    public const MAX_PAGE_SOURCE_BYTES   = 5242880;
    public const MAX_MANIFEST_BYTES      = 262144;
    public const MAX_ARCHIVE_BYTES       = 104857600;
    public const MAX_IMAGE_BYTES         = 20971520;
    public const MAX_TOTAL_IMAGE_BYTES   = 104857600;
    public const MAX_IMAGES              = 100;
    private const MAX_SECTIONS = 100;
    private const MAX_SECTION_HTML_BYTES = 2097152;
    private const MAX_SECTION_CSS_BYTES = 1048576;
    private const MAX_CUSTOM_JAVASCRIPT_BYTES = 1048576;
    private const MAX_ELEMENT_STYLE_RULES = 500;

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $source
     */
    private function __construct(
        private readonly array $page,
        private readonly array $source,
        private readonly string $exportedAt,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed> $designOverrides
     */
    public static function fromPage(
        int $pageId,
        array $sections,
        array $designOverrides,
        string $customJavaScript,
        string $exportedAt,
    ): self {
        $package = new self(
            page: [
                'sections' => self::normalizeSections($sections, true),
                'design_overrides' => $designOverrides,
                'custom_javascript' => $customJavaScript,
            ],
            source: [
                'page_id' => $pageId,
            ],
            exportedAt: $exportedAt,
        );

        $package->assertSerializedSize();

        return $package;
    }

    /**
     * Build a page package from an uploaded file before any WordPress page is
     * created. Imported sections deliberately drop source IDs so a package from
     * one site cannot overwrite rows on another site.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromImportPayload(array $payload): self
    {
        if (($payload['schema_version'] ?? null) !== self::SCHEMA_VERSION || ($payload['package_type'] ?? null) !== self::PACKAGE_TYPE) {
            throw new SourcePackageValidationException('Upload a valid Page Builder page JSON export.');
        }

        if (!is_array($payload['page'] ?? null)) {
            throw new SourcePackageValidationException('The page export is missing its page source.');
        }

        $page = $payload['page'];
        if (!array_key_exists('sections', $page) || !is_array($page['sections'])) {
            throw new SourcePackageValidationException('The page export is missing its sections.');
        }

        $designOverrides = $page['design_overrides'] ?? [];
        if (!is_array($designOverrides)) {
            throw new SourcePackageValidationException('The page export contains invalid design overrides.');
        }
        try {
            PageDesignOverrides::fromArray($designOverrides);
        } catch (\Throwable $e) {
            throw new SourcePackageValidationException('The page export contains invalid design overrides.', 0, $e);
        }

        $customJavaScript = $page['custom_javascript'] ?? '';
        if (!is_string($customJavaScript) || strlen($customJavaScript) > self::MAX_CUSTOM_JAVASCRIPT_BYTES) {
            throw new SourcePackageValidationException('The page export contains invalid custom JavaScript.');
        }

        $package = new self(
            page: [
                'sections' => self::normalizeSections($page['sections'], false),
                'design_overrides' => $designOverrides,
                'custom_javascript' => $customJavaScript,
            ],
            source: is_array($payload['source'] ?? null) ? $payload['source'] : [],
            exportedAt: self::stringValue($payload['exported_at'] ?? null, ''),
        );

        $package->assertSerializedSize();

        return $package;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'package_type'   => self::PACKAGE_TYPE,
            'exported_at'    => $this->exportedAt,
            'source'         => $this->source,
            'page'           => $this->page,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sectionsForRestore(): array
    {
        return is_array($this->page['sections'] ?? null) ? $this->page['sections'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function designOverrides(): array
    {
        return is_array($this->page['design_overrides'] ?? null) ? $this->page['design_overrides'] : [];
    }

    public function customJavaScript(): string
    {
        return self::stringValue($this->page['custom_javascript'] ?? null, '');
    }

    /**
     * @param array<int, mixed> $sections
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeSections(array $sections, bool $preserveIds): array
    {
        if (count($sections) > self::MAX_SECTIONS) {
            throw new SourcePackageValidationException('A page export can contain at most 100 sections.');
        }

        $normalized = [];

        foreach (array_values($sections) as $position => $section) {
            if (!is_array($section)) {
                throw new SourcePackageValidationException('The page export contains an invalid section.');
            }

            if (!is_array($section['content'] ?? null)) {
                throw new SourcePackageValidationException('Each page section must contain source content.');
            }

            $content = $section['content'];
            $name = $section['name'] ?? 'Section ' . ($position + 1);
            $html = $content['html'] ?? null;
            $css = $content['css'] ?? '';
            if (!is_string($name) || $name === '' || strlen($name) > 255) {
                throw new SourcePackageValidationException('The page export contains an invalid section name.');
            }
            if (!is_string($html) || trim($html) === '' || strlen($html) > self::MAX_SECTION_HTML_BYTES) {
                throw new SourcePackageValidationException('Each page section must contain no more than 2 MB of HTML.');
            }
            if (!is_string($css) || strlen($css) > self::MAX_SECTION_CSS_BYTES) {
                throw new SourcePackageValidationException('Each page section may contain no more than 1 MB of CSS.');
            }

            $elementStyles = $content['element_styles'] ?? [];
            if (!is_array($elementStyles)) {
                throw new SourcePackageValidationException('The page export contains invalid element styles.');
            }
            self::validateElementStyles($elementStyles);

            $normalizedSection = [
                'position' => $position,
                'name'     => $name,
                'content'  => [
                    'html' => $html,
                    'css'  => $css,
                    'element_styles' => $elementStyles,
                ],
            ];

            if ($preserveIds && isset($section['id']) && (int) $section['id'] > 0) {
                $normalizedSection['id'] = (int) $section['id'];
            }

            $normalized[] = $normalizedSection;
        }

        return $normalized;
    }

    /**
     * Reject malformed style records instead of letting ElementStyleSheet
     * silently discard them and report a successful but lower-fidelity import.
     *
     * @param array<string, mixed>|array<int, mixed> $styles
     */
    private static function validateElementStyles(array $styles): void
    {
        if ($styles === []) {
            return;
        }

        if (($styles['version'] ?? null) !== 1 || !is_array($styles['rules'] ?? null)) {
            throw new SourcePackageValidationException('The page export contains unsupported element styles.');
        }

        $rules = $styles['rules'];
        if (count($rules) > self::MAX_ELEMENT_STYLE_RULES) {
            throw new SourcePackageValidationException('A page section can contain at most 500 element style rules.');
        }

        foreach ($rules as $rawRule) {
            if (
                !is_array($rawRule)
                || !is_array($rawRule['declarations'] ?? null)
                || count($rawRule['declarations']) > 100
                || !ElementStyleRule::fromArray($rawRule) instanceof ElementStyleRule
            ) {
                throw new SourcePackageValidationException('The page export contains an invalid element style rule.');
            }

            foreach ($rawRule['declarations'] as $property => $value) {
                if (
                    !is_string($property)
                    || !is_string($value)
                    || trim($property) === ''
                    || strlen($property) > 100
                    || strlen($value) > 4096
                ) {
                    throw new SourcePackageValidationException('The page export contains an invalid element style declaration.');
                }
            }
        }
    }

    private static function stringValue(mixed $value, string $fallback): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $fallback;
    }

    private function assertSerializedSize(): void
    {
        try {
            $serialized = self::encodeJson($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new SourcePackageValidationException('The page source could not be encoded.', 0, $e);
        }

        if (strlen($serialized) > self::MAX_PAGE_SOURCE_BYTES) {
            throw new SourcePackageValidationException(
                'The page source must be 5 MB or smaller. Remove some source content and export again.',
            );
        }
    }

    private static function encodeJson(mixed $value, int $flags = 0): string|false
    {
        // Exact JSON bytes are part of the domain contract; wp_json_encode() may repair invalid UTF-8 and change that output.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This deterministic language operation is not a WordPress capability.
        return json_encode($value, $flags);
    }
}
