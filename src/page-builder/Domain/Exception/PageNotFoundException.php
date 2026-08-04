<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class PageNotFoundException extends \RuntimeException
{
    public function __construct(int $pageId)
    {
        parent::__construct("Page {$pageId} not found.");
    }
}
