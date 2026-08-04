<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\SourcePackage;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

/**
 * Portable source package for a Page Builder reusable/global part.
 */
final class ReusableSourcePackage
{
    public const SCHEMA_VERSION = 'upb.global_part_source.v1';
    public const PACKAGE_TYPE = 'global_part';

    /** @var string[] */
    private const ACCEPTED_SCHEMA_VERSIONS = [
        self::SCHEMA_VERSION,
        'upb.reusable_source.v1',
    ];

    /** @var string[] */
    private const ACCEPTED_PACKAGE_TYPES = [
        self::PACKAGE_TYPE,
        'reusable',
    ];

    /**
     * @param array<string, mixed> $section
     */
    private function __construct(
        private readonly string $title,
        private readonly GlobalPartType $type,
        private readonly array $section,
        private readonly string $customJavaScript,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromImportPayload(array $payload, ?GlobalPartType $requiredType = null): self
    {
        if (
            !in_array($payload['schema_version'] ?? null, self::ACCEPTED_SCHEMA_VERSIONS, true)
            || !in_array($payload['package_type'] ?? null, self::ACCEPTED_PACKAGE_TYPES, true)
        ) {
            throw new SourcePackageValidationException('Upload a valid Page Builder reusable JSON export.');
        }

        $source = self::sourceObject($payload);
        $rawType = self::stringValue($source['type'] ?? null, '');
        if (!in_array($rawType, GlobalPartType::validValues(), true)) {
            throw new SourcePackageValidationException('The reusable export is missing a valid reusable type.');
        }

        $type = GlobalPartType::fromString($rawType);
        if ($requiredType !== null && $type !== $requiredType) {
            throw new SourcePackageValidationException('Upload a reusable section JSON export.');
        }

        $section = self::sectionObject($source);
        $content = is_array($section['content'] ?? null) ? $section['content'] : [];
        if (trim(self::stringValue($content['html'] ?? null, '')) === '') {
            throw new SourcePackageValidationException('The reusable export is missing section HTML.');
        }

        return new self(
            title: self::stringValue($source['title'] ?? null, 'Imported reusable'),
            type: $type,
            section: self::normalizeSection($section),
            customJavaScript: self::stringValue($source['custom_javascript'] ?? null, ''),
        );
    }

    public function title(): string
    {
        return $this->title !== '' ? $this->title : 'Imported reusable';
    }

    public function type(): GlobalPartType
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function section(): array
    {
        return $this->section;
    }

    public function customJavaScript(): string
    {
        return $this->customJavaScript;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function sourceObject(array $payload): array
    {
        if (is_array($payload['global_part'] ?? null)) {
            return $payload['global_part'];
        }

        if (is_array($payload['reusable'] ?? null)) {
            return $payload['reusable'];
        }

        throw new SourcePackageValidationException('The reusable export is missing its source.');
    }

    /**
     * Reusable exports may store their single source section as "section" or as
     * the first item in "sections"; both are normalized to the canonical section
     * object used by GlobalPartService::create().
     *
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function sectionObject(array $source): array
    {
        if (is_array($source['section'] ?? null)) {
            return $source['section'];
        }

        if (is_array($source['sections'] ?? null) && is_array($source['sections'][0] ?? null)) {
            return $source['sections'][0];
        }

        if (is_array($source['content'] ?? null)) {
            return [
                'name' => self::stringValue($source['title'] ?? null, 'Imported reusable'),
                'content' => $source['content'],
            ];
        }

        throw new SourcePackageValidationException('The reusable export is missing its source section.');
    }

    /**
     * @param array<string, mixed> $section
     * @return array<string, mixed>
     */
    private static function normalizeSection(array $section): array
    {
        $content = is_array($section['content'] ?? null) ? $section['content'] : [];

        return [
            'name' => self::stringValue($section['name'] ?? null, 'Imported reusable'),
            'content' => [
                'html' => self::stringValue($content['html'] ?? null, ''),
                'css' => self::stringValue($content['css'] ?? null, ''),
                'element_styles' => is_array($content['element_styles'] ?? null)
                    ? $content['element_styles']
                    : [],
            ],
        ];
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
}
