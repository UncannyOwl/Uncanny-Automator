<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\EditorLock;

use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipState;

final class RefreshEditorOwnership
{
    public function __construct(private readonly EditorLockStoreInterface $store) {}

    public function execute(
        int $postId,
        int $actorUserId,
        string $knownToken,
    ): EditorOwnershipState {
        try {
            if (!$this->store->isEnabled($postId)) {
                return EditorOwnershipState::available();
            }

            return $this->store->refresh($postId, $actorUserId, $knownToken);
        } catch (\Throwable $exception) {
            return EditorOwnershipState::unavailable($exception->getMessage());
        }
    }
}
