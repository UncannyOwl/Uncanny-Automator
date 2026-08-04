<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

interface OriginalPageContentReaderInterface
{
    /**
     * Read the preserved WordPress-owned body without exposing a legacy Page
     * Builder projection when no valid published pointer exists.
     */
    public function publicContent(int $pageId): string;
}
