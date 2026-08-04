<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

interface PageSourceArchiveArtifactStoreInterface
{
    /**
     * Takes ownership of the artifact file and removes it when storage fails.
     */
    public function store(int $pageId, PageSourceArchiveArtifact $artifact): string;

    public function take(int $pageId, string $token): ?PageSourceArchiveArtifact;

    public function delete(PageSourceArchiveArtifact $artifact): void;
}
