<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

final class ListReusableQuery
{
    public function __construct(private readonly ?GlobalPartType $type = null) {}

    public function type(): ?GlobalPartType
    {
        return $this->type;
    }
}
