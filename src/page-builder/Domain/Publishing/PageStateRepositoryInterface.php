<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

interface PageStateRepositoryInterface
{
    public function findForPage(int $pageId): ?PagePublicationState;

    /**
     * Create the page-owned state if it does not exist. Repeated initialization
     * must never clear an existing publication pointer.
     */
    public function initialize(PagePublicationState $state): PagePublicationState;

    /**
     * Save draft-owned title and slug fields without changing publication data.
     * The same transaction must advance the existing page source generation.
     */
    public function saveDraftDetails(
        PagePublicationState $state,
        int $expectedGeneration,
    ): PagePublicationState;

    /**
     * Change only how the durable working draft reopens. This metadata does not
     * mutate page source and therefore must not advance its generation.
     */
    public function saveDraftResumePolicy(
        int $pageId,
        DraftResumePolicy $policy,
    ): PagePublicationState;

    /**
     * Remove state only during permanent page deletion, never ordinary trashing.
     */
    public function deleteForPage(int $pageId): int;
}
