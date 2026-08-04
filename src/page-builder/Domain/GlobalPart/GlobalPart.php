<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

final class GlobalPart
{
    public function __construct(
        private readonly string $title,
        private readonly GlobalPartType $type,
    ) {}

    public function title(): string          { return $this->title; }
    public function type(): GlobalPartType   { return $this->type; }
}
