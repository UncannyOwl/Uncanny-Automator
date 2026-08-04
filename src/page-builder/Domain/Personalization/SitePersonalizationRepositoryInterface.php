<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Personalization;

interface SitePersonalizationRepositoryInterface
{
    public function loadCustomInstructions(): SiteCustomInstructions;

    public function saveCustomInstructions(SiteCustomInstructions $instructions): void;

    public function clearCustomInstructions(): void;
}
