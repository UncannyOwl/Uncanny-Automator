<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

/**
 * Reads unfiltered public WordPress fields for publication-integrity checks.
 */
interface PublicPageIdentityReaderInterface
{
    public function read(int $pageId): ?PublicPageIdentity;
}
