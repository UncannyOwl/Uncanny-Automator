<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Domain\Canvas\Canvas;

final class DeleteCanvasResult
{
    public function __construct(
        private readonly Canvas $canvas,
        private readonly bool $forceDeleted,
    ) {}

    public function canvas(): Canvas
    {
        return $this->canvas;
    }

    public function forceDeleted(): bool
    {
        return $this->forceDeleted;
    }
}
