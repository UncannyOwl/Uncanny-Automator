<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

final class PageSourceImageCollection
{
    /**
     * @param list<PageSourceImage> $images
     * @param list<string> $warnings
     */
    public function __construct(
        private readonly array $images,
        private readonly array $warnings = [],
    ) {}

    /** @return list<PageSourceImage> */
    public function images(): array
    {
        return $this->images;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
