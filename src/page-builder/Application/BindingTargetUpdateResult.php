<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

final class BindingTargetUpdateResult
{
    /** @param string[] $warnings Advisory sanitization notes (e.g. logo rewritten to binding). */
    public function __construct(
        private readonly string $targetId,
        private readonly string $targetLabel,
        private readonly string $bindingId,
        private readonly ?string $targetRole = null,
        private readonly array $warnings = [],
    ) {}

    /** @return string[] */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function targetId(): string
    {
        return $this->targetId;
    }

    public function targetLabel(): string
    {
        return $this->targetLabel;
    }

    public function bindingId(): string
    {
        return $this->bindingId;
    }

    public function targetRole(): ?string
    {
        return $this->targetRole;
    }
}
