<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class CanvasNotFoundException extends \RuntimeException
{
    public function __construct(private readonly int $canvasId)
    {
        parent::__construct('Canvas not found.');
    }

    public function canvasId(): int
    {
        return $this->canvasId;
    }
}
