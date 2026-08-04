<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

final class PageSourceImageImportResult
{
    /**
     * @param array<string, string> $urlMap
     * @param list<int> $attachmentIds
     * @param list<string> $warnings
     */
    public function __construct(
        private readonly array $urlMap,
        private readonly array $attachmentIds,
        private readonly array $warnings = [],
    ) {}

    /** @return array<string, string> */
    public function urlMap(): array
    {
        return $this->urlMap;
    }

    /** @return list<int> */
    public function attachmentIds(): array
    {
        return $this->attachmentIds;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
