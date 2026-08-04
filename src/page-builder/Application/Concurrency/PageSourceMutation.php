<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Concurrency;

use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\History\OperationHistoryRepositoryInterface;

/**
 * Runs one logical page-source mutation under the shared generation guard.
 *
 * Nested writes for the same page join the outer transaction so source and
 * persisted history move together and advance the generation exactly once.
 * Cross-page nesting is rejected because it would introduce an unstable page
 * lock order.
 */
final class PageSourceMutation
{
    private ?int $activePageId = null;
    private ?int $activeExpectedGeneration = null;
    private ?int $draftWritePageId = null;
    private ?\Closure $markDraftResumePolicy = null;
    private ?\Closure $assertDraftWriteAllowed = null;

    public function __construct(
        private readonly SourceGenerationStoreInterface $sourceGenerations,
        private readonly ?OperationHistoryRepositoryInterface $history = null,
    ) {}

    /**
     * @param callable(): mixed $write
     */
    public function run(int $pageId, callable $write): mixed
    {
        return $this->runExpected(
            $pageId,
            $this->generation($pageId),
            $write,
        );
    }

    public function generation(int $pageId): int
    {
        return $this->sourceGenerations->pageGeneration($pageId);
    }

    /**
     * Mark every page mutation performed by one Agent facade as an active
     * working draft inside that mutation's transaction.
     *
     * This method deliberately does not open a transaction by itself. A
     * successful no-op Agent request must not advance the page generation.
     *
     * @param callable(): mixed $operation
     * @param callable(): void $markDraftActive
     * @param (callable(): void)|null $assertWriteAllowed
     */
    public function runAsAgentWrite(
        int $pageId,
        callable $operation,
        callable $markDraftActive,
        ?callable $assertWriteAllowed = null,
    ): mixed {
        return $this->runAsDraftWrite(
            $pageId,
            $operation,
            $markDraftActive,
            $assertWriteAllowed,
            'An Agent page write',
        );
    }

    /**
     * Park a human-saved draft in the same transaction as a legacy page write.
     *
     * New canvas authoring uses the aggregate Manual change-set handler. This
     * boundary exists for explicit secondary save surfaces that still call one
     * typed page service directly, such as a native WordPress metabox.
     *
     * @param callable(): mixed $operation
     * @param callable(): void $markDraftParked
     * @param (callable(): void)|null $assertWriteAllowed
     */
    public function runAsHumanSave(
        int $pageId,
        callable $operation,
        callable $markDraftParked,
        ?callable $assertWriteAllowed = null,
    ): mixed {
        return $this->runAsDraftWrite(
            $pageId,
            $operation,
            $markDraftParked,
            $assertWriteAllowed,
            'A human page save',
        );
    }

    /**
     * @param callable(): mixed $write
     */
    public function runExpected(int $pageId, int $expectedGeneration, callable $write): mixed
    {
        return $this->commit($pageId, $expectedGeneration, $write, false);
    }

    /**
     * Undo/redo owns its existing redo topology and must not clear it merely by
     * entering the page transaction.
     *
     * @param callable(): mixed $write
     */
    public function runHistoryExpected(int $pageId, int $expectedGeneration, callable $write): mixed
    {
        return $this->commit($pageId, $expectedGeneration, $write, true);
    }

    /**
     * @param callable(): mixed $write
     */
    private function commit(
        int $pageId,
        int $expectedGeneration,
        callable $write,
        bool $preserveRedo,
    ): mixed {
        if ($pageId <= 0 || $expectedGeneration < 0) {
            throw new \InvalidArgumentException('A valid page source generation is required.');
        }

        if ($this->activePageId !== null) {
            if ($this->activePageId !== $pageId) {
                throw new \LogicException('A page source mutation cannot nest a different page.');
            }
            if ($this->activeExpectedGeneration !== $expectedGeneration) {
                throw new StaleSourceGenerationException(
                    'page',
                    $expectedGeneration,
                    $this->activeExpectedGeneration ?? -1,
                );
            }

            return $write();
        }

        $this->activePageId = $pageId;
        $this->activeExpectedGeneration = $expectedGeneration;

        try {
            return $this->sourceGenerations->commitPage(
                $pageId,
                $expectedGeneration,
                function () use ($pageId, $write, $preserveRedo): mixed {
                    if (
                        $this->draftWritePageId === $pageId
                        && $this->assertDraftWriteAllowed instanceof \Closure
                    ) {
                        ($this->assertDraftWriteAllowed)();
                    }

                    if (!$preserveRedo) {
                        $this->history?->deleteRedoable('page', $pageId);
                    }

                    $result = $write();
                    if (
                        $this->draftWritePageId === $pageId
                        && $this->markDraftResumePolicy instanceof \Closure
                    ) {
                        ($this->markDraftResumePolicy)();
                    }

                    return $result;
                },
            );
        } finally {
            $this->activeExpectedGeneration = null;
            $this->activePageId = null;
        }
    }

    public function isRunningFor(int $pageId): bool
    {
        return $this->activePageId === $pageId;
    }

    /**
     * @param callable(): mixed $operation
     * @param callable(): void $markResumePolicy
     * @param (callable(): void)|null $assertWriteAllowed
     */
    private function runAsDraftWrite(
        int $pageId,
        callable $operation,
        callable $markResumePolicy,
        ?callable $assertWriteAllowed,
        string $label,
    ): mixed {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException($label . ' requires a positive page ID.');
        }
        if ($this->draftWritePageId !== null) {
            throw new \LogicException('A draft write context cannot be nested.');
        }

        $this->draftWritePageId = $pageId;
        $this->markDraftResumePolicy = \Closure::fromCallable($markResumePolicy);
        $this->assertDraftWriteAllowed = $assertWriteAllowed !== null
            ? \Closure::fromCallable($assertWriteAllowed)
            : null;

        try {
            return $operation();
        } finally {
            $this->assertDraftWriteAllowed = null;
            $this->markDraftResumePolicy = null;
            $this->draftWritePageId = null;
        }
    }
}
