<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

use UncannyPageBuilder\Domain\Settings\ToolSettings;

/**
 * Read-only access seam for live tool settings that gate runtime behavior.
 */
final class ToolSettingsAccess
{
    public function __construct(
        private readonly LoadSettingsUseCase $loadSettings,
    ) {}

    public function pageCustomJavaScriptEnabled(): bool
    {
        return ($this->loadSettings)()->tools()->pageCustomJavaScriptEnabled();
    }

    public function globalPartCustomJavaScriptEnabled(): bool
    {
        return ($this->loadSettings)()->tools()->globalPartCustomJavaScriptEnabled();
    }

    public function libraryEnabled(string $slug): bool
    {
        return ($this->loadSettings)()->tools()->libraryEnabled($slug);
    }

    /**
     * @return array{anime: bool, swiper: bool}
     */
    public function approvedLibraries(): array
    {
        return ($this->loadSettings)()->tools()->approvedLibraries();
    }

    /**
     * @return list<string>
     */
    public function approvedLibrarySlugs(): array
    {
        return array_values(array_keys(array_filter(
            $this->approvedLibraries(),
            static fn(bool $enabled): bool => $enabled,
        )));
    }
}
