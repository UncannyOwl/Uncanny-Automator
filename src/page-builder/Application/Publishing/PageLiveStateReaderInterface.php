<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Domain\Publishing\PageLiveState;

interface PageLiveStateReaderInterface
{
    public function forPage(int $pageId): PageLiveState;
}
