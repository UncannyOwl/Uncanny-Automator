<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class AdminBarButton
{
    public function __construct(
        private readonly SectionRepositoryInterface $repository,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly PageBuilderAvailabilityInterface $availability,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    public function register(\WP_Admin_Bar $adminBar): void
    {
        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            return;
        }

        if ($this->availability->allowsNewPages()) {
            $adminBar->add_node([
                'id'    => 'uncanny-page-builder-create-page',
                'title' => _x('New page', 'Page Builder', 'uncanny-automator'),
                'href'  => wp_nonce_url(
                    admin_url('admin-post.php?action=uncanny_page_builder_create_page'),
                    'uncanny_page_builder_create_page'
                ),
                'meta'  => [
                    'title' => _x('New page', 'Page Builder', 'uncanny-automator'),
                ],
            ]);
        }

        // "Edit with Uncanny Page Builder" for owned pages on the frontend.
        if (!is_admin() && is_singular()) {
            $postId = get_the_ID();
            $postType = $postId ? get_post_type((int) $postId) : null;
            if (
                $postId
                && is_string($postType)
                && $this->supportsPostType->isSupported($postType)
                && $this->repository->isOwnedPage((int) $postId)
            ) {
                $adminBar->add_node([
                    'id'    => 'uncanny-page-builder-edit-canvas',
                    'title' => _x('Edit with Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
                    'href'  => AdminCanvasEditorWindowedPage::editorUrl((int) $postId),
                    'meta'  => [
                        'title' => _x('Edit with Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
                    ],
                ]);
            }
        }
    }
}
