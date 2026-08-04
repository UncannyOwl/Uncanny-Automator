<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

/**
 * Validated upload boundary shared by legacy JSON and portable ZIP archives.
 */
final class UploadedPageSource
{
    /**
     * @param array<string, mixed> $payload
     * @param list<PageSourceImage> $images
     * @param list<string> $warnings
     */
    public function __construct(
        private readonly array $payload,
        private readonly array $images = [],
        private readonly array $warnings = [],
        private readonly string $format = 'json',
    ) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

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

    public function format(): string
    {
        return $this->format;
    }
}
