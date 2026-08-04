<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Editing;

/**
 * Plain-text before/after source diff for agent-readable tool results.
 */
final class CompactSourceDiff
{
    public function __construct(
        private readonly string $label,
        private readonly string $body,
        private readonly bool $changed,
        private readonly bool $truncated,
    ) {}

    public function label(): string
    {
        return $this->label;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function changed(): bool
    {
        return $this->changed;
    }

    public function truncated(): bool
    {
        return $this->truncated;
    }
}
