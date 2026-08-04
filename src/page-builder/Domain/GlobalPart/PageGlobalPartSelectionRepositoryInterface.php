<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

interface PageGlobalPartSelectionRepositoryInterface
{
    public function loadForPage(int $pageId): PageGlobalPartSelection;

    public function saveForPage(int $pageId, PageGlobalPartSelection $selection): void;
}
