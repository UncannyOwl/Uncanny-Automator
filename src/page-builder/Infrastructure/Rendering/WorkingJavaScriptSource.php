<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;

/**
 * Keeps mutable JavaScript reads on editor/export paths only.
 */
final class WorkingJavaScriptSource
{
    public function __construct(
        private readonly PageJavaScriptRuntimeService $runtime,
    ) {}

    public function page(int $pageId): string
    {
        return $this->runtime->readForPage($pageId);
    }

    public function globalPart(int $globalPartId): string
    {
        return $this->runtime->readForGlobalPart($globalPartId);
    }
}
