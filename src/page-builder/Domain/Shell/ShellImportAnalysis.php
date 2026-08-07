<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Shell;

/**
 * Value object describing detected theme shell elements.
 * Used by RenderedShellAnalyzer to produce structured data
 * that ShellImportService rebuilds into clean Uncanny global parts.
 */
final class ShellImportAnalysis
{
    /**
     * @param string $type                           'header' or 'footer'
     * @param ?string $logoUrl                       Detected logo image URL
     * @param ?string $logoText                      Detected logo/brand text
     * @param array<int, array{label: string, href: string}> $navLinks  Detected nav links
     * @param array<int, array{label: string, href: string}> $ctaLinks  Detected CTA links
     * @param string[] $socialLinks                  Detected social profile URLs
     * @param ?string $copyrightText                 Detected copyright text
     * @param int $footerColumns                     Number of footer columns detected
     * @param string[] $warnings                     Unsupported pattern warnings
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $logoUrl = null,
        public readonly ?string $logoText = null,
        public readonly array $navLinks = [],
        public readonly array $ctaLinks = [],
        public readonly array $socialLinks = [],
        public readonly ?string $copyrightText = null,
        public readonly int $footerColumns = 0,
        public readonly array $warnings = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type'           => $this->type,
            'logo_url'       => $this->logoUrl,
            'logo_text'      => $this->logoText,
            'nav_links'      => $this->navLinks,
            'cta_links'      => $this->ctaLinks,
            'social_links'   => $this->socialLinks,
            'copyright_text' => $this->copyrightText,
            'footer_columns' => $this->footerColumns,
            'warnings'       => $this->warnings,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = array_key_exists('type', $data) ? $data['type'] : 'header';
        if (!is_string($type) || !in_array($type, ['header', 'footer'], true)) {
            throw new \InvalidArgumentException('type must be header or footer.');
        }

        return new self(
            type: $type,
            logoUrl: self::nullableString($data, 'logo_url'),
            logoText: self::nullableString($data, 'logo_text'),
            navLinks: self::linkList($data, 'nav_links'),
            ctaLinks: self::linkList($data, 'cta_links'),
            socialLinks: self::stringList($data, 'social_links'),
            copyrightText: self::nullableString($data, 'copyright_text'),
            footerColumns: self::integer($data, 'footer_columns'),
            warnings: self::stringList($data, 'warnings'),
        );
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new \InvalidArgumentException($field . ' must be a string or null.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{label: string, href: string}>
     */
    private static function linkList(array $data, string $field): array
    {
        $links = array_key_exists($field, $data) ? $data[$field] : [];
        if (!is_array($links) || !array_is_list($links)) {
            throw new \InvalidArgumentException($field . ' must be a list.');
        }

        foreach ($links as $link) {
            if (
                !is_array($link)
                || !is_string($link['label'] ?? null)
                || !is_string($link['href'] ?? null)
            ) {
                throw new \InvalidArgumentException($field . ' must contain links with string label and href fields.');
            }
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $data
     * @return string[]
     */
    private static function stringList(array $data, string $field): array
    {
        $values = array_key_exists($field, $data) ? $data[$field] : [];
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException($field . ' must be a list.');
        }

        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException($field . ' must contain only strings.');
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $field): int
    {
        $value = array_key_exists($field, $data) ? $data[$field] : 0;
        if (!is_int($value)) {
            throw new \InvalidArgumentException($field . ' must be an integer.');
        }

        return $value;
    }
}
