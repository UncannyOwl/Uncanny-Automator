<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class BindingTargetNotFoundException extends \RuntimeException
{
    public function __construct(
        private readonly string $targetId,
    ) {
        parent::__construct("Binding target '{$targetId}' was not found.");
    }

    public function targetId(): string
    {
        return $this->targetId;
    }
}
