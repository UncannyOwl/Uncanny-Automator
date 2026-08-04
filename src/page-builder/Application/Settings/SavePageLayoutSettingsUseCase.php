<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Settings\PageLayoutSettings;
use UncannyPageBuilder\Domain\Settings\Settings;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;

final class SavePageLayoutSettingsUseCase
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settingsRepository,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
    ) {}

    public function __invoke(PageLayoutSettings $pageLayout): Settings
    {
        $generation = $this->sourceGenerations->globalGeneration();

        return $this->sourceGenerations->commitGlobal(
            $generation,
            fn (): Settings => $this->settingsRepository->mutate(
                static fn (Settings $settings): Settings => $settings->withPageLayout($pageLayout),
            ),
        );
    }
}
