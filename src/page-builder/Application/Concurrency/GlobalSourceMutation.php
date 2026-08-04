<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Concurrency;

use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;

/**
 * Runs one logical global-source mutation under the shared generation guard.
 *
 * Nested global writes join the outer mutation so a reusable metadata change
 * and its dependent default/reference cleanup remain one atomic boundary and
 * advance the generation exactly once.
 */
final class GlobalSourceMutation
{
    private bool $running = false;
    private ?int $activeExpectedGeneration = null;

    public function __construct(
        private readonly SourceGenerationStoreInterface $sourceGenerations,
    ) {}

    /**
     * @param callable(): mixed $write
     */
    public function run(callable $write): mixed
    {
        if ($this->running) {
            return $write();
        }

        $generation = $this->sourceGenerations->globalGeneration();

        return $this->runExpected($generation, $write);
    }

    /**
     * @param callable(): mixed $write
     */
    public function runExpected(int $expectedGeneration, callable $write): mixed
    {
        if ($this->running) {
            if ($this->activeExpectedGeneration !== $expectedGeneration) {
                throw new StaleSourceGenerationException(
                    'global',
                    $expectedGeneration,
                    $this->activeExpectedGeneration ?? -1,
                );
            }

            return $write();
        }

        $this->running = true;
        $this->activeExpectedGeneration = $expectedGeneration;

        try {
            return $this->sourceGenerations->commitGlobal($expectedGeneration, $write);
        } finally {
            $this->activeExpectedGeneration = null;
            $this->running = false;
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}
