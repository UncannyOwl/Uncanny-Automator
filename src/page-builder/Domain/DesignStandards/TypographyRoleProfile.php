<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;

/**
 * One typography role, such as Body or Headings.
 *
 * The role stays sparse: only explicitly assigned fields are stored. Sitewide
 * defaults may populate a fuller shape, while page overrides may carry just one
 * or two fields.
 */
final class TypographyRoleProfile
{
    // ── Field Contract ──────────────────────────────────────

    /** @var array<string, string> */
    private const FIELD_ALIASES = [
        'family' => 'font_family',
        'font-family' => 'font_family',
        'fontfamily' => 'font_family',
        'size' => 'font_size',
        'font-size' => 'font_size',
        'fontsize' => 'font_size',
        'weight' => 'font_weight',
        'font-weight' => 'font_weight',
        'fontweight' => 'font_weight',
        'line-height' => 'line_height',
        'lineheight' => 'line_height',
        'style' => 'font_style',
        'font-style' => 'font_style',
        'fontstyle' => 'font_style',
        'letter-spacing' => 'letter_spacing',
        'letterspacing' => 'letter_spacing',
        'spacing' => 'letter_spacing',
        'transform' => 'text_transform',
        'text-transform' => 'text_transform',
        'texttransform' => 'text_transform',
        'color' => 'color',
    ];

    /** @var list<string> */
    private const ALLOWED_FIELDS = [
        'font_family',
        'font_size',
        'font_weight',
        'line_height',
        'font_style',
        'letter_spacing',
        'text_transform',
        'color',
    ];

    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        private readonly array $fields = [],
    ) {}

    /**
     * @param array<string, mixed> $fields
     */
    public static function fromArray(array $fields, string $roleName = 'Typography role'): self
    {
        $normalized = [];

        foreach ($fields as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException("{$roleName} fields must use string keys.");
            }

            $field = self::normalizeFieldName($key);

            if (!is_string($value) && !is_numeric($value)) {
                throw new \InvalidArgumentException("{$roleName} field '{$field}' must be a string value.");
            }

            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            if (!DesignStyleValue::isSafeValue($text)) {
                throw new \InvalidArgumentException("{$roleName} field '{$field}' contains an unsafe CSS value.");
            }

            $normalized[$field] = $text;
        }

        return new self($normalized);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->fields;
    }

    /** @return array<string, string> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function get(string $field): ?string
    {
        return $this->fields[self::normalizeFieldName($field)] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    private static function normalizeFieldName(string $field): string
    {
        $candidate = trim(strtolower($field));
        if ($candidate === '') {
            throw new \InvalidArgumentException('Typography field names cannot be empty.');
        }

        $candidate = self::FIELD_ALIASES[$candidate]
            ?? str_replace('-', '_', preg_replace('/\s+/', '_', $candidate) ?? $candidate);

        if (!in_array($candidate, self::ALLOWED_FIELDS, true)) {
            throw new \InvalidArgumentException("Typography field '{$field}' is not supported.");
        }

        return $candidate;
    }
}
