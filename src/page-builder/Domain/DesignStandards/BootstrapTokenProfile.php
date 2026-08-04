<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

/**
 * Flat map of Bootstrap 5 CSS custom properties (--bs-* tokens).
 *
 * Immutable value object. Every key is a valid CSS custom property name
 * that Bootstrap's Reboot and component CSS consume via var().
 * When injected into :root after Bootstrap's <link> tag, these values
 * override Bootstrap's compiled defaults — no Sass needed.
 */
final class BootstrapTokenProfile
{
    // ── Palettes ─────────────────────────────────────────────
    private const THEME_COLORS = [
        '--bs-primary'   => '#0d6efd',
        '--bs-secondary' => '#6c757d',
        '--bs-success'   => '#198754',
        '--bs-info'      => '#0dcaf0',
        '--bs-warning'   => '#ffc107',
        '--bs-danger'    => '#dc3545',
        '--bs-light'     => '#f8f9fa',
        '--bs-dark'      => '#212529',
    ];

    private const THEME_COLOR_RGB = [
        '--bs-primary-rgb'   => '13,110,253',
        '--bs-secondary-rgb' => '108,117,125',
        '--bs-success-rgb'   => '25,135,84',
        '--bs-info-rgb'      => '13,202,240',
        '--bs-warning-rgb'   => '255,193,7',
        '--bs-danger-rgb'    => '220,53,69',
        '--bs-light-rgb'     => '248,249,250',
        '--bs-dark-rgb'      => '33,37,41',
        '--bs-white-rgb'     => '255,255,255',
        '--bs-black-rgb'     => '0,0,0',
    ];

    private const TEXT_EMPHASIS = [
        '--bs-primary-text-emphasis'   => '#052c65',
        '--bs-secondary-text-emphasis' => '#2b2f32',
        '--bs-success-text-emphasis'   => '#0a3622',
        '--bs-info-text-emphasis'      => '#055160',
        '--bs-warning-text-emphasis'   => '#664d03',
        '--bs-danger-text-emphasis'    => '#58151c',
    ];

    private const BG_SUBTLE = [
        '--bs-primary-bg-subtle'   => '#cfe2ff',
        '--bs-secondary-bg-subtle' => '#e2e3e5',
        '--bs-success-bg-subtle'   => '#d1e7dd',
        '--bs-info-bg-subtle'      => '#cff4fc',
        '--bs-warning-bg-subtle'   => '#fff3cd',
        '--bs-danger-bg-subtle'    => '#f8d7da',
    ];

    private const BORDER_SUBTLE = [
        '--bs-primary-border-subtle'   => '#9ec5fe',
        '--bs-secondary-border-subtle' => '#c4c8cb',
        '--bs-success-border-subtle'   => '#a3cfbb',
        '--bs-info-border-subtle'      => '#9eeaf9',
        '--bs-warning-border-subtle'   => '#ffe69c',
        '--bs-danger-border-subtle'    => '#f1aeb5',
    ];

    // ── Body ─────────────────────────────────────────────────
    private const BODY = [
        '--bs-body-font-family'  => "system-ui,-apple-system,\"Segoe UI\",Roboto,\"Helvetica Neue\",\"Noto Sans\",\"Liberation Sans\",Arial,sans-serif,\"Apple Color Emoji\",\"Segoe UI Emoji\",\"Segoe UI Symbol\",\"Noto Color Emoji\"",
        '--bs-body-font-size'    => '1rem',
        '--bs-body-font-weight'  => '400',
        '--bs-body-line-height'  => '1.5',
        '--bs-body-color'        => '#212529',
        '--bs-body-bg'           => '#fff',
        '--bs-body-color-rgb'    => '33,37,41',
        '--bs-body-bg-rgb'       => '255,255,255',
    ];

    // ── Typography ───────────────────────────────────────────
    private const TYPOGRAPHY = [
        '--bs-font-sans-serif' => "system-ui,-apple-system,\"Segoe UI\",Roboto,\"Helvetica Neue\",\"Noto Sans\",\"Liberation Sans\",Arial,sans-serif,\"Apple Color Emoji\",\"Segoe UI Emoji\",\"Segoe UI Symbol\",\"Noto Color Emoji\"",
        '--bs-font-monospace'  => "SFMono-Regular,Menlo,Monaco,Consolas,\"Liberation Mono\",\"Courier New\",monospace",
        '--bs-heading-color'   => 'inherit',
    ];

    // ── Heading Sizes (Engine extension — not native BS tokens) ──
    private const HEADINGS = [
        '--bs-heading-font-family'   => 'inherit',
        '--bs-heading-h1-font-size'  => '2.5rem',
        '--bs-heading-h2-font-size'  => '2rem',
        '--bs-heading-h3-font-size'  => '1.75rem',
        '--bs-heading-h4-font-size'  => '1.5rem',
        '--bs-heading-h5-font-size'  => '1.25rem',
        '--bs-heading-h6-font-size'  => '1rem',
        '--bs-heading-font-weight'   => '500',
        '--bs-heading-line-height'   => '1.2',
    ];

    // ── Links ────────────────────────────────────────────────
    private const LINKS = [
        '--bs-link-color'           => '#0d6efd',
        '--bs-link-color-rgb'       => '13,110,253',
        '--bs-link-hover-color'     => '#0a58ca',
        '--bs-link-hover-color-rgb' => '10,88,202',
        '--bs-link-decoration'      => 'underline',
    ];

    // ── Borders and radius ───────────────────────────────────
    private const BORDERS = [
        '--bs-border-width'            => '1px',
        '--bs-border-style'            => 'solid',
        '--bs-border-color'            => '#dee2e6',
        '--bs-border-color-translucent' => 'rgba(0,0,0,0.175)',
        '--bs-border-radius'           => '0.375rem',
        '--bs-border-radius-sm'        => '0.25rem',
        '--bs-border-radius-lg'        => '0.5rem',
        '--bs-border-radius-xl'        => '1rem',
        '--bs-border-radius-xxl'       => '2rem',
        '--bs-border-radius-pill'      => '50rem',
    ];

    // ── Shadows ──────────────────────────────────────────────
    private const SHADOWS = [
        '--bs-box-shadow'       => '0 0.5rem 1rem rgba(0,0,0,0.15)',
        '--bs-box-shadow-sm'    => '0 0.125rem 0.25rem rgba(0,0,0,0.075)',
        '--bs-box-shadow-lg'    => '0 1rem 3rem rgba(0,0,0,0.175)',
        '--bs-box-shadow-inset' => 'inset 0 1px 2px rgba(0,0,0,0.075)',
    ];

    // ── Supporting colors ────────────────────────────────────
    private const EMPHASIS = [
        '--bs-emphasis-color'       => '#000',
        '--bs-emphasis-color-rgb'   => '0,0,0',
        '--bs-secondary-color'      => 'rgba(33,37,41,0.75)',
        '--bs-secondary-color-rgb'  => '33,37,41',
        '--bs-secondary-bg'         => '#e9ecef',
        '--bs-secondary-bg-rgb'     => '233,236,239',
        '--bs-tertiary-color'       => 'rgba(33,37,41,0.5)',
        '--bs-tertiary-color-rgb'   => '33,37,41',
        '--bs-tertiary-bg'          => '#f8f9fa',
        '--bs-tertiary-bg-rgb'      => '248,249,250',
    ];

    // ── Focus ────────────────────────────────────────────────
    private const FOCUS = [
        '--bs-focus-ring-width'   => '0.25rem',
        '--bs-focus-ring-opacity' => '0.25',
        '--bs-focus-ring-color'   => 'rgba(13,110,253,0.25)',
    ];

    // ── Component Tokens (curated high-impact subset) ────────
    private const COMPONENTS = [
        '--bs-btn-font-size'       => '1rem',
        '--bs-btn-font-weight'     => '400',
        '--bs-btn-border-radius'   => '0.375rem',
        '--bs-btn-padding-x'       => '0.75rem',
        '--bs-btn-padding-y'       => '0.375rem',
        '--bs-card-border-radius'  => '0.375rem',
        '--bs-card-border-color'   => 'var(--bs-border-color-translucent)',
        '--bs-card-bg'             => 'var(--bs-body-bg)',
        '--bs-card-spacer-y'       => '1rem',
        '--bs-card-spacer-x'       => '1rem',
        '--bs-card-cap-bg'         => 'rgba(var(--bs-body-color-rgb),0.03)',
        '--bs-card-box-shadow'     => '',
        '--bs-navbar-padding-y'    => '0.5rem',
        '--bs-modal-border-radius' => '0.5rem',
        '--bs-modal-bg'            => 'var(--bs-body-bg)',
    ];

    /** @var array<string, string> */
    private readonly array $tokens;

    /** @param array<string, string> $tokens */
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public static function defaults(): self
    {
        return new self(array_merge(
            self::THEME_COLORS,
            self::THEME_COLOR_RGB,
            self::TEXT_EMPHASIS,
            self::BG_SUBTLE,
            self::BORDER_SUBTLE,
            self::BODY,
            self::TYPOGRAPHY,
            self::HEADINGS,
            self::LINKS,
            self::BORDERS,
            self::SHADOWS,
            self::EMPHASIS,
            self::FOCUS,
            self::COMPONENTS,
        ));
    }

    /** @param array<string, string> $data */
    public static function fromArray(array $data): self
    {
        return new self(DesignTokenValidator::normalizeBucket($data, 'Token'));
    }

    /** @return array<string, string> */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->tokens;
    }

    /**
     * Token keys that hold hex color values (used for color picker UI and RGB auto-computation).
     *
     * @return list<string>
     */
    public static function colorTokenKeys(): array
    {
        return array_keys(array_merge(self::THEME_COLORS, [
            '--bs-body-color' => '', '--bs-body-bg' => '',
            '--bs-link-color' => '', '--bs-link-hover-color' => '',
            '--bs-border-color' => '', '--bs-heading-color' => '',
            '--bs-emphasis-color' => '',
        ]));
    }

    /**
     * Token keys whose values are auto-computed RGB triplets from a parent hex color.
     * Maps the RGB token key to its parent hex token key.
     *
     * @return array<string, string>
     */
    public static function rgbDerivedTokens(): array
    {
        return [
            '--bs-primary-rgb'   => '--bs-primary',
            '--bs-secondary-rgb' => '--bs-secondary',
            '--bs-success-rgb'   => '--bs-success',
            '--bs-info-rgb'      => '--bs-info',
            '--bs-warning-rgb'   => '--bs-warning',
            '--bs-danger-rgb'    => '--bs-danger',
            '--bs-light-rgb'     => '--bs-light',
            '--bs-dark-rgb'      => '--bs-dark',
            '--bs-body-color-rgb' => '--bs-body-color',
            '--bs-body-bg-rgb'    => '--bs-body-bg',
            '--bs-link-color-rgb'       => '--bs-link-color',
            '--bs-link-hover-color-rgb' => '--bs-link-hover-color',
        ];
    }

    /**
     * Convert a hex color (#rrggbb or #rgb) to an RGB triplet string "r,g,b".
     * Returns null if the input is not a valid hex color.
     */
    public static function hexToRgb(string $hex): ?string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return null;
        }

        return sprintf(
            '%d,%d,%d',
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * Heading size token keys (Engine extension, not native Bootstrap).
     *
     * @return list<string>
     */
    public static function headingSizeTokenKeys(): array
    {
        return array_keys(self::HEADINGS);
    }

    /**
     * Admin UI token grouping.
     *
     * @return array<string, array{label: string, keys: list<string>}>
     */
    public static function tokenGroups(): array
    {
        return [
            'theme_colors' => [
                'label' => 'palettes',
                'keys'  => array_keys(self::THEME_COLORS),
            ],
            'body' => [
                'label' => 'Body',
                'keys'  => array_keys(self::BODY),
            ],
            'typography' => [
                'label' => 'Typography',
                'keys'  => array_keys(array_merge(self::TYPOGRAPHY, self::HEADINGS)),
            ],
            'links' => [
                'label' => 'Links',
                'keys'  => array_keys(self::LINKS),
            ],
            'borders' => [
                'label' => 'Borders and radius',
                'keys'  => array_keys(self::BORDERS),
            ],
            'shadows' => [
                'label' => 'Shadows',
                'keys'  => array_keys(self::SHADOWS),
            ],
            'emphasis' => [
                'label' => 'Supporting colors',
                'keys'  => array_keys(self::EMPHASIS),
            ],
            'focus' => [
                'label' => 'Focus',
                'keys'  => array_keys(self::FOCUS),
            ],
            'components' => [
                'label' => 'Components',
                'keys'  => array_keys(self::COMPONENTS),
            ],
        ];
    }
}
