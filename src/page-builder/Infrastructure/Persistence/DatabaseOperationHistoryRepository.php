<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\History\OperationEntry;
use UncannyPageBuilder\Domain\History\OperationHistoryRepositoryInterface;

final class DatabaseOperationHistoryRepository implements OperationHistoryRepositoryInterface
{
    public function __construct(
        private readonly ?\Closure $clock = null,
    ) {}

    private function table(): string
    {
        return SchemaManager::operationsTableName();
    }

    public function insert(OperationEntry $entry): OperationEntry
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table(),
            [
                'scope_type'     => $entry->scopeType(),
                'scope_id'       => $entry->scopeId(),
                'actor_user_id'  => $entry->actorUserId(),
                'operation'      => $entry->operation(),
                'label'          => $entry->label(),
                'before_payload' => $this->encodePayload($entry->beforePayload()),
                'after_payload'  => $this->encodePayload($entry->afterPayload()),
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s'],
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to record operation history.');
        }

        $saved = $this->findById((int) $wpdb->insert_id);
        if (!$saved instanceof OperationEntry) {
            throw new \RuntimeException('Failed to reload operation history entry.');
        }

        return $saved;
    }

    public function latestUndoable(string $scopeType, int $scopeId): ?OperationEntry
    {
        global $wpdb;
        $table = $this->table();

        $wpdb->last_error = '';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE scope_type = %s AND scope_id = %d AND undone_at IS NULL
             ORDER BY id DESC
             LIMIT 1",
            $scopeType,
            $scopeId,
        ));
        $this->assertReadSucceeded('read undoable operation history');

        return $row ? $this->hydrate($row) : null;
    }

    public function latestRedoable(string $scopeType, int $scopeId): ?OperationEntry
    {
        global $wpdb;
        $table = $this->table();

        $wpdb->last_error = '';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE scope_type = %s AND scope_id = %d AND undone_at IS NOT NULL
             ORDER BY id ASC
             LIMIT 1",
            $scopeType,
            $scopeId,
        ));
        $this->assertReadSucceeded('read redoable operation history');

        return $row ? $this->hydrate($row) : null;
    }

    public function markUndone(int $operationId): void
    {
        global $wpdb;

        $table = $this->table();
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET undone_at = %s WHERE id = %d AND undone_at IS NULL",
            $this->now(),
            $operationId,
        ));

        if ($result !== 1) {
            throw new \RuntimeException('Failed to mark operation as undone.');
        }
    }

    public function markRedone(int $operationId): void
    {
        global $wpdb;
        $table = $this->table();

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET undone_at = NULL WHERE id = %d AND undone_at IS NOT NULL",
            $operationId,
        ));

        if ($result !== 1) {
            throw new \RuntimeException('Failed to mark operation as redone.');
        }
    }

    public function deleteRedoable(string $scopeType, int $scopeId): void
    {
        global $wpdb;
        $table = $this->table();

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table}
             WHERE scope_type = %s AND scope_id = %d AND undone_at IS NOT NULL",
            $scopeType,
            $scopeId,
        ));

        if ($result === false) {
            throw new \RuntimeException('Failed to clear redo operation history.');
        }
    }

    public function deleteAll(string $scopeType, int $scopeId): void
    {
        global $wpdb;
        $table = $this->table();

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table}
             WHERE scope_type = %s AND scope_id = %d",
            $scopeType,
            $scopeId,
        ));

        if ($result === false) {
            throw new \RuntimeException('Failed to clear operation history.');
        }
    }

    public function pruneActive(string $scopeType, int $scopeId, int $limit): void
    {
        global $wpdb;
        $table = $this->table();

        $wpdb->last_error = '';
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE scope_type = %s AND scope_id = %d AND undone_at IS NULL
             ORDER BY id DESC",
            $scopeType,
            $scopeId,
        ));
        $this->assertReadSucceeded('read operation history for pruning');

        if (!is_array($ids)) {
            throw new \RuntimeException('Failed to read operation history for pruning.');
        }
        if (count($ids) <= $limit) {
            return;
        }

        $deleteIds = array_map('intval', array_slice($ids, $limit));
        $placeholders = implode(',', array_fill(0, count($deleteIds), '%d'));
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE id IN ({$placeholders})",
            ...$deleteIds,
        ));

        if ($result === false) {
            throw new \RuntimeException('Failed to prune operation history.');
        }
    }

    private function findById(int $id): ?OperationEntry
    {
        global $wpdb;
        $table = $this->table();

        $wpdb->last_error = '';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        $this->assertReadSucceeded('reload operation history');

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(object $row): OperationEntry
    {
        return OperationEntry::hydrate(
            id: (int) $row->id,
            scopeType: (string) $row->scope_type,
            scopeId: (int) $row->scope_id,
            actorUserId: (int) $row->actor_user_id,
            operation: (string) $row->operation,
            label: (string) $row->label,
            beforePayload: $this->decodePayload((string) $row->before_payload),
            afterPayload: $this->decodePayload((string) $row->after_payload),
            createdAt: (string) $row->created_at,
            undoneAt: isset($row->undone_at) ? (string) $row->undone_at : null,
        );
    }

    /** @param array<int, array<string, mixed>> $payload */
    private function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /** @return array<int, array<string, mixed>> */
    private function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }

    private function now(): string
    {
        if ($this->clock instanceof \Closure) {
            return (string) ($this->clock)();
        }

        return \function_exists('current_time')
            ? (string) \current_time('mysql')
            : gmdate('Y-m-d H:i:s');
    }

    private function assertReadSucceeded(string $operation): void
    {
        global $wpdb;

        if ((string) ($wpdb->last_error ?? '') !== '') {
            throw new \RuntimeException(sprintf('Failed to %s.', $operation));
        }
    }
}
