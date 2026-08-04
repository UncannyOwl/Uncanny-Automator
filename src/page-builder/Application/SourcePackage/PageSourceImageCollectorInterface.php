<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

interface PageSourceImageCollectorInterface
{
    /**
     * @param array<string, mixed> $pageSource
     */
    public function collect(array $pageSource): PageSourceImageCollection;
}
