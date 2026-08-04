<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

use UncannyPageBuilder\Domain\Compiler\CompiledOutput;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Persists one canonical global-part source row without rewriting sibling rows.
 */
interface GlobalPartSourceUpdateRepositoryInterface extends GlobalPartRepositoryInterface
{
    /**
     * The source and compiled projection must share one generation-checked
     * persistence boundary. Every other stored source row must remain untouched.
     */
    public function saveSource(
        int $globalPartId,
        Section $source,
        CompiledOutput $compiled,
        int $expectedGeneration,
    ): void;
}
