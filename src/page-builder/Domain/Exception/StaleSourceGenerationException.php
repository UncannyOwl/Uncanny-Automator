<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

/**
 * Raised when a caller tries to persist or publish an out-of-date aggregate.
 */
final class StaleSourceGenerationException extends \RuntimeException
{
    public function __construct(
        private readonly string $scope,
        private readonly int $expectedGeneration,
        private readonly int $currentGeneration,
    ) {
        parent::__construct(sprintf(
            'The %s source changed before this write completed (expected generation %d, current generation %d).',
            $scope,
            $expectedGeneration,
            $currentGeneration,
        ));
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function expectedGeneration(): int
    {
        return $this->expectedGeneration;
    }

    public function currentGeneration(): int
    {
        return $this->currentGeneration;
    }
}
