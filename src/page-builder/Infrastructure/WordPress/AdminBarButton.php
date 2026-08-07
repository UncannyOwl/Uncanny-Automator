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

    public function register($adminBar = null): void
    {
        if (!$adminBar instanceof \WP_Admin_Bar) {
            return;
        }

        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            return;
        }

        if ($this->availability->allowsNewPages()) {
            $createLabel = _x('New page', 'Page Builder', 'uncanny-automator');
            $createForm = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">'
                . '<input type="hidden" name="action" value="' . esc_attr(PageFactory::CREATE_ACTION) . '">'
                . '<input type="hidden" name="_wpnonce" value="' . esc_attr((string) wp_create_nonce(PageFactory::CREATE_ACTION)) . '">'
                . '<button type="submit" style="background:none;border:0;color:inherit;cursor:pointer;font:inherit;padding:0">'
                . esc_html($createLabel)
                . '</button></form>';

            $adminBar->add_node([
                'id'    => 'uncanny-page-builder-create-page',
                'title' => $createForm,
                'meta'  => [
                    'title' => $createLabel,
                ],
            ]);
        }

        // "Edit with Uncanny Page Builder" for owned pages on the frontend.
        if (!is_admin() && is_singular()) {
            $postId = WordPressPostId::fromCurrentQuery(get_queried_object_id());
            $postType = $postId !== null ? get_post_type($postId) : null;
            if (
                $postId !== null
                && is_string($postType)
                && $this->supportsPostType->isSupported($postType)
                && $this->repository->isOwnedPage($postId)
            ) {
                $adminBar->add_node([
                    'id'    => 'uncanny-page-builder-edit-canvas',
                    'title' => _x('Edit with Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
                    'href'  => AdminCanvasEditorWindowedPage::editorUrl($postId),
                    'meta'  => [
                        'title' => _x('Edit with Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
                    ],
                ]);
            }
        }
    }
}
