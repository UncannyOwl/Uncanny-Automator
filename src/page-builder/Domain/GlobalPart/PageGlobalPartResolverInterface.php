<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

interface PageGlobalPartResolverInterface
{
    /**
     * Resolve the global part a page's shell slot would actually render.
     *
     * The page override wins (-1 suppresses the slot, >0 selects a specific
     * part), then the assigned site default, then the first published part
     * of the type. Returns null when the slot resolves to no part on this
     * page.
     *
     * @return array<string, mixed>|null
     */
    public function resolveForPage(int $pageId, GlobalPartType $type): ?array;
}
