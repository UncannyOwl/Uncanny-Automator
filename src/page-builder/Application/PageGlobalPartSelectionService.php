<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelection;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelectionRepositoryInterface;

/**
 * Coordinates per-page header/footer assignments with page source ordering.
 */
final class PageGlobalPartSelectionService
{
    public function __construct(
        private readonly PageGlobalPartSelectionRepositoryInterface $repository,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
        private readonly ?PageSourceMutation $pageSource = null,
    ) {}

    public function selectionForPage(int $pageId): PageGlobalPartSelection
    {
        $this->assertPageId($pageId);

        return $this->repository->loadForPage($pageId);
    }

    /**
     * Returns true only when the persisted selection changed.
     */
    public function saveForPage(int $pageId, PageGlobalPartSelection $selection): bool
    {
        $this->assertPageId($pageId);

        /*
         * Capture before reading. If another request changes the selection
         * while this save is being prepared, commitPage rejects this snapshot
         * before either header/footer value can overwrite the newer pair.
         */
        $generation = $this->sourceGenerations->pageGeneration($pageId);
        if ($this->repository->loadForPage($pageId)->equals($selection)) {
            return false;
        }

        $write = fn(): mixed => $this->repository->saveForPage($pageId, $selection);
        if ($this->pageSource instanceof PageSourceMutation) {
            $this->pageSource->runExpected($pageId, $generation, $write);
        } else {
            $this->sourceGenerations->commitPage($pageId, $generation, $write);
        }

        return true;
    }

    public function useSiteDefaults(int $pageId): bool
    {
        return $this->saveForPage($pageId, PageGlobalPartSelection::siteDefaults());
    }

    public function removeParts(int $pageId): bool
    {
        return $this->saveForPage($pageId, PageGlobalPartSelection::noParts());
    }

    private function assertPageId(int $pageId): void
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be positive.');
        }
    }
}
