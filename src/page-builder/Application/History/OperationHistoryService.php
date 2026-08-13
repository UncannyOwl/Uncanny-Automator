<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\History;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\History\OperationEntry;
use UncannyPageBuilder\Domain\History\OperationHistoryRepositoryInterface;

/**
 * Owns one persisted history timeline per Page Builder page.
 *
 * Actors are audit data only. Traversal, redo deletion, and retention are
 * always scoped by page, and source plus cursor changes share one generation
 * transaction.
 */
final class OperationHistoryService
{
    public const SCOPE_PAGE = 'page';
    private const MAX_ACTIVE_ENTRIES = 30;

    public function __construct(
        private readonly OperationHistoryRepositoryInterface $repository,
        private readonly PageSourceMutation $pageSource,
    ) {}

    public function canUndo(string $scopeType, int $scopeId): bool
    {
        return $this->repository->latestUndoable($scopeType, $scopeId) instanceof OperationEntry;
    }

    public function canRedo(string $scopeType, int $scopeId): bool
    {
        return $this->repository->latestRedoable($scopeType, $scopeId) instanceof OperationEntry;
    }

    public function nextUndo(string $scopeType, int $scopeId): ?OperationEntry
    {
        return $this->repository->latestUndoable($scopeType, $scopeId);
    }

    public function nextRedo(string $scopeType, int $scopeId): ?OperationEntry
    {
        return $this->repository->latestRedoable($scopeType, $scopeId);
    }

    /**
     * Compatibility preview for cached clients that predate one-step durable history.
     *
     * The production Manual editor commits one cursor movement per Undo or Redo.
     */
    public function previewPageTransition(
        int $pageId,
        string $direction,
        int $expectedGeneration,
    ): ?HistoryTransitionPreview {
        if ($pageId <= 0 || !in_array($direction, ['undo', 'redo'], true)) {
            throw new \InvalidArgumentException('A valid page history direction is required.');
        }
        if ($expectedGeneration < 0) {
            throw new \InvalidArgumentException('A valid page generation is required.');
        }

        $before = $this->pageSource->generation($pageId);
        if ($before !== $expectedGeneration) {
            throw new StaleSourceGenerationException('page', $expectedGeneration, $before);
        }

        $entry = $direction === 'undo'
            ? $this->repository->latestUndoable(self::SCOPE_PAGE, $pageId)
            : $this->repository->latestRedoable(self::SCOPE_PAGE, $pageId);
        $after = $this->pageSource->generation($pageId);
        if ($after !== $expectedGeneration) {
            throw new StaleSourceGenerationException('page', $expectedGeneration, $after);
        }
        if (!$entry instanceof OperationEntry) {
            return null;
        }

        $operationId = $entry->id();
        if ($operationId === null) {
            throw new \LogicException('History preview requires a saved operation.');
        }
        $preview = HistoryOperationRestorer::previewTarget($entry, $direction === 'undo');
        $baseline = HistoryOperationRestorer::previewTarget($entry, $direction !== 'undo');

        if ($preview['kind'] !== $baseline['kind']) {
            throw new \LogicException('History preview states must have the same kind.');
        }

        return new HistoryTransitionPreview(
            operationId: $operationId,
            direction: $direction,
            operation: $entry->operation(),
            label: $entry->label(),
            baseGeneration: $expectedGeneration,
            kind: $preview['kind'],
            target: $preview['target'],
            baseline: $baseline['target'],
        );
    }

    /**
     * Apply only the exact operation previously previewed by this browser.
     *
     * @param callable(OperationEntry): mixed $restore
     * @return array{entry: OperationEntry, result: mixed}
     */
    public function applyPreviewedPageTransition(
        int $pageId,
        string $direction,
        int $operationId,
        int $expectedGeneration,
        callable $restore,
    ): array {
        if ($operationId <= 0 || !in_array($direction, ['undo', 'redo'], true)) {
            throw new \InvalidArgumentException('A valid saved history transition is required.');
        }

        return $this->pageSource->runHistoryExpected(
            $pageId,
            $expectedGeneration,
            function () use ($pageId, $direction, $operationId, $restore): array {
                $entry = $direction === 'undo'
                    ? $this->repository->latestUndoable(self::SCOPE_PAGE, $pageId)
                    : $this->repository->latestRedoable(self::SCOPE_PAGE, $pageId);
                if (!$entry instanceof OperationEntry || $entry->id() !== $operationId) {
                    throw new \UncannyPageBuilder\Domain\Exception\HistorySnapshotConflictException();
                }

                $result = $restore($entry);
                if ($direction === 'undo') {
                    $this->repository->markUndone($operationId);
                } else {
                    $this->repository->markRedone($operationId);
                    $this->repository->pruneActive(
                        self::SCOPE_PAGE,
                        $pageId,
                        self::MAX_ACTIVE_ENTRIES,
                    );
                }

                return ['entry' => $entry, 'result' => $result];
            },
        );
    }

    /**
     * A new Manual edit after a previewed Undo/Redo starts a new branch.
     * Call only from the surrounding page-source transaction.
     */
    public function discardRedoablePageBranch(int $pageId): void
    {
        $this->repository->deleteRedoable(self::SCOPE_PAGE, $pageId);
    }

    /**
     * Discard history for a working source that is being replaced wholesale.
     * The caller must already own the surrounding page-source transaction.
     */
    public function discardPageHistory(int $pageId): void
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('A valid page is required to clear history.');
        }

        $this->repository->deleteAll(self::SCOPE_PAGE, $pageId);
    }

    /**
     * Commit one working-source mutation and its audit entry atomically.
     *
     * @param array<int, array<string, mixed>> $beforePayload
     * @param array<int, array<string, mixed>> $afterPayload
     * @param callable(): mixed $write
     * @param null|callable(): array<int, array<string, mixed>> $persistedAfterPayload
     */
    public function recordPageMutation(
        int $pageId,
        int $expectedGeneration,
        int $actorUserId,
        string $operation,
        string $label,
        array $beforePayload,
        array $afterPayload,
        callable $write,
        ?callable $persistedAfterPayload = null,
    ): mixed {
        if (!HistoryOperationRestorer::supports($operation)) {
            throw new \InvalidArgumentException(sprintf(
                'History operation "%s" has no complete working-source restore path.',
                $operation,
            ));
        }
        if ($beforePayload === $afterPayload) {
            return null;
        }

        return $this->pageSource->runExpected(
            $pageId,
            $expectedGeneration,
            function () use (
                $pageId,
                $actorUserId,
                $operation,
                $label,
                $beforePayload,
                $afterPayload,
                $write,
                $persistedAfterPayload,
            ): mixed {
                $result = $write();
                $canonicalAfterPayload = $persistedAfterPayload !== null
                    ? $persistedAfterPayload()
                    : $afterPayload;
                if ($beforePayload === $canonicalAfterPayload) {
                    throw new \LogicException('A recorded page mutation must change its persisted history payload.');
                }

                $this->repository->insert(OperationEntry::record(
                    scopeType: self::SCOPE_PAGE,
                    scopeId: $pageId,
                    actorUserId: max(0, $actorUserId),
                    operation: $operation,
                    label: $label,
                    beforePayload: $beforePayload,
                    afterPayload: $canonicalAfterPayload,
                ));
                $this->repository->pruneActive(
                    self::SCOPE_PAGE,
                    $pageId,
                    self::MAX_ACTIVE_ENTRIES,
                );

                return $result;
            },
        );
    }

    /**
     * @param callable(OperationEntry): mixed $restore
     * @return array{entry: OperationEntry, result: mixed}|null
     */
    public function undoPage(int $pageId, callable $restore): ?array
    {
        $candidate = $this->repository->latestUndoable(self::SCOPE_PAGE, $pageId);
        if (!$candidate instanceof OperationEntry) {
            return null;
        }

        $generation = $this->pageSource->generation($pageId);

        return $this->pageSource->runHistoryExpected(
            $pageId,
            $generation,
            function () use ($pageId, $candidate, $restore): array {
                $entry = $this->repository->latestUndoable(self::SCOPE_PAGE, $pageId);
                $this->assertSameCandidate($candidate, $entry);

                $result = $restore($entry);
                $this->repository->markUndone($this->savedId($entry));

                return ['entry' => $entry, 'result' => $result];
            },
        );
    }

    /**
     * @param callable(OperationEntry): mixed $restore
     * @return array{entry: OperationEntry, result: mixed}|null
     */
    public function redoPage(int $pageId, callable $restore): ?array
    {
        $candidate = $this->repository->latestRedoable(self::SCOPE_PAGE, $pageId);
        if (!$candidate instanceof OperationEntry) {
            return null;
        }

        $generation = $this->pageSource->generation($pageId);

        return $this->pageSource->runHistoryExpected(
            $pageId,
            $generation,
            function () use ($pageId, $candidate, $restore): array {
                $entry = $this->repository->latestRedoable(self::SCOPE_PAGE, $pageId);
                $this->assertSameCandidate($candidate, $entry);

                $result = $restore($entry);
                $this->repository->markRedone($this->savedId($entry));
                $this->repository->pruneActive(
                    self::SCOPE_PAGE,
                    $pageId,
                    self::MAX_ACTIVE_ENTRIES,
                );

                return ['entry' => $entry, 'result' => $result];
            },
        );
    }

    private function assertSameCandidate(OperationEntry $expected, ?OperationEntry $current): void
    {
        if (!$current instanceof OperationEntry || $current->id() !== $expected->id()) {
            throw new \UncannyPageBuilder\Domain\Exception\HistorySnapshotConflictException();
        }
    }

    private function savedId(OperationEntry $entry): int
    {
        $id = $entry->id();
        if ($id === null) {
            throw new \InvalidArgumentException('Cannot transition an unsaved history operation.');
        }

        return $id;
    }
}
