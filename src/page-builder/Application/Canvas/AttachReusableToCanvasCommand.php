<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

final class AttachReusableToCanvasCommand
{
    public function __construct(
        private readonly int $canvasId,
        private readonly int $reusableId,
    ) {}

    public function canvasId(): int
    {
        return $this->canvasId;
    }

    public function reusableId(): int
    {
        return $this->reusableId;
    }
}
