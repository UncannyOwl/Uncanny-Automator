<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Access;

/**
 * Reports if users can start new Page Builder pages.
 *
 * Existing owned pages do not use this gate.
 */
interface PageBuilderAvailabilityInterface
{
    public function allowsNewPages(): bool;
}
