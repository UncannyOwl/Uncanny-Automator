<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Canvas;

/**
 * The WordPress post can remain when Canvas creation and cleanup both fail.
 */
final class CanvasCreationUncertainException extends \RuntimeException
{
    public function __construct(
        private readonly int $canvasId,
        private readonly string $kind,
        private readonly \Throwable $creationFailure,
        \Throwable $cleanupFailure,
    ) {
        parent::__construct('Canvas creation result is uncertain.', 0, $cleanupFailure);
    }

    public function canvasId(): int
    {
        return $this->canvasId;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function creationFailure(): \Throwable
    {
        return $this->creationFailure;
    }
}
