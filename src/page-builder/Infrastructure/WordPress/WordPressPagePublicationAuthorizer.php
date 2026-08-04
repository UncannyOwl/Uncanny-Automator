<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Publishing\PagePublicationAuthorizerInterface;
use UncannyPageBuilder\Domain\Canvas\PageOwnershipRepositoryInterface;

/**
 * Confirms that publication belongs to the active human WordPress session.
 */
final class WordPressPagePublicationAuthorizer implements PagePublicationAuthorizerInterface
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly PageOwnershipRepositoryInterface $ownership,
    ) {}

    public function canPublish(int $pageId, int $userId): bool
    {
        return $pageId > 0
            && $userId > 0
            && $this->currentUserId() === $userId
            && $this->ownership->isOwned($pageId)
            && $this->permissions->canEditPage($pageId)
            && $this->permissions->canPublishPost($pageId);
    }

    private function currentUserId(): int
    {
        $namespaced = __NAMESPACE__ . '\\get_current_user_id';
        if (function_exists($namespaced)) {
            return (int) $namespaced();
        }

        return function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    }
}
