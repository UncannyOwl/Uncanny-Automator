<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

/**
 * Stored font library for brand styles.
 */
final class FontSettings
{
    /**
     * @param list<GoogleFontSettings> $googleFonts
     * @param list<CustomFontSettings> $customFonts
     */
    public function __construct(
        private readonly array $googleFonts = [],
        private readonly array $customFonts = [],
    ) {}

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return new self();
        }

        $googleFonts = [];
        foreach ((array) ($data['google'] ?? []) as $entry) {
            $font = GoogleFontSettings::fromArray($entry);
            if ($font instanceof GoogleFontSettings) {
                $googleFonts[] = $font;
            }
        }

        $customFonts = [];
        foreach ((array) ($data['custom'] ?? []) as $entry) {
            $font = CustomFontSettings::fromArray($entry);
            if ($font instanceof CustomFontSettings) {
                $customFonts[] = $font;
            }
        }

        return new self($googleFonts, $customFonts);
    }

    /**
     * @return array{
     *     google: list<array{family: string, weights: string}>,
     *     custom: list<array{family: string, attachment_id: int, weight: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'google' => array_map(
                static fn(GoogleFontSettings $font): array => $font->toArray(),
                $this->googleFonts,
            ),
            'custom' => array_map(
                static fn(CustomFontSettings $font): array => $font->toArray(),
                $this->customFonts,
            ),
        ];
    }

    /**
     * @return list<GoogleFontSettings>
     */
    public function googleFonts(): array
    {
        return $this->googleFonts;
    }

    /**
     * @return list<CustomFontSettings>
     */
    public function customFonts(): array
    {
        return $this->customFonts;
    }
}
