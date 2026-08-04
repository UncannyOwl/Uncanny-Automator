<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\Personalization\SiteCustomInstructions;
use UncannyPageBuilder\Domain\Personalization\SitePersonalizationRepositoryInterface;

/**
 * Legacy adapter kept on the new Settings aggregate row.
 */
final class WpSitePersonalizationRepository implements SitePersonalizationRepositoryInterface
{
    private WpSettingsSitePersonalizationRepository $repository;

    public function __construct(?WpSettingsSitePersonalizationRepository $repository = null)
    {
        $this->repository = $repository ?? new WpSettingsSitePersonalizationRepository();
    }

    public function loadCustomInstructions(): SiteCustomInstructions
    {
        return $this->repository->loadCustomInstructions();
    }

    public function saveCustomInstructions(SiteCustomInstructions $instructions): void
    {
        $this->repository->saveCustomInstructions($instructions);
    }

    public function clearCustomInstructions(): void
    {
        $this->repository->clearCustomInstructions();
    }
}
