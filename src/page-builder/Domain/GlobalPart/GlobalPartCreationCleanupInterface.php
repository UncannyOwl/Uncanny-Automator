<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

/**
 * Removes a global part whose create operation did not reach a usable state.
 */
interface GlobalPartCreationCleanupInterface
{
    public function removeCreatedGlobalPart(int $globalPartId): void;
}
