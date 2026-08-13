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
}
