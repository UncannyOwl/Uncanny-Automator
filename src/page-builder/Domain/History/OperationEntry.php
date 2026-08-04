<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\History;

final class OperationEntry
{
    /**
     * @param array<int, array<string, mixed>> $beforePayload
     * @param array<int, array<string, mixed>> $afterPayload
     */
    public function __construct(
        private readonly ?int $id,
        private readonly string $scopeType,
        private readonly int $scopeId,
        private readonly int $actorUserId,
        private readonly string $operation,
        private readonly string $label,
        private readonly array $beforePayload,
        private readonly array $afterPayload,
        private readonly ?\DateTimeImmutable $createdAt = null,
        private readonly ?\DateTimeImmutable $undoneAt = null,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $beforePayload
     * @param array<int, array<string, mixed>> $afterPayload
     */
    public static function record(
        string $scopeType,
        int $scopeId,
        int $actorUserId,
        string $operation,
        string $label,
        array $beforePayload,
        array $afterPayload,
    ): self {
        return new self(
            id: null,
            scopeType: $scopeType,
            scopeId: $scopeId,
            actorUserId: $actorUserId,
            operation: $operation,
            label: $label,
            beforePayload: $beforePayload,
            afterPayload: $afterPayload,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $beforePayload
     * @param array<int, array<string, mixed>> $afterPayload
     */
    public static function hydrate(
        int $id,
        string $scopeType,
        int $scopeId,
        int $actorUserId,
        string $operation,
        string $label,
        array $beforePayload,
        array $afterPayload,
        string $createdAt,
        ?string $undoneAt,
    ): self {
        return new self(
            id: $id,
            scopeType: $scopeType,
            scopeId: $scopeId,
            actorUserId: $actorUserId,
            operation: $operation,
            label: $label,
            beforePayload: $beforePayload,
            afterPayload: $afterPayload,
            createdAt: new \DateTimeImmutable($createdAt),
            undoneAt: $undoneAt !== null && $undoneAt !== '' ? new \DateTimeImmutable($undoneAt) : null,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function scopeType(): string
    {
        return $this->scopeType;
    }

    public function scopeId(): int
    {
        return $this->scopeId;
    }

    public function actorUserId(): int
    {
        return $this->actorUserId;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function label(): string
    {
        return $this->label;
    }

    /** @return array<int, array<string, mixed>> */
    public function beforePayload(): array
    {
        return $this->beforePayload;
    }

    /** @return array<int, array<string, mixed>> */
    public function afterPayload(): array
    {
        return $this->afterPayload;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function undoneAt(): ?\DateTimeImmutable
    {
        return $this->undoneAt;
    }
}
