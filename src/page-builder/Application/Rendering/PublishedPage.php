<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifact;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * The exact immutable artifact and runtime selected for one public request.
 */
final class PublishedPage
{
    public function __construct(
        private readonly PublishedPageArtifact $artifact,
        private readonly PublishedPageAssets $assets,
    ) {
        if ($artifact->id() === null) {
            throw new \InvalidArgumentException('A public page requires a stored artifact identity.');
        }
    }

    public function artifact(): PublishedPageArtifact
    {
        return $this->artifact;
    }

    public function assets(): PublishedPageAssets
    {
        return $this->assets;
    }

    public function pageId(): int
    {
        return $this->artifact->pageId();
    }

    public function artifactId(): int
    {
        return (int) $this->artifact->id();
    }

    public function shellMode(): ShellMode
    {
        return $this->artifact->shellMode();
    }

    public function html(): string
    {
        return $this->artifact->html();
    }

    public function css(): string
    {
        return $this->artifact->css();
    }

    public function customJavaScript(): string
    {
        return $this->assets->resolveReferences($this->artifact->customJavaScript());
    }

    public function cacheKey(): string
    {
        return sprintf(
            'upb-published-%d-%s',
            $this->artifactId(),
            $this->artifact->contentHash(),
        );
    }
}
