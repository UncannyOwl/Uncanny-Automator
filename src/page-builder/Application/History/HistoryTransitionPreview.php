<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\History;

/**
 * Read-only projection of one saved history transition.
 *
 * The target is paint data only. A later Manual save sends the operation
 * identity back to the server, which reloads the trusted history entry and
 * applies it under the page generation lock.
 */
final class HistoryTransitionPreview
{
    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $baseline
     */
    public function __construct(
        private readonly int $operationId,
        private readonly string $direction,
        private readonly string $operation,
        private readonly string $label,
        private readonly int $baseGeneration,
        private readonly string $kind,
        private readonly array $target,
        private readonly array $baseline,
    ) {
        if ($operationId <= 0) {
            throw new \InvalidArgumentException('History preview requires a saved operation.');
        }
        if (!in_array($direction, ['undo', 'redo'], true)) {
            throw new \InvalidArgumentException('History preview direction is invalid.');
        }
        if ($operation === '' || $label === '') {
            throw new \InvalidArgumentException('History preview identity is incomplete.');
        }
        if ($baseGeneration < 0) {
            throw new \InvalidArgumentException('History preview generation must not be negative.');
        }
        if (!in_array($kind, ['sections', 'page_details'], true)) {
            throw new \InvalidArgumentException('History preview kind is unsupported.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'direction' => $this->direction,
            'operation' => $this->operation,
            'label' => $this->label,
            'base_generation' => $this->baseGeneration,
            'kind' => $this->kind,
            'target' => $this->target,
        ];
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function operationId(): int
    {
        return $this->operationId;
    }

    /** @return array<string, mixed> */
    public function target(): array
    {
        return $this->target;
    }

    /** @return array<string, mixed> */
    public function baseline(): array
    {
        return $this->baseline;
    }
}
