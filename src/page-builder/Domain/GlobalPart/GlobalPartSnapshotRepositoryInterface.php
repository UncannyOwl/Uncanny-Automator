<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

use UncannyPageBuilder\Domain\Compiler\CompiledOutput;
use UncannyPageBuilder\Domain\Section\SectionCollection;

/**
 * Persists one complete global-part source and its compiled projection.
 */
interface GlobalPartSnapshotRepositoryInterface extends GlobalPartRepositoryInterface
{
    public function saveSnapshot(
        int $globalPartId,
        SectionCollection $sections,
        CompiledOutput $compiled,
    ): void;
}
