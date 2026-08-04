<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class InvalidPageArtifactPointerException extends \RuntimeException
{
    public function __construct(
        private readonly int $pageId,
        private readonly int $artifactId,
    ) {
        parent::__construct("Artifact {$artifactId} cannot be published for page {$pageId} because it is missing or belongs to another page.");
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function artifactId(): int
    {
        return $this->artifactId;
    }
}
