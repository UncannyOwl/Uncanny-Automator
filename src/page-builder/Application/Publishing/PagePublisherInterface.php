<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Domain\Publishing\PageArtifactCandidate;

interface PagePublisherInterface
{
    public function publish(PageArtifactCandidate $candidate): PublishPageResult;
}
