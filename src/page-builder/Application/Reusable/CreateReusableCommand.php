<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

final class CreateReusableCommand
{
    public function __construct(
        private readonly string $title = '',
        private readonly GlobalPartType $type = GlobalPartType::Section,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function type(): GlobalPartType
    {
        return $this->type;
    }
}
