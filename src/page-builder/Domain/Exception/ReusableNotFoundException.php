<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class ReusableNotFoundException extends \RuntimeException
{
    public function __construct(private readonly int $reusableId)
    {
        parent::__construct('Reusable not found.');
    }

    public function reusableId(): int
    {
        return $this->reusableId;
    }
}
