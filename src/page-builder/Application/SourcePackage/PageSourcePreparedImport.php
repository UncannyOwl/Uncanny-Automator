<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

final class PageSourcePreparedImport
{
    /**
     * @param array<string, mixed> $payload
     * @param list<int> $attachmentIds
     * @param list<string> $warnings
     */
    public function __construct(
        private readonly array $payload,
        private readonly array $attachmentIds,
        private readonly array $warnings,
        private readonly int $importedImageCount,
    ) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
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

    public function importedImageCount(): int
    {
        return $this->importedImageCount;
    }
}
