<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Settings;

use UncannyPageBuilder\Domain\DesignStandards\BootstrapTokenProfile;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsProfile;

/**
 * Shared field/view-model builders for the design settings forms.
 *
 * Both the global Brand styles pages (AdminBrandingPage) and the page-level
 * "Page style overrides" metabox (DesignStandardsMetaBox) render the same
 * partials from the same field shapes built here, so the two surfaces cannot
 * drift apart in vocabulary, grouping, or which tokens are exposed.
 */
final class DesignSettingsFields
{
    /**
     * @return array<int, array{key: string, label: string, fields: array<int, array{key: string, label: string, value: string, default: string, isColor: bool}>}>
     */
    public static function visibleTokenGroups(DesignStandardsProfile $profile): array
    {
        $tokens = $profile->tokens()->toArray();
        $groups = [];

        foreach (BootstrapTokenProfile::tokenGroups() as $groupKey => $group) {
            $fields = [];

            if (self::isHiddenTokenGroup($groupKey)) {
                continue;
            }

            foreach ($group['keys'] as $tokenKey) {
                $field = self::tokenField($tokens, $tokenKey);
                if (self::isHiddenTokenField($tokenKey)) {
                    continue;
                }

                $fields[] = $field;
            }

            if ($fields === []) {
                continue;
            }

            $groups[] = [
                'key' => $groupKey,
                'label' => self::tokenGroupLabel($groupKey, (string) ($group['label'] ?? $groupKey)),
                'fields' => $fields,
            ];
        }

        return $groups;
    }

    /**
     * @return array<int, array{key: string, label: string, value: string, default: string, isColor: bool}>
     */
    public static function hiddenTokenFields(DesignStandardsProfile $profile): array
    {
        $tokens = $profile->tokens()->toArray();
        $fields = [];

        foreach (BootstrapTokenProfile::tokenGroups() as $groupKey => $group) {
            foreach ($group['keys'] as $tokenKey) {
                if (
                    !self::isHiddenTokenField($tokenKey)
                    && !self::isHiddenTokenGroup($groupKey)
                ) {
                    continue;
                }

                $fields[] = self::tokenField($tokens, $tokenKey);
            }
        }

        return $fields;
    }

    /**
     * Page-scope variant: values are the page's sparse overrides, defaults are
     * the site-effective values the page inherits when a field is empty.
     *
     * @param array<string, string> $overrideTokens
     * @param array<string, string> $effectiveTokens
     * @return array<int, array{key: string, label: string, fields: array<int, array{key: string, label: string, value: string, default: string, isColor: bool}>}>
     */
    public static function visibleTokenGroupsForOverrides(array $overrideTokens, array $effectiveTokens): array
    {
        $groups = [];

        foreach (BootstrapTokenProfile::tokenGroups() as $groupKey => $group) {
            $fields = [];

            if (self::isHiddenTokenGroup($groupKey)) {
                continue;
            }

            foreach ($group['keys'] as $tokenKey) {
                if (self::isHiddenTokenField($tokenKey)) {
                    continue;
                }

                $fields[] = self::buildTokenField($overrideTokens, $effectiveTokens, $tokenKey);
            }

            if ($fields === []) {
                continue;
            }

            $groups[] = [
                'key' => $groupKey,
                'label' => self::tokenGroupLabel($groupKey, (string) ($group['label'] ?? $groupKey)),
                'fields' => $fields,
            ];
        }

        return $groups;
    }

    /**
     * Page-scope variant of linkFields(); see visibleTokenGroupsForOverrides().
     *
     * @param array<string, string> $overrideTokens
     * @param array<string, string> $effectiveTokens
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     value: string,
     *     default: string,
     *     control: string,
     *     isColor: bool,
     *     options?: array<int, array{value: string, label: string}>
     * }>
     */
    public static function linkFieldsForOverrides(array $overrideTokens, array $effectiveTokens): array
    {
        return self::buildLinkFields($overrideTokens, $effectiveTokens);
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     value: string,
     *     default: string,
     *     control: string,
     *     isColor: bool,
     *     options?: array<int, array{value: string, label: string}>
     * }>
     */
    public static function linkFields(DesignStandardsProfile $profile): array
    {
        return self::buildLinkFields(
            $profile->tokens()->toArray(),
            BootstrapTokenProfile::defaults()->toArray(),
        );
    }

    /**
     * @param array<string, string> $tokens
     * @param array<string, string> $defaults
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     value: string,
     *     default: string,
     *     control: string,
     *     isColor: bool,
     *     options?: array<int, array{value: string, label: string}>
     * }>
     */
    private static function buildLinkFields(array $tokens, array $defaults): array
    {
        return [
            [
                'key' => '--bs-link-color',
                'label' => _x('Link color', 'Page Builder', 'uncanny-automator'),
                'value' => (string) ($tokens['--bs-link-color'] ?? ''),
                'default' => (string) ($defaults['--bs-link-color'] ?? ''),
                'control' => 'color',
                'isColor' => true,
            ],
            [
                'key' => '--bs-link-hover-color',
                'label' => _x('Link hover color', 'Page Builder', 'uncanny-automator'),
                'value' => (string) ($tokens['--bs-link-hover-color'] ?? ''),
                'default' => (string) ($defaults['--bs-link-hover-color'] ?? ''),
                'control' => 'color',
                'isColor' => true,
            ],
            [
                'key' => '--bs-link-decoration',
                'label' => _x('Link underline style', 'Page Builder', 'uncanny-automator'),
                'value' => (string) ($tokens['--bs-link-decoration'] ?? ''),
                'default' => (string) ($defaults['--bs-link-decoration'] ?? ''),
                'control' => 'select',
                'isColor' => false,
                'options' => [
                    ['value' => 'underline', 'label' => _x('Underline', 'Page Builder', 'uncanny-automator')],
                    ['value' => 'none', 'label' => _x('No underline', 'Page Builder', 'uncanny-automator')],
                ],
            ],
        ];
    }

    /**
     * @param array<string, string> $tokens
     * @return array{key: string, label: string, value: string, default: string, isColor: bool}
     */
    public static function tokenField(array $tokens, string $tokenKey): array
    {
        return self::buildTokenField($tokens, BootstrapTokenProfile::defaults()->toArray(), $tokenKey);
    }

    /**
     * @param array<string, string> $values
     * @param array<string, string> $defaults
     * @return array{key: string, label: string, value: string, default: string, isColor: bool}
     */
    private static function buildTokenField(array $values, array $defaults, string $tokenKey): array
    {
        return [
            'key' => $tokenKey,
            'label' => self::tokenFieldLabel($tokenKey),
            'value' => (string) ($values[$tokenKey] ?? ''),
            'default' => (string) ($defaults[$tokenKey] ?? ''),
            'isColor' => in_array($tokenKey, BootstrapTokenProfile::colorTokenKeys(), true),
        ];
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     preview: string,
     *     fields: array<int, array{key: string, label: string, control: string}>
     * }>
     */
    public static function typographyRoleDefinitions(): array
    {
        return [
            [
                'key' => 'body',
                'label' => 'Body',
                'description' => 'Default site text',
                'preview' => 'Build pages that feel readable from the first paragraph.',
                'fields' => [
                    ['key' => 'font_family', 'label' => 'Font family', 'control' => 'font_family'],
                    ['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size'],
                    ['key' => 'font_weight', 'label' => 'Font weight', 'control' => 'font_weight'],
                    ['key' => 'line_height', 'label' => 'Line height', 'control' => 'line_height'],
                ],
            ],
            [
                'key' => 'paragraph',
                'label' => 'Paragraph',
                'description' => 'Long-form paragraph text',
                'preview' => 'Use this role when paragraph copy needs its own cadence.',
                'fields' => [
                    ['key' => 'font_family', 'label' => 'Font family', 'control' => 'font_family'],
                    ['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size'],
                    ['key' => 'font_weight', 'label' => 'Font weight', 'control' => 'font_weight'],
                    ['key' => 'line_height', 'label' => 'Line height', 'control' => 'line_height'],
                ],
            ],
            [
                'key' => 'headings',
                'label' => 'Headings',
                'description' => 'Shared heading typography',
                'preview' => 'Heading preview',
                'fields' => [
                    ['key' => 'font_family', 'label' => 'Font family', 'control' => 'font_family'],
                    ['key' => 'font_weight', 'label' => 'Font weight', 'control' => 'font_weight'],
                    ['key' => 'line_height', 'label' => 'Line height', 'control' => 'line_height'],
                ],
            ],
            [
                'key' => 'h1',
                'label' => 'H1',
                'description' => 'Top-level display size',
                'preview' => 'Hero headline',
                'fields' => [['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size']],
            ],
            [
                'key' => 'h2',
                'label' => 'H2',
                'description' => 'Section title size',
                'preview' => 'Section heading',
                'fields' => [['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size']],
            ],
            [
                'key' => 'h3',
                'label' => 'H3',
                'description' => 'Subsection title size',
                'preview' => 'Subsection heading',
                'fields' => [['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size']],
            ],
            [
                'key' => 'h4',
                'label' => 'H4',
                'description' => 'Utility heading size',
                'preview' => 'Utility heading',
                'fields' => [['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size']],
            ],
            [
                'key' => 'h5',
                'label' => 'H5',
                'description' => 'Small heading size',
                'preview' => 'Small heading',
                'fields' => [['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size']],
            ],
            [
                'key' => 'h6',
                'label' => 'H6',
                'description' => 'Micro heading size',
                'preview' => 'Micro heading',
                'fields' => [['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size']],
            ],
            [
                'key' => 'buttons',
                'label' => 'Buttons',
                'description' => 'Calls to action and buttons',
                'preview' => 'Primary action',
                'fields' => [
                    ['key' => 'font_family', 'label' => 'Font family', 'control' => 'font_family'],
                    ['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size'],
                    ['key' => 'font_weight', 'label' => 'Font weight', 'control' => 'font_weight'],
                ],
            ],
            [
                'key' => 'navigation',
                'label' => 'Navigation',
                'description' => 'Menus and navigation labels',
                'preview' => 'Home  About  Contact',
                'fields' => [
                    ['key' => 'font_family', 'label' => 'Font family', 'control' => 'font_family'],
                    ['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size'],
                    ['key' => 'font_weight', 'label' => 'Font weight', 'control' => 'font_weight'],
                    ['key' => 'line_height', 'label' => 'Line height', 'control' => 'line_height'],
                    ['key' => 'letter_spacing', 'label' => 'Letter spacing', 'control' => 'letter_spacing'],
                    ['key' => 'text_transform', 'label' => 'Text transform', 'control' => 'text_transform'],
                ],
            ],
            [
                'key' => 'blockquote',
                'label' => 'Blockquote',
                'description' => 'Quoted content',
                'preview' => '“Strong typography makes the layout feel intentional.”',
                'fields' => [
                    ['key' => 'font_family', 'label' => 'Font family', 'control' => 'font_family'],
                    ['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size'],
                    ['key' => 'font_weight', 'label' => 'Font weight', 'control' => 'font_weight'],
                    ['key' => 'line_height', 'label' => 'Line height', 'control' => 'line_height'],
                    ['key' => 'font_style', 'label' => 'Font style', 'control' => 'font_style'],
                ],
            ],
            [
                'key' => 'code',
                'label' => 'Code',
                'description' => 'Code, keyboard, and monospace text',
                'preview' => 'const heading = "Code preview";',
                'fields' => [
                    ['key' => 'font_family', 'label' => 'Font family', 'control' => 'font_family'],
                    ['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size'],
                    ['key' => 'font_weight', 'label' => 'Font weight', 'control' => 'font_weight'],
                    ['key' => 'line_height', 'label' => 'Line height', 'control' => 'line_height'],
                ],
            ],
            [
                'key' => 'caption',
                'label' => 'Captions and labels',
                'description' => 'Helper text and metadata',
                'preview' => 'Helpful details live here.',
                'fields' => [
                    ['key' => 'font_family', 'label' => 'Font family', 'control' => 'font_family'],
                    ['key' => 'font_size', 'label' => 'Font size', 'control' => 'font_size'],
                    ['key' => 'font_weight', 'label' => 'Font weight', 'control' => 'font_weight'],
                    ['key' => 'line_height', 'label' => 'Line height', 'control' => 'line_height'],
                    ['key' => 'letter_spacing', 'label' => 'Letter spacing', 'control' => 'letter_spacing'],
                    ['key' => 'text_transform', 'label' => 'Text transform', 'control' => 'text_transform'],
                ],
            ],
        ];
    }

    private static function isHiddenTokenField(string $tokenKey): bool
    {
        return in_array($tokenKey, array_keys(BootstrapTokenProfile::rgbDerivedTokens()), true)
            || self::isTextStylesManagedTokenField($tokenKey)
            || self::isTextStylesManagedColorTokenField($tokenKey)
            || str_ends_with($tokenKey, '-rgb');
    }

    private static function isHiddenTokenGroup(string $groupKey): bool
    {
        return in_array($groupKey, [
            'body',
            'typography',
            'borders',
            'shadows',
            'emphasis',
            'focus',
            'components',
        ], true);
    }

    private static function isTextStylesManagedTokenField(string $tokenKey): bool
    {
        return in_array($tokenKey, [
            '--bs-body-font-family',
            '--bs-body-font-size',
            '--bs-body-font-weight',
            '--bs-body-line-height',
            '--bs-font-sans-serif',
            '--bs-font-monospace',
            '--bs-heading-font-family',
            '--bs-heading-h1-font-size',
            '--bs-heading-h2-font-size',
            '--bs-heading-h3-font-size',
            '--bs-heading-h4-font-size',
            '--bs-heading-h5-font-size',
            '--bs-heading-h6-font-size',
            '--bs-heading-font-weight',
            '--bs-heading-line-height',
            '--bs-btn-font-size',
            '--bs-btn-font-weight',
        ], true);
    }

    private static function isTextStylesManagedColorTokenField(string $tokenKey): bool
    {
        return in_array($tokenKey, [
            '--bs-link-color',
            '--bs-link-hover-color',
            '--bs-link-decoration',
            '--bs-light',
            '--bs-dark',
        ], true);
    }

    private static function tokenGroupLabel(string $groupKey, string $fallback): string
    {
        return match ($groupKey) {
            'theme_colors' => _x('Brand colors', 'Page Builder', 'uncanny-automator'),
            'body' => _x('Page background and text', 'Page Builder', 'uncanny-automator'),
            'typography' => _x('Headings', 'Page Builder', 'uncanny-automator'),
            'links' => _x('Links', 'Page Builder', 'uncanny-automator'),
            'borders' => _x('Borders and corners', 'Page Builder', 'uncanny-automator'),
            'shadows' => _x('Shadows', 'Page Builder', 'uncanny-automator'),
            'emphasis' => _x('Supporting colors', 'Page Builder', 'uncanny-automator'),
            'focus' => _x('Keyboard focus style', 'Page Builder', 'uncanny-automator'),
            'components' => _x('Buttons, cards, and popups', 'Page Builder', 'uncanny-automator'),
            default => $fallback,
        };
    }

    private static function tokenFieldLabel(string $tokenKey): string
    {
        return match ($tokenKey) {
            '--bs-primary' => _x('Primary brand color', 'Page Builder', 'uncanny-automator'),
            '--bs-secondary' => _x('Secondary brand color', 'Page Builder', 'uncanny-automator'),
            '--bs-success' => _x('Positive message color', 'Page Builder', 'uncanny-automator'),
            '--bs-info' => _x('Helpful message color', 'Page Builder', 'uncanny-automator'),
            '--bs-warning' => _x('Caution message color', 'Page Builder', 'uncanny-automator'),
            '--bs-danger' => _x('Error message color', 'Page Builder', 'uncanny-automator'),
            '--bs-light' => _x('Light background color', 'Page Builder', 'uncanny-automator'),
            '--bs-dark' => _x('Dark background color', 'Page Builder', 'uncanny-automator'),
            '--bs-body-font-family' => _x('Default text font', 'Page Builder', 'uncanny-automator'),
            '--bs-body-font-size' => _x('Default text size', 'Page Builder', 'uncanny-automator'),
            '--bs-body-font-weight' => _x('Default text weight', 'Page Builder', 'uncanny-automator'),
            '--bs-body-line-height' => _x('Default line spacing', 'Page Builder', 'uncanny-automator'),
            '--bs-body-color' => _x('Default text color', 'Page Builder', 'uncanny-automator'),
            '--bs-body-bg' => _x('Page background color', 'Page Builder', 'uncanny-automator'),
            '--bs-font-sans-serif' => _x('Fallback text fonts', 'Page Builder', 'uncanny-automator'),
            '--bs-font-monospace' => _x('Fallback code fonts', 'Page Builder', 'uncanny-automator'),
            '--bs-heading-color' => _x('Heading color', 'Page Builder', 'uncanny-automator'),
            '--bs-heading-h1-font-size' => _x('Heading 1 size', 'Page Builder', 'uncanny-automator'),
            '--bs-heading-h2-font-size' => _x('Heading 2 size', 'Page Builder', 'uncanny-automator'),
            '--bs-heading-h3-font-size' => _x('Heading 3 size', 'Page Builder', 'uncanny-automator'),
            '--bs-heading-h4-font-size' => _x('Heading 4 size', 'Page Builder', 'uncanny-automator'),
            '--bs-heading-h5-font-size' => _x('Heading 5 size', 'Page Builder', 'uncanny-automator'),
            '--bs-heading-h6-font-size' => _x('Heading 6 size', 'Page Builder', 'uncanny-automator'),
            '--bs-heading-font-weight' => _x('Heading weight', 'Page Builder', 'uncanny-automator'),
            '--bs-heading-line-height' => _x('Heading line spacing', 'Page Builder', 'uncanny-automator'),
            '--bs-link-color' => _x('Link color', 'Page Builder', 'uncanny-automator'),
            '--bs-link-hover-color' => _x('Link hover color', 'Page Builder', 'uncanny-automator'),
            '--bs-link-decoration' => _x('Link underline style', 'Page Builder', 'uncanny-automator'),
            '--bs-border-width' => _x('Default border width', 'Page Builder', 'uncanny-automator'),
            '--bs-border-style' => _x('Default border style', 'Page Builder', 'uncanny-automator'),
            '--bs-border-color' => _x('Default border color', 'Page Builder', 'uncanny-automator'),
            '--bs-border-color-translucent' => _x('Soft border color', 'Page Builder', 'uncanny-automator'),
            '--bs-border-radius' => _x('Default corner radius', 'Page Builder', 'uncanny-automator'),
            '--bs-border-radius-sm' => _x('Small corner radius', 'Page Builder', 'uncanny-automator'),
            '--bs-border-radius-lg' => _x('Large corner radius', 'Page Builder', 'uncanny-automator'),
            '--bs-border-radius-xl' => _x('Extra-large corner radius', 'Page Builder', 'uncanny-automator'),
            '--bs-border-radius-xxl' => _x('Oversized corner radius', 'Page Builder', 'uncanny-automator'),
            '--bs-border-radius-pill' => _x('Pill corner radius', 'Page Builder', 'uncanny-automator'),
            '--bs-box-shadow' => _x('Default shadow', 'Page Builder', 'uncanny-automator'),
            '--bs-box-shadow-sm' => _x('Small shadow', 'Page Builder', 'uncanny-automator'),
            '--bs-box-shadow-lg' => _x('Large shadow', 'Page Builder', 'uncanny-automator'),
            '--bs-box-shadow-inset' => _x('Inset shadow', 'Page Builder', 'uncanny-automator'),
            '--bs-emphasis-color' => _x('Strong text color', 'Page Builder', 'uncanny-automator'),
            '--bs-emphasis-color-rgb' => _x('Strong text color value', 'Page Builder', 'uncanny-automator'),
            '--bs-secondary-color' => _x('Muted text color', 'Page Builder', 'uncanny-automator'),
            '--bs-secondary-color-rgb' => _x('Muted text color value', 'Page Builder', 'uncanny-automator'),
            '--bs-secondary-bg' => _x('Muted background color', 'Page Builder', 'uncanny-automator'),
            '--bs-secondary-bg-rgb' => _x('Muted background value', 'Page Builder', 'uncanny-automator'),
            '--bs-tertiary-color' => _x('Subtle text color', 'Page Builder', 'uncanny-automator'),
            '--bs-tertiary-color-rgb' => _x('Subtle text color value', 'Page Builder', 'uncanny-automator'),
            '--bs-tertiary-bg' => _x('Subtle background color', 'Page Builder', 'uncanny-automator'),
            '--bs-tertiary-bg-rgb' => _x('Subtle background value', 'Page Builder', 'uncanny-automator'),
            '--bs-focus-ring-width' => _x('Focus outline width', 'Page Builder', 'uncanny-automator'),
            '--bs-focus-ring-opacity' => _x('Focus outline strength', 'Page Builder', 'uncanny-automator'),
            '--bs-focus-ring-color' => _x('Focus outline color', 'Page Builder', 'uncanny-automator'),
            '--bs-btn-font-size' => _x('Button text size', 'Page Builder', 'uncanny-automator'),
            '--bs-btn-font-weight' => _x('Button text weight', 'Page Builder', 'uncanny-automator'),
            '--bs-btn-border-radius' => _x('Button corner radius', 'Page Builder', 'uncanny-automator'),
            '--bs-btn-padding-x' => _x('Button horizontal padding', 'Page Builder', 'uncanny-automator'),
            '--bs-btn-padding-y' => _x('Button vertical padding', 'Page Builder', 'uncanny-automator'),
            '--bs-card-border-radius' => _x('Card corner radius', 'Page Builder', 'uncanny-automator'),
            '--bs-card-border-color' => _x('Card border color', 'Page Builder', 'uncanny-automator'),
            '--bs-card-bg' => _x('Card background color', 'Page Builder', 'uncanny-automator'),
            '--bs-card-spacer-y' => _x('Card vertical spacing', 'Page Builder', 'uncanny-automator'),
            '--bs-card-spacer-x' => _x('Card horizontal spacing', 'Page Builder', 'uncanny-automator'),
            '--bs-card-cap-bg' => _x('Card header background', 'Page Builder', 'uncanny-automator'),
            '--bs-card-box-shadow' => _x('Card shadow', 'Page Builder', 'uncanny-automator'),
            '--bs-navbar-padding-y' => _x('Menu vertical padding', 'Page Builder', 'uncanny-automator'),
            '--bs-modal-border-radius' => _x('Popup corner radius', 'Page Builder', 'uncanny-automator'),
            '--bs-modal-bg' => _x('Popup background color', 'Page Builder', 'uncanny-automator'),
            default => ucwords(str_replace('-', ' ', preg_replace('/^--bs-/', '', $tokenKey) ?: $tokenKey)),
        };
    }
}
