<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\GetAvailableFontFamilies;
use UncannyPageBuilder\Application\Settings\LoadSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveFontSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveLogoSettingsUseCase;
use UncannyPageBuilder\Domain\DesignStandards\BootstrapTokenProfile;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsProfile;
use UncannyPageBuilder\Domain\DesignStandards\TypographyProfile;
use UncannyPageBuilder\Domain\Settings\CustomFontSettings;
use UncannyPageBuilder\Domain\Settings\FontSettings;
use UncannyPageBuilder\Domain\Settings\GoogleFontSettings;
use UncannyPageBuilder\Domain\Settings\LogoSettings;
use UncannyPageBuilder\Infrastructure\Persistence\WpSettingsRepository;
use UncannyPageBuilder\Presentation\Settings\DesignSettingsFields;

final class AdminBrandingPage
{
    private const LOGO_NONCE_ACTION = 'upb_save_branding_logo';
    private const LOGO_NONCE_FIELD = 'upb_branding_logo_nonce';
    private const LOGO_FIELD = 'upb_brand_logo_id';
    private const FONTS_NONCE_ACTION = 'upb_save_branding_fonts';
    private const FONTS_NONCE_FIELD = 'upb_branding_fonts_nonce';
    private const DESIGN_NONCE_ACTION = 'upb_save_design_standards';
    private const DESIGN_NONCE_FIELD = 'upb_design_standards_nonce';

    public function __construct(
        private readonly LoadSettingsUseCase $loadSettings,
        private readonly SaveLogoSettingsUseCase $saveLogoSettings,
        private readonly SaveFontSettingsUseCase $saveFontSettings,
        private readonly DesignStandardsService $designStandardsService,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly GetAvailableFontFamilies $availableFontFamilies,
    ) {}

    public function renderPlainBrandIdentitySettingsContent(): void
    {
        [
            'updated' => $updated,
            'logoId' => $logoId,
            'logoSource' => $logoSource,
            'logoUrl' => $logoUrl,
            'logoField' => $logoField,
            'nonce' => $nonce,
        ] = $this->logoViewData();

        include __DIR__ . '/../../Presentation/Settings/brand-identity-settings-form.php';
    }

    public function renderPlainFontLibrarySettingsContent(): void
    {
        [
            'updated' => $updated,
            'googleFonts' => $googleFonts,
            'customFonts' => $customFonts,
            'weightOptions' => $weightOptions,
            'nonce' => $nonce,
        ] = $this->fontLibraryViewData();

        include __DIR__ . '/../../Presentation/Settings/font-library-settings-form.php';
    }

    public function renderPlainTextStylesSettingsContent(string $activeSection = ''): void
    {
        [
            'updated' => $updated,
            'error' => $error,
            'warning' => $warning,
            'typographyRoles' => $typographyRoles,
            'typographyDefaults' => $typographyDefaults,
            'linkFields' => $linkFields,
            'roleDefinitions' => $roleDefinitions,
            'fontFamilyCatalog' => $fontFamilyCatalog,
            'nonce' => $nonce,
        ] = $this->typographySettingsViewData();

        include __DIR__ . '/../../Presentation/Settings/text-styles-settings-form.php';
    }

    public function renderPlainColorsComponentsSettingsContent(): void
    {
        [
            'updated' => $updated,
            'error' => $error,
            'warning' => $warning,
            'tokenGroups' => $tokenGroups,
            'hiddenFields' => $hiddenFields,
            'nonce' => $nonce,
        ] = $this->typographySettingsViewData();

        include __DIR__ . '/../../Presentation/Settings/colors-components-settings-form.php';
    }

    // ── Logo ─────────────────────────────────────────────

    public static function resolveLogoUrl(): string
    {
        $logoId = self::detectLogoId();
        if ($logoId <= 0) {
            return '';
        }

        $url = wp_get_attachment_image_url($logoId, 'medium');
        return is_string($url) ? $url : '';
    }

    public static function detectLogoId(): int
    {
        $engineLogo = (new WpSettingsRepository())
            ->load()
            ->brandStyles()
            ->logo()
            ->attachmentId();
        if ($engineLogo > 0) {
            return $engineLogo;
        }

        $customizerLogo = (int) get_theme_mod('custom_logo', 0);
        if ($customizerLogo > 0) {
            return $customizerLogo;
        }

        $fseLogo = (int) get_option('site_logo', 0);
        if ($fseLogo > 0) {
            return $fseLogo;
        }

        return 0;
    }

    private function detectLogoSource(): string
    {
        $engineLogo = (new WpSettingsRepository())
            ->load()
            ->brandStyles()
            ->logo()
            ->attachmentId();
        if ($engineLogo > 0) {
            return '';
        }

        $customizerLogo = (int) get_theme_mod('custom_logo', 0);
        if ($customizerLogo > 0) {
            return _x('theme customizer', 'Page Builder', 'uncanny-automator');
        }

        $fseLogo = (int) get_option('site_logo', 0);
        if ($fseLogo > 0) {
            return _x('site editor', 'Page Builder', 'uncanny-automator');
        }

        return '';
    }

    /**
     * @return array{
     *     updated: bool,
     *     logoId: int,
     *     logoSource: string,
     *     logoUrl: string,
     *     logoField: string,
     *     nonce: array{name: string, value: string}
     * }
     */
    private function logoViewData(): array
    {
        $updated = false;

        // Keep the duplicate settings shell on the same guarded logo write
        // path as the existing branding screen so rollout cannot drift.
        if (
            isset($_POST[self::LOGO_NONCE_FIELD])
            && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::LOGO_NONCE_FIELD])),
                self::LOGO_NONCE_ACTION
            )
            && $this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            $attachmentId = absint($_POST[self::LOGO_FIELD] ?? 0);
            ($this->saveLogoSettings)(
                new LogoSettings($attachmentId > 0 ? $attachmentId : 0),
            );
            $updated = true;
        }

        $logoId = self::detectLogoId();

        return [
            'updated' => $updated,
            'logoId' => $logoId,
            'logoSource' => $this->detectLogoSource(),
            'logoUrl' => $logoId > 0 ? (string) wp_get_attachment_image_url($logoId, 'medium') : '',
            'logoField' => self::LOGO_FIELD,
            'nonce' => [
                'name' => self::LOGO_NONCE_FIELD,
                'value' => wp_create_nonce(self::LOGO_NONCE_ACTION),
            ],
        ];
    }

    private function saveFontsFromPost(): void
    {
        $rawGf = wp_unslash($_POST['upb_gf'] ?? []);
        $googleFonts = [];
        if (is_array($rawGf)) {
            foreach ($rawGf as $entry) {
                $family = sanitize_text_field((string) ($entry['family'] ?? ''));
                $weights = sanitize_text_field(trim((string) ($entry['weights'] ?? '')));
                if ($family !== '') {
                    $googleFonts[] = new GoogleFontSettings(
                        $family,
                        $weights !== '' ? $weights : '100;200;300;400;500;600;700;800;900',
                    );
                }
            }
        }

        $rawCf = wp_unslash($_POST['upb_cf'] ?? []);
        $customFonts = [];
        if (is_array($rawCf)) {
            foreach ($rawCf as $entry) {
                $family = sanitize_text_field((string) ($entry['family'] ?? ''));
                $attachmentId = absint($entry['attachment_id'] ?? 0);
                $weight = sanitize_text_field((string) ($entry['weight'] ?? '400'));
                if ($family !== '' && $attachmentId > 0) {
                    $customFonts[] = new CustomFontSettings($family, $attachmentId, $weight);
                }
            }
        }

        ($this->saveFontSettings)(
            new FontSettings($googleFonts, $customFonts),
        );
    }

    /**
     * @return array{
     *     updated: bool,
     *     googleFonts: array<int, array{family: string, weights: string}>,
     *     customFonts: array<int, array{family: string, attachment_id: int, weight: string, file_name: string}>,
     *     weightOptions: array<int, array{value: string, label: string}>,
     *     nonce: array{name: string, value: string}
     * }
     */
    private function fontLibraryViewData(): array
    {
        $updated = false;

        // Keep the duplicate settings shell on the same guarded font write
        // path as the existing branding screen so rollout cannot drift.
        if (
            isset($_POST[self::FONTS_NONCE_FIELD])
            && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::FONTS_NONCE_FIELD])),
                self::FONTS_NONCE_ACTION
            )
            && $this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            $this->saveFontsFromPost();
            $updated = true;
        }

        $fonts = ($this->loadSettings)()->brandStyles()->fonts();

        return [
            'updated' => $updated,
            'googleFonts' => array_values(array_map(
                static fn(GoogleFontSettings $font): array => $font->toArray(),
                $fonts->googleFonts(),
            )),
            'customFonts' => array_values(array_map(
                static fn(CustomFontSettings $font): array => [
                    'family' => $font->family(),
                    'attachment_id' => $font->attachmentId(),
                    'weight' => $font->weight(),
                    'file_name' => $font->attachmentId() > 0
                        ? basename((string) get_attached_file($font->attachmentId()))
                        : '',
                ],
                $fonts->customFonts(),
            )),
            'weightOptions' => [
                ['value' => '100', 'label' => _x('Thin', 'Page Builder', 'uncanny-automator')],
                ['value' => '200', 'label' => _x('Extra light', 'Page Builder', 'uncanny-automator')],
                ['value' => '300', 'label' => _x('Light', 'Page Builder', 'uncanny-automator')],
                ['value' => '400', 'label' => _x('Regular', 'Page Builder', 'uncanny-automator')],
                ['value' => '500', 'label' => _x('Medium', 'Page Builder', 'uncanny-automator')],
                ['value' => '600', 'label' => _x('Semi bold', 'Page Builder', 'uncanny-automator')],
                ['value' => '700', 'label' => _x('Bold', 'Page Builder', 'uncanny-automator')],
                ['value' => '800', 'label' => _x('Extra bold', 'Page Builder', 'uncanny-automator')],
                ['value' => '900', 'label' => _x('Black', 'Page Builder', 'uncanny-automator')],
            ],
            'nonce' => [
                'name' => self::FONTS_NONCE_FIELD,
                'value' => wp_create_nonce(self::FONTS_NONCE_ACTION),
            ],
        ];
    }

    /**
     * @return array{
     *     updated: bool,
     *     error: string,
     *     warning: string,
     *     tokenGroups: array<int, array{key: string, label: string, fields: array<int, array{key: string, label: string, value: string, isColor: bool}>}>,
     *     hiddenFields: array<int, array{key: string, label: string, value: string, isColor: bool}>,
     *     linkFields: array<int, array{
     *         key: string,
     *         label: string,
     *         value: string,
     *         default: string,
     *         control: string,
     *         isColor: bool,
     *         options?: array<int, array{value: string, label: string}>
     *     }>,
     *     lockedKeys: array{tokens: string, typography: string},
     *     typographyRoles: array<string, array<string, string>>,
     *     typographyDefaults: array<string, array<string, string>>,
     *     roleDefinitions: array<int, array{key: string, label: string, description: string, preview: string, fields: array<int, array{key: string, label: string, control: string}>}>,
     *     fontFamilyCatalog: array<int, array{key: string, label: string, options: array<int, array{label: string, value: string, source: string}>}>,
     *     nonce: array{name: string, value: string}
     * }
     */
    private function typographySettingsViewData(): array
    {
        $updated = false;
        $error = '';
        $warning = '';

        if (
            isset($_POST[self::DESIGN_NONCE_FIELD])
            && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::DESIGN_NONCE_FIELD])),
                self::DESIGN_NONCE_ACTION
            )
            && $this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            try {
                $profile = $this->buildProfileFromPost();
                $artifactsQueued = $this->designStandardsService->save($profile);
                $updated = true;
                if (!$artifactsQueued) {
                    $warning = DesignStandardsService::workingCanvasRefreshWarning()['message'];
                }
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $profile = $this->designStandardsService->resolve();
        $lockedKeys = $profile->lockedKeys();

        return [
            'updated' => $updated,
            'error' => $error,
            'warning' => $warning,
            'tokenGroups' => DesignSettingsFields::visibleTokenGroups($profile),
            'hiddenFields' => DesignSettingsFields::hiddenTokenFields($profile),
            'linkFields' => DesignSettingsFields::linkFields($profile),
            'lockedKeys' => [
                'tokens' => implode(', ', $lockedKeys['tokens'] ?? []),
                'typography' => implode(', ', $lockedKeys['typography'] ?? []),
            ],
            'typographyRoles' => $profile->typography()->toRoleArray(),
            'typographyDefaults' => DesignStandardsProfile::defaults()->typography()->toRoleArray(),
            'roleDefinitions' => DesignSettingsFields::typographyRoleDefinitions(),
            'fontFamilyCatalog' => $this->availableFontFamilies->catalog(),
            'nonce' => [
                'name' => self::DESIGN_NONCE_FIELD,
                'value' => wp_create_nonce(self::DESIGN_NONCE_ACTION),
            ],
        ];
    }

    // ── POST Processing ─────────────────────────────────

    private function buildProfileFromPost(): DesignStandardsProfile
    {
        $current = $this->designStandardsService->loadProfile();
        $tokens = $this->buildTokenProfileFromPost($current->tokens());
        $typography = $this->buildTypographyProfileFromPost($current->typography());
        $lockedKeys = $this->buildLockedKeysFromPost($current->lockedKeys());

        return new DesignStandardsProfile(
            $tokens,
            $current->breakpoints(),
            $typography,
            $lockedKeys,
        );
    }

    private function buildTypographyProfileFromPost(TypographyProfile $current): TypographyProfile
    {
        if (!isset($_POST['ds_typography'])) {
            return $current;
        }

        $raw = wp_unslash($_POST['ds_typography'] ?? []);
        if (!is_array($raw)) {
            return $current;
        }

        $submitted = TypographyRolesPostPayload::withoutCustomSentinel($raw['roles'] ?? null);

        /*
         * Each text style section posts only the roles it renders, so merge over
         * the current profile rather than rebuilding from the submission alone.
         * Without this, saving one section would drop every role belonging to the
         * others. Submitted roles win, so clearing a field still empties it.
         */
        return TypographyProfile::fromRolesArray(
            array_merge($current->toRoleArray(), $submitted),
        );
    }

    private function buildTokenProfileFromPost(BootstrapTokenProfile $current): BootstrapTokenProfile
    {
        if (!isset($_POST['ds_tokens'])) {
            return $current;
        }

        $raw = wp_unslash($_POST['ds_tokens'] ?? []);
        if (!is_array($raw)) {
            return $current;
        }

        $colorKeys = BootstrapTokenProfile::colorTokenKeys();
        $rgbDerived = BootstrapTokenProfile::rgbDerivedTokens();
        // Each duplicate settings tab can now submit a partial token subset.
        // Merge into the current profile so one tab does not erase another.
        $tokens = $current->toArray();

        foreach ($raw as $key => $value) {
            $key = sanitize_text_field((string) $key);
            if ($key === '') {
                continue;
            }

            if (in_array($key, $colorKeys, true)) {
                $sanitized = sanitize_hex_color((string) $value);
                $tokens[$key] = $sanitized !== null && $sanitized !== '' ? $sanitized : sanitize_text_field((string) $value);
            } else {
                $tokens[$key] = sanitize_text_field((string) $value);
            }
        }

        // Auto-compute RGB triplets for derived tokens.
        foreach ($rgbDerived as $rgbKey => $parentKey) {
            if (isset($tokens[$parentKey])) {
                $rgb = BootstrapTokenProfile::hexToRgb($tokens[$parentKey]);
                if ($rgb !== null) {
                    $tokens[$rgbKey] = $rgb;
                }
            }
        }

        return new BootstrapTokenProfile($tokens);
    }

    /**
     * @param array{tokens: string[], typography: string[]} $currentLockedKeys
     * @return array{tokens: string[], typography: string[]}
     */
    private function buildLockedKeysFromPost(array $currentLockedKeys): array
    {
        if (!isset($_POST['ds_locked'])) {
            return $currentLockedKeys;
        }

        $rawLocked = wp_unslash($_POST['ds_locked'] ?? []);
        if (!is_array($rawLocked)) {
            return $currentLockedKeys;
        }

        // Each duplicate settings tab submits only its own lock lane. Preserve
        // the untouched lane so one form does not clear another tab's state.
        return [
            'tokens' => array_key_exists('tokens', $rawLocked)
                ? $this->normalizeLockedKeysString($rawLocked['tokens'])
                : ($currentLockedKeys['tokens'] ?? []),
            'typography' => array_key_exists('typography', $rawLocked)
                ? $this->normalizeLockedKeysString($rawLocked['typography'])
                : ($currentLockedKeys['typography'] ?? []),
        ];
    }

    /** @return string[] */
    private function normalizeLockedKeysString(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $keys = array_map('trim', explode(',', $value));
        $keys = array_filter($keys, static fn(string $key): bool => $key !== '');
        $keys = array_map(static fn(string $key): string => sanitize_text_field($key), $keys);

        return array_values($keys);
    }
}
