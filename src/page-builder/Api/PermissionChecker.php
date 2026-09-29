<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Infrastructure\Automator\BearerAuthenticator;

final class PermissionChecker
{
    public function __construct(
        private readonly BearerAuthenticator $authenticator,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function canManage(\WP_REST_Request $request): bool
    {
        return $this->failClosed('manage', function () use ($request): bool {
            $this->authenticator->authenticate($request);
            return $this->canAuthorPageBuilderContent();
        });
    }

    public function canEdit(\WP_REST_Request $request): bool
    {
        return $this->failClosed('edit', function () use ($request): bool {
            $this->authenticator->authenticate($request);
            return $this->canAuthorPageBuilderContent();
        });
    }

    /**
     * Object-aware edit check for page/global-part post objects.
     */
    public function canEditPost(int $postId): bool
    {
        return $this->failClosed('edit_post', fn(): bool => $postId > 0
            && $this->canAuthorPageBuilderContent()
            && current_user_can('edit_post', $postId));
    }

    /**
     * Object-aware destructive check for page/global-part post objects.
     */
    public function canDeletePost(int $postId): bool
    {
        return $this->failClosed('delete_post', fn(): bool => $postId > 0
            && $this->canAuthorPageBuilderContent()
            && current_user_can('delete_post', $postId));
    }

    /**
     * Page Builder currently uses one authoring permission for edit/manage.
     */
    public function canManagePost(int $postId): bool
    {
        return $this->canEditPost($postId);
    }

    /**
     * Normal page-authoring access requires both object permission and current
     * Page Builder eligibility. Recovery and global-part paths intentionally
     * continue to use canEditPost().
     */
    public function canEditPage(int $postId): bool
    {
        return $this->failClosed('edit_page', function () use ($postId): bool {
            $postType = $this->postType($postId);
            if ($postType === null) {
                return !$this->hasPostTypeApi() && $this->canEditPost($postId);
            }

            return $this->supportsPostType->isSupported($postType)
                && $this->canEditPost($postId);
        });
    }

    public function canManagePage(int $postId): bool
    {
        return $this->canEditPage($postId);
    }

    public function canPublishPost(int $postId): bool
    {
        return $this->failClosed('publish_post', function () use ($postId): bool {
            $postType = $this->postType($postId);
            if ($postType === null) {
                /*
                 * WordPress always exposes post-type APIs in production. The
                 * fallback preserves isolated permission adapters that intentionally
                 * provide only capability functions.
                 */
                return !$this->hasPostTypeApi()
                    && $postId > 0
                    && current_user_can('publish_pages');
            }

            $postTypeObject = $this->postTypeObject($postType);
            $capability = is_object($postTypeObject)
                ? ($postTypeObject->cap->publish_posts ?? null)
                : null;

            return is_string($capability)
                && $capability !== ''
                && current_user_can($capability);
        });
    }

    public function canUploadFiles(): bool
    {
        return $this->failClosed('upload_files', static fn(): bool => current_user_can('upload_files'));
    }

    public function canCapability(string $capability): bool
    {
        return $this->failClosed('capability', static fn(): bool => current_user_can($capability));
    }

    /**
     * @param int[] $treeItemIds
     */
    public function canManageNavigation(string $operation, int $itemId = 0, array $treeItemIds = []): bool
    {
        return $this->failClosed('navigation_' . $operation, function () use ($operation, $itemId, $treeItemIds): bool {
            $postType = $this->postTypeObject('nav_menu_item');
            $taxonomy = $this->taxonomyObject('nav_menu');
            if (!is_object($postType) || !is_object($taxonomy)) {
                return false;
            }

            $postCapabilities = is_object($postType->cap ?? null) ? $postType->cap : null;
            $taxonomyCapabilities = is_object($taxonomy->cap ?? null) ? $taxonomy->cap : null;
            if ($postCapabilities === null || $taxonomyCapabilities === null) {
                return false;
            }

            $assignTerms = $this->mappedCapability($taxonomyCapabilities, 'assign_terms');

            return match ($operation) {
                'create_menu' => $this->canMappedCapability(
                    $this->mappedCapability($taxonomyCapabilities, 'manage_terms'),
                ),
                'add_item' => $this->canMappedCapability(
                    $this->mappedCapability($postCapabilities, 'create_posts')
                        ?? $this->mappedCapability($postCapabilities, 'edit_posts'),
                ) && $this->canMappedCapability($assignTerms),
                'update_item' => $itemId > 0
                    && $this->canMappedCapability(
                        $this->mappedCapability($postCapabilities, 'edit_post'),
                        $itemId,
                    )
                    && $this->canMappedCapability($assignTerms),
                'move_item' => $itemId > 0
                    && in_array($itemId, $treeItemIds, true)
                    && $this->canReplaceNavigationTree(
                        $postCapabilities,
                        $assignTerms,
                        $treeItemIds,
                    ),
                'delete_item' => $itemId > 0
                    && $this->canMappedCapability(
                        $this->mappedCapability($postCapabilities, 'delete_post'),
                        $itemId,
                    ),
                'replace_tree' => $this->canReplaceNavigationTree(
                    $postCapabilities,
                    $assignTerms,
                    $treeItemIds,
                ),
                'assign_location' => $this->canMappedCapability('edit_theme_options'),
                default => false,
            };
        });
    }

    public function isBearerRequest(\WP_REST_Request $request): bool
    {
        return $this->failClosed(
            'bearer_request',
            fn(): bool => $this->authenticator->hasBearerCredentials($request),
        );
    }

    /**
     * WordPress invokes permission callbacks without an exception boundary.
     * Any host or extension failure must deny access instead of causing a
     * fatal REST response or accidentally weakening authorization.
     *
     * @param callable(): bool $check
     */
    private function failClosed(string $step, callable $check): bool
    {
        try {
            return $check();
        } catch (\Throwable $failure) {
            try {
                $this->failureReporter?->report('REST permission', 0, $step, $failure);
            } catch (\Throwable) {
                // A diagnostic failure cannot weaken the permission decision.
            }

            return false;
        }
    }

    private function canAuthorPageBuilderContent(): bool
    {
        return $this->allowedCapabilities->currentUserHasAllowedCapability();
    }

    private function postType(int $postId): ?string
    {
        if ($postId <= 0) {
            return null;
        }

        $function = __NAMESPACE__ . '\\get_post_type';
        $postType = function_exists('get_post_type')
            ? \get_post_type($postId)
            : (function_exists($function) ? $function($postId) : null);

        return is_string($postType) && $postType !== '' ? $postType : null;
    }

    private function hasPostTypeApi(): bool
    {
        return function_exists('get_post_type')
            || function_exists(__NAMESPACE__ . '\\get_post_type');
    }

    private function postTypeObject(string $postType): ?object
    {
        $function = __NAMESPACE__ . '\\get_post_type_object';
        $postTypeObject = function_exists('get_post_type_object')
            ? \get_post_type_object($postType)
            : (function_exists($function) ? $function($postType) : null);

        return is_object($postTypeObject) ? $postTypeObject : null;
    }

    private function taxonomyObject(string $taxonomy): ?object
    {
        $function = __NAMESPACE__ . '\\get_taxonomy';
        $taxonomyObject = function_exists('get_taxonomy')
            ? \get_taxonomy($taxonomy)
            : (function_exists($function) ? $function($taxonomy) : null);

        return is_object($taxonomyObject) ? $taxonomyObject : null;
    }

    private function mappedCapability(object $capabilities, string $property): ?string
    {
        $capability = $capabilities->{$property} ?? null;

        return is_string($capability) && $capability !== '' ? $capability : null;
    }

    private function canMappedCapability(?string $capability, int $objectId = 0): bool
    {
        if ($capability === null) {
            return false;
        }

        return $objectId > 0
            ? current_user_can($capability, $objectId)
            : current_user_can($capability);
    }

    /**
     * @param int[] $itemIds
     */
    private function canReplaceNavigationTree(object $postCapabilities, ?string $assignTerms, array $itemIds): bool
    {
        if (!$this->canMappedCapability($assignTerms)) {
            return false;
        }

        if ($itemIds === []) {
            return $this->canMappedCapability($this->mappedCapability($postCapabilities, 'edit_posts'));
        }

        foreach ($itemIds as $itemId) {
            if ($itemId > 0) {
                if (
                    !$this->canMappedCapability(
                        $this->mappedCapability($postCapabilities, 'edit_post'),
                        $itemId,
                    )
                ) {
                    return false;
                }
                continue;
            }

            if (
                !$this->canMappedCapability(
                    $this->mappedCapability($postCapabilities, 'create_posts')
                        ?? $this->mappedCapability($postCapabilities, 'edit_posts'),
                )
            ) {
                return false;
            }
        }

        return true;
    }
}
