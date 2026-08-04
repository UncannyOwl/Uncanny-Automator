<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\DesignStandards\FontFamilyCatalogSourceInterface;

final class WordPressFontFamilyCatalogSource implements FontFamilyCatalogSourceInterface
{
    public function __construct(
        private readonly WordPressFontSettings $settings,
    ) {}

    /** @return list<string> */
    public function googleFontFamilies(): array
    {
        $families = [];

        foreach ($this->settings->googleFonts() as $font) {
            $family = $this->sanitizeFamily((string) ($font['family'] ?? ''));
            if ($family !== '') {
                $families[] = $family;
            }
        }

        return $families;
    }

    /** @return list<string> */
    public function customFontFamilies(): array
    {
        $families = [];

        foreach ($this->settings->customFonts() as $font) {
            $family = $this->sanitizeFamily((string) ($font['family'] ?? ''));
            if ($family !== '') {
                $families[] = $family;
            }
        }

        return $families;
    }

    private function sanitizeFamily(string $family): string
    {
        return FontInjector::sanitizeCustomFontFamily($family);
    }
}
