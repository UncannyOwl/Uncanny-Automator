<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\EditorLock;

/**
 * Persistence boundary for WordPress-compatible editor leases.
 *
 * Every state returned after a write must come from a fresh read. Callers must
 * never infer ownership from a metadata write result.
 */
interface EditorLockStoreInterface
{
    public function isEnabled(int $postId): bool;

    public function inspect(int $postId, int $actorUserId): EditorOwnershipState;

    public function claim(int $postId, int $actorUserId): EditorOwnershipState;

    public function takeOver(int $postId, int $actorUserId): EditorOwnershipState;

    public function refresh(int $postId, int $actorUserId, string $knownToken): EditorOwnershipState;

    public function release(int $postId, int $actorUserId, string $knownToken): bool;
}
