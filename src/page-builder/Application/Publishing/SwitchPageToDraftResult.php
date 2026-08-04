<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

final class SwitchPageToDraftResult
{
    public function __construct(
        private readonly string $previousStatus,
        private readonly string $status,
    ) {}

    public function previousStatus(): string
    {
        return $this->previousStatus;
    }

    public function status(): string
    {
        return $this->status;
    }
}
