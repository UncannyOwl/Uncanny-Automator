<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

final class DeleteReusableCommand
{
    public function __construct(
        private readonly int $reusableId,
        private readonly bool $forceDelete = false,
    ) {}

    public function reusableId(): int
    {
        return $this->reusableId;
    }

    public function forceDelete(): bool
    {
        return $this->forceDelete;
    }
}
