<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\Personalization\SiteCustomInstructions;
use UncannyPageBuilder\Domain\Personalization\SitePersonalizationRepositoryInterface;
use UncannyPageBuilder\Domain\Settings\Settings;

/**
 * Persists design-direction instructions inside the Settings aggregate.
 */
final class WpSettingsSitePersonalizationRepository implements SitePersonalizationRepositoryInterface
{
    private WpSettingsRepository $settingsRepository;

    public function __construct(?WpSettingsRepository $settingsRepository = null)
    {
        $this->settingsRepository = $settingsRepository ?? new WpSettingsRepository();
    }

    public function loadCustomInstructions(): SiteCustomInstructions
    {
        return $this->settingsRepository
            ->load()
            ->designDirection()
            ->customInstructions();
    }

    public function saveCustomInstructions(SiteCustomInstructions $instructions): void
    {
        $this->settingsRepository->mutate(
            static fn (Settings $settings): Settings => $settings->withDesignDirection(
                $settings->designDirection()->withCustomInstructions($instructions),
            ),
        );
    }

    public function clearCustomInstructions(): void
    {
        $this->settingsRepository->mutate(
            static fn (Settings $settings): Settings => $settings->withDesignDirection(
                $settings->designDirection()->clearCustomInstructions(),
            ),
        );
    }
}
