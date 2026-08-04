<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

interface SwitchPageToDraftInterface
{
    public function switch(int $pageId): SwitchPageToDraftResult;
}
