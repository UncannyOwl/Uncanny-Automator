<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifact;

interface PublishedPageAssetResolverInterface
{
    /**
     * @throws PublishedPageRuntimeUnavailable
     */
    public function resolve(PublishedPageArtifact $artifact): PublishedPageAssets;
}
