<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

final class UpdateReusableCommand
{
    public function __construct(
        private readonly int $reusableId,
        private readonly ?string $title = null,
        private readonly ?GlobalPartType $type = null,
    ) {}

    public function reusableId(): int
    {
        return $this->reusableId;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function type(): ?GlobalPartType
    {
        return $this->type;
    }
}
