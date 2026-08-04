<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\History;

use UncannyPageBuilder\Application\Controls\PageDetails;

interface PageDetailsHistoryRestorerInterface
{
    /**
     * @param array{title: string, slug: string} $target
     * @param array{title: string, slug: string} $expectedCurrent
     */
    public function restoreFromHistory(
        int $pageId,
        array $target,
        array $expectedCurrent,
        int $updatedBy,
    ): PageDetails;
}
