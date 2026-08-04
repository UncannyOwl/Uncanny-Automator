<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Settings\LoadSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveDesignDirectionSettingsUseCase;
use UncannyPageBuilder\Domain\Settings\DesignDirectionSettings;
use UncannyPageBuilder\Domain\Personalization\SiteCustomInstructions;

final class AdminPersonalizationPage
{
    private const NONCE_ACTION = 'uncanny_page_builder_save_personalization';
    private const NONCE_FIELD = 'uncanny_page_builder_personalization_nonce';
    private const FIELD_CUSTOM_INSTRUCTIONS = 'custom_instructions';

    public function __construct(
        private readonly LoadSettingsUseCase $loadSettings,
        private readonly SaveDesignDirectionSettingsUseCase $saveDesignDirection,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
    ) {}

    public function renderPlainPersonalizationSettingsContent(): void
    {
        [
            'updated' => $updated,
            'customInstructions' => $customInstructions,
            'maxCharacters' => $maxCharacters,
            'fieldCustomInstructions' => $fieldCustomInstructions,
            'nonce' => $nonce,
        ] = $this->personalizationViewData();

        include __DIR__ . '/../../Presentation/Settings/personalization-settings-form.php';
    }

    /**
     * @return array{
     *     updated: bool,
     *     customInstructions: string,
     *     maxCharacters: int,
     *     fieldCustomInstructions: string,
     *     nonce: array{name: string, value: string}
     * }
     */
    private function personalizationViewData(): array
    {
        $updated = false;

        // Save site-wide Agent personalization.
        if (
            isset($_POST[self::NONCE_FIELD])
            && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])),
                self::NONCE_ACTION
            )
            && $this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            $rawInstructions = $_POST[self::FIELD_CUSTOM_INSTRUCTIONS] ?? '';
            $instructions = is_string($rawInstructions) ? wp_unslash($rawInstructions) : '';
            ($this->saveDesignDirection)(
                new DesignDirectionSettings(SiteCustomInstructions::fromString($instructions)),
            );
            $updated = true;
        }

        return [
            'updated' => $updated,
            'customInstructions' => ($this->loadSettings)()->designDirection()->customInstructions()->text(),
            'maxCharacters' => SiteCustomInstructions::MAX_CHARACTERS,
            'fieldCustomInstructions' => self::FIELD_CUSTOM_INSTRUCTIONS,
            'nonce' => [
                'name' => self::NONCE_FIELD,
                'value' => wp_create_nonce(self::NONCE_ACTION),
            ],
        ];
    }
}
