<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Domain\Publishing\PageArtifactCandidate;

interface PageArtifactBuilderInterface
{
    public function buildForPage(
        int $pageId,
        int $createdBy,
        ?int $expectedPageGeneration = null,
    ): PageArtifactCandidate;
}
