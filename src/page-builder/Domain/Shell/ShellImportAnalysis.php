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
        return new self(
            type: $data['type'] ?? 'header',
            logoUrl: $data['logo_url'] ?? null,
            logoText: $data['logo_text'] ?? null,
            navLinks: $data['nav_links'] ?? [],
            ctaLinks: $data['cta_links'] ?? [],
            socialLinks: $data['social_links'] ?? [],
            copyrightText: $data['copyright_text'] ?? null,
            footerColumns: $data['footer_columns'] ?? 0,
            warnings: $data['warnings'] ?? [],
        );
    }
}
