<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Access;

use UncannyPageBuilder\Domain\Access\PageBuilderAllowedCapabilityPort;

final class GetPageBuilderAllowedCapabilities
{
    public function __construct(
        private readonly PageBuilderAllowedCapabilityPort $allowedCapabilityPort,
    ) {}

    /**
     * @return list<string>
     */
    public function getAllowedCapabilities(): array
    {
        return $this->allowedCapabilityPort->getAllowedCapabilities();
    }

    public function currentUserHasAllowedCapability(): bool
    {
        foreach ($this->getAllowedCapabilities() as $capability) {
            if ($this->allowedCapabilityPort->userHasCapability($capability)) {
                return true;
            }
        }

        return false;
    }
}
