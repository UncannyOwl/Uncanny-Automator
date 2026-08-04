<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Access;

interface PageBuilderAllowedCapabilityPort
{
    /**
     * @return list<string>
     */
    public function getAllowedCapabilities(): array;

    public function userHasCapability(string $capability): bool;
}
