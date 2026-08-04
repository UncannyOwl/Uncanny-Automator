<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

use UncannyPageBuilder\Domain\Settings\DesignDirectionSettings;
use UncannyPageBuilder\Domain\Settings\Settings;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;

final class SaveDesignDirectionSettingsUseCase
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settingsRepository,
    ) {}

    public function __invoke(DesignDirectionSettings $designDirection): Settings
    {
        return $this->settingsRepository->mutate(
            static fn (Settings $settings): Settings => $settings->withDesignDirection($designDirection),
        );
    }
}
