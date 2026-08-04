<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\EditorLock;

final class EditorLockOwner
{
    public function __construct(
        private readonly int $userId,
        private readonly string $displayName,
        private readonly string $avatarUrl,
    ) {}

    public function userId(): int
    {
        return $this->userId;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function avatarUrl(): string
    {
        return $this->avatarUrl;
    }

    /** @return array{display_name: string, avatar_url: string} */
    public function safeSummary(): array
    {
        return [
            'display_name' => $this->displayName,
            'avatar_url'   => $this->avatarUrl,
        ];
    }
}
