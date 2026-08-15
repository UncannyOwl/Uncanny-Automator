<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Application\Rendering\PublishedPageAssets;
use UncannyPageBuilder\Application\Rendering\PublishedPageRuntimeUnavailable;
use UncannyPageBuilder\Domain\Publishing\PageDeactivationFallback;

interface PageDeactivationFallbackAssetResolverInterface
{
    /** @throws PublishedPageRuntimeUnavailable */
    public function resolveFallback(PageDeactivationFallback $fallback): PublishedPageAssets;
}
