<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Domain\Publishing\PagePublicationState;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshot;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Captures editable page-owned source for one publication identity.
 */
interface PageSourceSnapshotCaptureInterface
{
    public function capture(
        int $pageId,
        string $sourceRevisionHash,
        int $pageGeneration,
        PagePublicationState $state,
        int $createdBy,
        ?ShellMode $shellMode = null,
        ?bool $shellModeExplicit = null,
    ): PageSourceSnapshot;
}
