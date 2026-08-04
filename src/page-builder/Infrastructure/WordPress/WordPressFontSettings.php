<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Infrastructure\Persistence\WpSettingsRepository;

/**
 * Reads mutable WordPress font settings for editor and build-time use only.
 */
final class WordPressFontSettings
{
    public function __construct(
        private readonly WpSettingsRepository $settings,
    ) {}

    /** @return list<array{family: string, weights: string}> */
    public function googleFonts(): array
    {
        return array_values(array_map(
            static fn($font): array => $font->toArray(),
            $this->settings->load()->brandStyles()->fonts()->googleFonts(),
        ));
    }

    /** @return list<array{family: string, attachment_id: int, weight: string}> */
    public function customFonts(): array
    {
        return array_values(array_map(
            static fn($font): array => $font->toArray(),
            $this->settings->load()->brandStyles()->fonts()->customFonts(),
        ));
    }

    /** @return list<array{family: string, weight: string, url: string}> */
    public function renderableCustomFonts(): array
    {
        $fonts = [];

        foreach ($this->customFonts() as $font) {
            $url = wp_get_attachment_url((int) $font['attachment_id']);
            if (!is_string($url) || $url === '') {
                continue;
            }

            $fonts[] = [
                'family' => $font['family'],
                'weight' => $font['weight'],
                'url' => $url,
            ];
        }

        return $fonts;
    }
}
