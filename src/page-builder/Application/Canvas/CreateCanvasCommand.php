<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Domain\Canvas\CanvasKind;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

final class CreateCanvasCommand
{
    public function __construct(
        private readonly CanvasKind $kind,
        private readonly string $title = '',
        private readonly GlobalPartType $globalPartType = GlobalPartType::Section,
    ) {}

    public function kind(): CanvasKind
    {
        return $this->kind;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function globalPartType(): GlobalPartType
    {
        return $this->globalPartType;
    }
}
