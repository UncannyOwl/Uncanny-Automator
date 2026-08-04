<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\EditorLock;

use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;

final class ReleaseEditorOwnership
{
    public function __construct(private readonly EditorLockStoreInterface $store) {}

    public function execute(int $postId, int $actorUserId, string $knownToken): bool
    {
        try {
            if (!$this->store->isEnabled($postId)) {
                return true;
            }

            return $this->store->release($postId, $actorUserId, $knownToken);
        } catch (\Throwable) {
            return false;
        }
    }
}
