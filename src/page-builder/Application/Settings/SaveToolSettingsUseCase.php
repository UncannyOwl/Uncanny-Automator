<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Settings\Settings;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;
use UncannyPageBuilder\Domain\Settings\ToolSettings;

final class SaveToolSettingsUseCase
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settingsRepository,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
    ) {}

    public function __invoke(ToolSettings $tools): Settings
    {
        $generation = $this->sourceGenerations->globalGeneration();

        return $this->sourceGenerations->commitGlobal(
            $generation,
            fn (): Settings => $this->settingsRepository->mutate(
                static fn (Settings $settings): Settings => $settings->withTools($tools),
            ),
        );
    }
}
