<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

/**
 * The global part post can remain when creation and its cleanup both fail.
 */
final class GlobalPartCreationUncertainException extends \RuntimeException
{
    public function __construct(
        private readonly int $globalPartId,
        private readonly \Throwable $creationFailure,
        \Throwable $cleanupFailure,
    ) {
        parent::__construct('Global part creation result is uncertain.', 0, $cleanupFailure);
    }

    public function globalPartId(): int
    {
        return $this->globalPartId;
    }

    public function creationFailure(): \Throwable
    {
        return $this->creationFailure;
    }
}
