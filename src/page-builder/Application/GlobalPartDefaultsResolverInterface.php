<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

/**
 * Resolves the current reusable part selected as the site default.
 */
interface GlobalPartDefaultsResolverInterface
{
    /** @return array<string, mixed>|null */
    public function resolveForType(GlobalPartType $type): ?array;
}
