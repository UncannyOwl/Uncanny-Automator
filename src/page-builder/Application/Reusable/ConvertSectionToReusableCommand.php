<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

final class ConvertSectionToReusableCommand
{
    public function __construct(
        private readonly int $sectionId,
        private readonly string $title = '',
        private readonly GlobalPartType $type = GlobalPartType::Section,
    ) {}

    public function sectionId(): int
    {
        return $this->sectionId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function type(): GlobalPartType
    {
        return $this->type;
    }
}
