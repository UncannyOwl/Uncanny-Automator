<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/** Result of source-preserving inline declaration removal. */
final class InlineStylePatch
{
    /**
     * @param array<string, string> $removedDeclarations
     */
    public function __construct(
        private readonly string $html,
        private readonly array $removedDeclarations = [],
        private readonly bool $safe = true,
        private readonly string $reason = '',
    ) {}

    /** @param array<string, string> $removedDeclarations */
    public static function success(string $html, array $removedDeclarations = []): self
    {
        return new self($html, $removedDeclarations);
    }

    public static function unsafe(string $html, string $reason): self
    {
        return new self($html, [], false, $reason);
    }

    public function html(): string
    {
        return $this->html;
    }

    /** @return array<string, string> */
    public function removedDeclarations(): array
    {
        return $this->removedDeclarations;
    }

    public function isSafe(): bool
    {
        return $this->safe;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
