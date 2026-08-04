<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\EditorLock;

final class EditorLockToken
{
    private function __construct(
        private readonly string $raw,
        private readonly int $timestamp,
        private readonly int $userId,
    ) {}

    public static function parse(string $raw): ?self
    {
        if (preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/', $raw, $matches) !== 1) {
            return null;
        }

        $timestamp = (int) $matches[1];
        $userId = (int) $matches[2];

        if (
            $timestamp <= 0
            || $userId <= 0
            || (string) $timestamp !== $matches[1]
            || (string) $userId !== $matches[2]
        ) {
            return null;
        }

        return new self($raw, $timestamp, $userId);
    }

    public static function create(int $timestamp, int $userId): self
    {
        if ($timestamp <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('Editor lock tokens require a positive timestamp and user ID.');
        }

        return new self($timestamp . ':' . $userId, $timestamp, $userId);
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function timestamp(): int
    {
        return $this->timestamp;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function isExpiredAt(int $serverTimestamp, int $windowSeconds): bool
    {
        return $this->timestamp <= ($serverTimestamp - max(0, $windowSeconds));
    }
}
