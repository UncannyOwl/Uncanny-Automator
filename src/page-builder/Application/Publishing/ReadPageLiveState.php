<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Application\Rendering\PublishedPageReaderInterface;
use UncannyPageBuilder\Application\Rendering\PublishedPageStatus;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationSnapshot;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Publishing\PageLiveState;

/** Reads editor status through the same integrity gate used by public rendering. */
final class ReadPageLiveState implements PageLiveStateReaderInterface
{
    public function __construct(
        private readonly PublishedPageReaderInterface $publishedPages,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
    ) {}

    public function forPage(int $pageId): PageLiveState
    {
        $read = $this->publishedPages->read($pageId);
        if (in_array($read->status(), [PublishedPageStatus::NotManaged, PublishedPageStatus::Unpublished], true)) {
            return PageLiveState::Draft;
        }

        $artifact = $read->page()?->artifact();
        if ($artifact === null) {
            return PageLiveState::ChangesNotLive;
        }

        $published = SourceGenerationSnapshot::fromDependencies($artifact->dependencies());
        if ($published === null || $published->pageId() !== $pageId) {
            return PageLiveState::ChangesNotLive;
        }

        return $published->pageGeneration() === $this->sourceGenerations->pageGeneration($pageId)
            && $published->globalGeneration() === $this->sourceGenerations->globalGeneration()
                ? PageLiveState::Live
                : PageLiveState::ChangesNotLive;
    }
}
