<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Domain\Shell\ShellMode;

final class EditCanvasCommand
{
    public function __construct(
        private readonly int $canvasId,
        private readonly ?string $title = null,
        private readonly ?ShellMode $shellMode = null,
    ) {}

    public function canvasId(): int
    {
        return $this->canvasId;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function shellMode(): ?ShellMode
    {
        return $this->shellMode;
    }
}
