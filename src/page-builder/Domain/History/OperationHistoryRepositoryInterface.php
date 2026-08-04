<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\History;

interface OperationHistoryRepositoryInterface
{
    public function insert(OperationEntry $entry): OperationEntry;

    public function latestUndoable(string $scopeType, int $scopeId): ?OperationEntry;

    public function latestRedoable(string $scopeType, int $scopeId): ?OperationEntry;

    public function markUndone(int $operationId): void;

    public function markRedone(int $operationId): void;

    public function deleteRedoable(string $scopeType, int $scopeId): void;

    public function deleteAll(string $scopeType, int $scopeId): void;

    public function pruneActive(string $scopeType, int $scopeId, int $limit): void;
}
