<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\Reusable\Reusable;

final class DeleteReusableResult
{
    public function __construct(
        private readonly Reusable $reusable,
        private readonly bool $forceDeleted,
    ) {}

    public function reusable(): Reusable
    {
        return $this->reusable;
    }

    public function forceDeleted(): bool
    {
        return $this->forceDeleted;
    }
}
