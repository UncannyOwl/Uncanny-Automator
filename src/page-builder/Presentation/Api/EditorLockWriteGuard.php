<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\EditorLock\CheckHumanWriteOwnership;
use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipStatus;

/**
 * Cookie-authenticated write gate for Page Builder editor mutations.
 *
 * Bearer calls intentionally stay on their existing Agent authorization and
 * source-generation boundaries.
 */
final class EditorLockWriteGuard
{
    public function __construct(
        private readonly CheckHumanWriteOwnership $checkOwnership,
        private readonly EditorLockStoreInterface $store,
        private readonly PermissionChecker $permissions,
    ) {}

    public function check(
        \WP_REST_Request $request,
        int $postId,
        string $scope,
    ): ?\WP_Error {
        if ($this->permissions->isBearerRequest($request)) {
            return null;
        }

        try {
            if (!$this->store->isEnabled($postId)) {
                return null;
            }
        } catch (\Throwable) {
            $this->logRejectedWrite($postId, $scope, 'feature_check_unavailable');

            return $this->unavailableError();
        }

        $state = $this->checkOwnership->execute($postId, (int) get_current_user_id());
        if ($state->status() === EditorOwnershipStatus::Owned) {
            return null;
        }

        if ($state->status() === EditorOwnershipStatus::Blocked && $state->owner() !== null) {
            $this->logRejectedWrite($postId, $scope, 'owned_by_another_user');

            return new \WP_Error(
                'editor_locked',
                _x('This item is being edited by someone else.', 'Page Builder', 'uncanny-automator'),
                [
                    'status' => 409,
                    'owner'  => $state->owner()->safeSummary(),
                ],
            );
        }

        $this->logRejectedWrite($postId, $scope, 'ownership_unavailable');

        return $this->unavailableError();
    }

    private function logRejectedWrite(int $postId, string $scope, string $reason): void
    {
        error_log(sprintf(
            '[Uncanny Page Builder] Editor write rejected target=%d actor=%d scope=%s reason=%s',
            $postId,
            (int) get_current_user_id(),
            $scope,
            $reason,
        ));
    }

    private function unavailableError(): \WP_Error
    {
        return new \WP_Error(
            'editor_lock_unavailable',
            _x('Editing ownership could not be verified. Try saving again.', 'Page Builder', 'uncanny-automator'),
            [
                'status'    => 503,
                'retryable' => true,
            ],
        );
    }
}
