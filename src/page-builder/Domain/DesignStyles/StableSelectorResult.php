<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStyles;

/**
 * Outcome of resolving a stable selector for an element style commit.
 *
 * Carries the selector to patch CSS against, the (possibly promoted) HTML, and
 * whether identity promotion mutated the source.
 */
final class StableSelectorResult
{
    public function __construct(
        private readonly ?string $selector,
        private readonly string $html,
        private readonly bool $promoted,
    ) {}

    public static function unresolved(string $html): self
    {
        return new self(null, $html, false);
    }

    public function isResolved(): bool
    {
        return $this->selector !== null;
    }

    public function selector(): ?string
    {
        return $this->selector;
    }

    public function html(): string
    {
        return $this->html;
    }

    public function wasPromoted(): bool
    {
        return $this->promoted;
    }
}
