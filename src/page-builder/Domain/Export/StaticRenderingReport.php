<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

final class StaticRenderingReport
{
    /**
     * @param array<int, array<string, mixed>> $records
     */
    public function __construct(private readonly array $records = [])
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function records(): array
    {
        return $this->records;
    }

    public function isSafe(): bool
    {
        foreach ($this->records as $record) {
            if (($record['status'] ?? '') === 'failed') {
                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        foreach ($this->records as $record) {
            if (($record['status'] ?? '') === 'failed') {
                return (string) ($record['message'] ?? 'Static rendering failed.');
            }
        }

        return 'Static rendering passed.';
    }

    public function merge(self $other): self
    {
        return new self([...$this->records, ...$other->records]);
    }
}
