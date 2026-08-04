<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

/**
 * Moves a page out of public view without changing its published artifact.
 */
final class SwitchPageToDraft implements SwitchPageToDraftInterface
{
    public function __construct(
        private readonly PageDraftStatusPortInterface $statuses,
    ) {}

    public function switch(int $pageId): SwitchPageToDraftResult
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be positive.');
        }

        $previousStatus = $this->statuses->currentStatus($pageId);
        if ($previousStatus === 'trash') {
            throw new \InvalidArgumentException('Restore this page before moving it to draft.');
        }
        if ($previousStatus !== 'draft') {
            $this->statuses->setDraft($pageId);
        }

        return new SwitchPageToDraftResult($previousStatus, 'draft');
    }
}
