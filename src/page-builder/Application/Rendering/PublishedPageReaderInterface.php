<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

interface PublishedPageReaderInterface
{
    public function read(int $pageId): PublishedPageReadResult;
}
