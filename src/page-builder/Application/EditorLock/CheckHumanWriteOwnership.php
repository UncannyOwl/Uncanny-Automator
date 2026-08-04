<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\EditorLock;

use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipState;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipStatus;

final class CheckHumanWriteOwnership
{
    public function __construct(private readonly EditorLockStoreInterface $store) {}

    public function execute(int $postId, int $actorUserId): EditorOwnershipState
    {
        try {
            if (!$this->store->isEnabled($postId)) {
                return EditorOwnershipState::available();
            }

            $state = $this->store->inspect($postId, $actorUserId);

            return $state->status() === EditorOwnershipStatus::Available
                ? $this->store->claim($postId, $actorUserId)
                : $state;
        } catch (\Throwable $exception) {
            return EditorOwnershipState::unavailable($exception->getMessage());
        }
    }
}
