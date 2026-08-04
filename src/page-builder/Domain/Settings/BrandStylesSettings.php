<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

use UncannyPageBuilder\Domain\DesignStandards\BootstrapBreakpoints;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsProfile;

/**
 * Brand styles subsection of the sitewide settings aggregate.
 */
final class BrandStylesSettings
{
    public function __construct(
        private readonly LogoSettings $logo,
        private readonly FontSettings $fonts,
        private readonly TextStylesSettings $textStyles,
        private readonly ColorSettings $colors,
        private readonly BootstrapBreakpoints $breakpoints,
    ) {}

    public static function defaults(): self
    {
        $defaults = DesignStandardsProfile::defaults();

        return new self(
            new LogoSettings(),
            new FontSettings(),
            new TextStylesSettings($defaults->typography(), $defaults->lockedKeys()['typography']),
            new ColorSettings($defaults->tokens(), $defaults->lockedKeys()['tokens']),
            $defaults->breakpoints(),
        );
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return self::defaults();
        }

        return new self(
            LogoSettings::fromArray($data['logo'] ?? null),
            FontSettings::fromArray($data['fonts'] ?? null),
            self::textStylesFromArray($data['text_styles'] ?? null),
            self::colorSettingsFromArray($data['colors'] ?? null),
            BootstrapBreakpoints::fromArray(is_array($data['breakpoints'] ?? null) ? $data['breakpoints'] : []),
        );
    }

    /**
     * @return array{
     *     logo: array{attachment_id: int},
     *     fonts: array{
     *         google: list<array{family: string, weights: string}>,
     *         custom: list<array{family: string, attachment_id: int, weight: string}>
     *     },
     *     text_styles: array{typography: array<string, mixed>, locked_keys: list<string>},
     *     colors: array{tokens: array<string, string>, locked_keys: list<string>},
     *     breakpoints: array<string, int>
     * }
     */
    public function toArray(): array
    {
        return [
            'logo' => $this->logo->toArray(),
            'fonts' => $this->fonts->toArray(),
            'text_styles' => $this->textStyles->toArray(),
            'colors' => $this->colors->toArray(),
            'breakpoints' => $this->breakpoints->toArray(),
        ];
    }

    public function logo(): LogoSettings
    {
        return $this->logo;
    }

    public function fonts(): FontSettings
    {
        return $this->fonts;
    }

    public function textStyles(): TextStylesSettings
    {
        return $this->textStyles;
    }

    public function colors(): ColorSettings
    {
        return $this->colors;
    }

    public function breakpoints(): BootstrapBreakpoints
    {
        return $this->breakpoints;
    }

    public function withLogo(LogoSettings $logo): self
    {
        return new self($logo, $this->fonts, $this->textStyles, $this->colors, $this->breakpoints);
    }

    public function withFonts(FontSettings $fonts): self
    {
        return new self($this->logo, $fonts, $this->textStyles, $this->colors, $this->breakpoints);
    }

    public function withDesignStandardsProfile(DesignStandardsProfile $profile): self
    {
        return new self(
            $this->logo,
            $this->fonts,
            new TextStylesSettings($profile->typography(), $profile->lockedKeys()['typography']),
            new ColorSettings($profile->tokens(), $profile->lockedKeys()['tokens']),
            $profile->breakpoints(),
        );
    }

    public function designStandardsProfile(): DesignStandardsProfile
    {
        return new DesignStandardsProfile(
            $this->colors->tokens(),
            $this->breakpoints,
            $this->textStyles->typography(),
            [
                'tokens' => $this->colors->lockedKeys(),
                'typography' => $this->textStyles->lockedKeys(),
            ],
        );
    }

    private static function textStylesFromArray(mixed $data): TextStylesSettings
    {
        try {
            return TextStylesSettings::fromArray($data);
        } catch (\Throwable) {
            return self::defaults()->textStyles();
        }
    }

    private static function colorSettingsFromArray(mixed $data): ColorSettings
    {
        try {
            return ColorSettings::fromArray($data);
        } catch (\Throwable) {
            return self::defaults()->colors();
        }
    }
}
