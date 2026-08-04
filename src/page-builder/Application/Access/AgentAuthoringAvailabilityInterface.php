<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Access;

/**
 * Reports if Uncanny Agent can author Page Builder content.
 */
interface AgentAuthoringAvailabilityInterface
{
    public function isAvailable(): bool;
}
