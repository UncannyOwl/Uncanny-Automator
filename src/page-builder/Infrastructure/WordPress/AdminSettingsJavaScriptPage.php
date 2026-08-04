<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Settings\LoadSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveToolSettingsUseCase;
use UncannyPageBuilder\Domain\Settings\ToolSettings;

final class AdminSettingsJavaScriptPage
{
    private const NONCE_ACTION = 'uncanny_page_builder_save_tools';
    private const NONCE_FIELD = 'uncanny_page_builder_tools_nonce';
    private const FIELD_PAGE_CUSTOM_JAVASCRIPT = 'upb_tools_page_custom_javascript';
    private const FIELD_GLOBAL_PART_CUSTOM_JAVASCRIPT = 'upb_tools_global_part_custom_javascript';
    private const FIELD_APPROVED_LIBRARIES = 'upb_tools_approved_libraries';

    public function __construct(
        private readonly LoadSettingsUseCase $loadSettings,
        private readonly SaveToolSettingsUseCase $saveTools,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
    ) {
    }

    // Section: JavaScript settings content
    public function render(): void
    {
        [
            'updated' => $updated,
            'toolSettings' => $toolSettings,
            'nonce' => $nonce,
            'customJavaScriptFields' => $customJavaScriptFields,
            'approvedLibraryField' => $approvedLibraryField,
            'approvedLibraries' => $approvedLibraries,
        ] = $this->viewData();

        include __DIR__ . '/../../Presentation/Settings/javascript-section.php';
    }

    /**
     * @return array{
     *     updated: bool,
     *     toolSettings: ToolSettings,
     *     nonce: array{name: string, value: string},
     *     customJavaScriptFields: array{page: string, global_part: string},
     *     approvedLibraryField: string,
     *     approvedLibraries: list<array{slug: string, label: string, description: string, enabled: bool}>
     * }
     */
    private function viewData(): array
    {
        $updated = false;

        // Section: Persisted JavaScript settings
        if (
            isset($_POST[self::NONCE_FIELD])
            && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])),
                self::NONCE_ACTION
            )
            && $this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            ($this->saveTools)($this->submittedToolSettings());
            $updated = true;
        }

        $toolSettings = ($this->loadSettings)()->tools();

        return [
            'updated' => $updated,
            'toolSettings' => $toolSettings,
            'nonce' => [
                'name' => self::NONCE_FIELD,
                'value' => wp_create_nonce(self::NONCE_ACTION),
            ],
            'customJavaScriptFields' => [
                'page' => self::FIELD_PAGE_CUSTOM_JAVASCRIPT,
                'global_part' => self::FIELD_GLOBAL_PART_CUSTOM_JAVASCRIPT,
            ],
            'approvedLibraryField' => self::FIELD_APPROVED_LIBRARIES,
            'approvedLibraries' => [
                [
                    'slug' => ToolSettings::LIBRARY_ANIME,
                    'label' => 'Anime.js',
                    'description' => _x('Best for choreographed motion, timelines, easing, and interface animation.', 'Page Builder', 'uncanny-automator'),
                    'enabled' => $toolSettings->libraryEnabled(ToolSettings::LIBRARY_ANIME),
                ],
                [
                    'slug' => ToolSettings::LIBRARY_SWIPER,
                    'label' => 'Swiper',
                    'description' => _x('Best for sliders, carousels, galleries, and touch-friendly swipe interactions.', 'Page Builder', 'uncanny-automator'),
                    'enabled' => $toolSettings->libraryEnabled(ToolSettings::LIBRARY_SWIPER),
                ],
            ],
        ];
    }

    private function submittedToolSettings(): ToolSettings
    {
        // Section: <uo-switch> submits "1" when on and "0" when off, so every
        // control posts on each save. Read the value rather than testing for the
        // key, otherwise a switch turned off would still read as enabled. A
        // missing key means the control was never rendered, which is also off.
        $approvedLibraries = [];
        $rawLibraries = is_array($_POST[self::FIELD_APPROVED_LIBRARIES] ?? null)
            ? $_POST[self::FIELD_APPROVED_LIBRARIES]
            : [];

        foreach (ToolSettings::knownLibrarySlugs() as $slug) {
            $approvedLibraries[$slug] = self::submittedFlag($rawLibraries[$slug] ?? null);
        }

        return ToolSettings::fromArray([
            'custom_javascript' => [
                'page' => self::submittedFlag($_POST[self::FIELD_PAGE_CUSTOM_JAVASCRIPT] ?? null),
                'global_part' => self::submittedFlag($_POST[self::FIELD_GLOBAL_PART_CUSTOM_JAVASCRIPT] ?? null),
            ],
            'approved_libraries' => $approvedLibraries,
        ]);
    }

    /**
     * Resolves a submitted switch value to a boolean.
     *
     * Accepts the legacy checkbox shape too, where the key is only present when
     * the box was ticked, so a stale cached form still saves correctly.
     */
    private static function submittedFlag(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }

        return in_array(
            sanitize_text_field(wp_unslash(is_scalar($value) ? (string) $value : '')),
            ['1', 'on', 'true'],
            true,
        );
    }
}
