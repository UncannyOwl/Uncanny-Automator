<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Domain\Canvas\CanvasKind;

final class ListCanvasQuery
{
    public function __construct(private readonly ?CanvasKind $kind = null) {}

    public function kind(): ?CanvasKind
    {
        return $this->kind;
    }
}
