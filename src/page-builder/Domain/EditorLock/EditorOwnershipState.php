<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\EditorLock;

final class EditorOwnershipState
{
    private function __construct(
        private readonly EditorOwnershipStatus $status,
        private readonly ?EditorLockToken $token = null,
        private readonly ?EditorLockOwner $owner = null,
        private readonly bool $takeoverAllowed = false,
        private readonly string $reason = '',
    ) {}

    public static function available(): self
    {
        return new self(EditorOwnershipStatus::Available);
    }

    public static function owned(EditorLockToken $token): self
    {
        return new self(EditorOwnershipStatus::Owned, token: $token);
    }

    public static function blocked(
        EditorLockOwner $owner,
        bool $takeoverAllowed,
        ?EditorLockToken $token = null,
    ): self {
        return new self(
            EditorOwnershipStatus::Blocked,
            token: $token,
            owner: $owner,
            takeoverAllowed: $takeoverAllowed,
        );
    }

    public static function unavailable(string $reason = ''): self
    {
        return new self(EditorOwnershipStatus::Unavailable, reason: $reason);
    }

    public function status(): EditorOwnershipStatus
    {
        return $this->status;
    }

    public function token(): ?EditorLockToken
    {
        return $this->token;
    }

    public function owner(): ?EditorLockOwner
    {
        return $this->owner;
    }

    public function takeoverAllowed(): bool
    {
        return $this->takeoverAllowed;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function isOwned(): bool
    {
        return $this->status === EditorOwnershipStatus::Owned;
    }
}
