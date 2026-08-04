<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

final class DeleteCanvasCommand
{
    public function __construct(
        private readonly int $canvasId,
        private readonly bool $forceDelete = false,
    ) {}

    public function canvasId(): int
    {
        return $this->canvasId;
    }

    public function forceDelete(): bool
    {
        return $this->forceDelete;
    }
}
