<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

interface PublishedPageArtifactRepositoryInterface
{
    /**
     * Insert one immutable publication artifact. This does not publish it.
     */
    public function insert(PublishedPageArtifact $artifact): PublishedPageArtifact;

    /**
     * Resolve one exact artifact only when it belongs to the requested page.
     */
    public function findForPage(int $pageId, int $artifactId): ?PublishedPageArtifact;

    /**
     * Resolve only the owning page ID for pointer-integrity diagnostics.
     * This method never authorizes rendering.
     */
    public function pageIdForArtifact(int $artifactId): ?int;

    /** @return PublishedPageArtifact[] */
    public function historyForPage(int $pageId, int $limit = 20): array;

    /**
     * Bound artifact history while always retaining the artifact currently
     * selected by page state.
     */
    public function pruneForPage(int $pageId): int;

    /**
     * Remove artifacts only as part of permanent page deletion.
     */
    public function deleteForPage(int $pageId): int;
}
