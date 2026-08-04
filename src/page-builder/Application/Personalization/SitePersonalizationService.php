<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Personalization;

use UncannyPageBuilder\Domain\Personalization\SiteCustomInstructions;
use UncannyPageBuilder\Domain\Personalization\SitePersonalizationRepositoryInterface;

/**
 * Loads and saves site-wide Agent personalization.
 */
final class SitePersonalizationService
{
    public function __construct(
        private readonly SitePersonalizationRepositoryInterface $repository,
    ) {}

    public function loadCustomInstructions(): SiteCustomInstructions
    {
        return $this->repository->loadCustomInstructions();
    }

    public function saveCustomInstructions(string $value): SiteCustomInstructions
    {
        $instructions = SiteCustomInstructions::fromString($value);

        if ($instructions->isEmpty()) {
            $this->repository->clearCustomInstructions();
            return $instructions;
        }

        $this->repository->saveCustomInstructions($instructions);

        return $instructions;
    }
}
