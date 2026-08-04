<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Domain\Canvas\PageOwnershipRepositoryInterface;

/**
 * Presents Page Builder ownership and editing routes in WordPress's native Pages list.
 */
final class NativePageListPresenter
{
    public const OWNERSHIP_STATE = 'uncanny_page_builder';

    /**
     * Other editors may keep dormant source data so a user can return to them.
     * Their list-table labels must not claim active ownership while UPB owns the page.
     *
     * @var string[]
     */
    private const DORMANT_EDITOR_STATE_KEYS = [
        'classic-editor-plugin',
        'elementor',
        'seedprod',
        'seedprod-editor',
    ];

    /**
     * These action keys are added by popular editors after WordPress builds its
     * native row. Hiding only their editor entry points preserves unrelated
     * actions from SEO, duplication, workflow, and other admin plugins.
     *
     * @var string[]
     */
    private const DORMANT_EDITOR_ACTION_KEYS = [
        'classic',
        'classic-editor-block',
        'classic-editor-classic',
        'edit_vc',
        'edit_seedprod',
        'edit_with_elementor',
    ];

    /**
     * Classic Editor replaces WordPress's `edit` action with these keys. Their
     * presence therefore proves WordPress originally granted edit access.
     *
     * @var string[]
     */
    private const EDIT_PERMISSION_ACTION_KEYS = [
        'edit',
        'classic-editor-block',
        'classic-editor-classic',
    ];

    public function __construct(
        private readonly PageOwnershipRepositoryInterface $ownership,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    // Section: Native WordPress post states

    /**
     * @param array<int|string, string> $states
     * @return array<int|string, string>
     */
    public function addOwnershipState(array $states, \WP_Post $post): array
    {
        if (!$this->isPresentedAsOwned($post)) {
            return $states;
        }

        // Keep foreign editor data dormant, but expose one unambiguous active
        // owner in the native Pages list. Core states such as Draft stay intact.
        foreach (self::DORMANT_EDITOR_STATE_KEYS as $stateKey) {
            unset($states[$stateKey]);
        }

        $states[self::OWNERSHIP_STATE] = _x('Uncanny Page Builder', 'Page Builder', 'uncanny-automator');

        return $states;
    }

    // Section: Owned page navigation

    /**
     * Make the Page Builder canvas the primary editing surface while preserving
     * the native WordPress screen as the page's secondary settings surface.
     *
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function routeOwnedPageActions(array $actions, \WP_Post $post): array
    {
        if (!$this->isPresentedAsOwned($post)) {
            return $actions;
        }

        $hasWordPressEditAction = $this->hasWordPressEditAction($actions);
        $actions = $this->withoutDormantEditorActions($actions);

        // Trashed pages keep only lifecycle actions such as Restore/Delete. A
        // foreign editor must not reopen a page while WordPress considers it trashed.
        if (($post->post_status ?? '') === 'trash') {
            unset($actions['edit']);

            return $actions;
        }

        if (!$hasWordPressEditAction) {
            return $actions;
        }

        $settingsUrl = admin_url('post.php?post=' . $post->ID . '&action=edit');
        $pageTitle = trim((string) ($post->post_title ?? ''));
        if ($pageTitle === '') {
            $pageTitle = _x('(no title)', 'Page Builder', 'uncanny-automator');
        }

        if (!$this->supportsPostType->isSupported($post->post_type)) {
            $recoveryLabel = sprintf(
                /* translators: %s: Page title. */
                _x('Resolve Page Builder ownership for “%s”', 'Page Builder', 'uncanny-automator'),
                $pageTitle,
            );
            $actions['edit'] = '<a href="' . esc_url($settingsUrl) . '" aria-label="' . esc_attr($recoveryLabel) . '">'
                . esc_html_x('Page Builder settings', 'Page Builder', 'uncanny-automator') . '</a>';

            return $actions;
        }

        $canvasUrl = AdminCanvasEditorWindowedPage::editorUrl($post->ID);
        $editLabel = sprintf(
            /* translators: %s: Page title. */
            _x('Edit “%s” in Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
            $pageTitle,
        );
        $settingsLabel = sprintf(
            /* translators: %s: Page title. */
            _x('Open WordPress settings for “%s”', 'Page Builder', 'uncanny-automator'),
            $pageTitle,
        );

        $updatedActions = [
            'edit' => '<a href="' . esc_url($canvasUrl) . '" aria-label="' . esc_attr($editLabel) . '">'
                . esc_html_x('Edit', 'Page Builder', 'uncanny-automator') . '</a>',
            'settings' => '<a href="' . esc_url($settingsUrl) . '" aria-label="' . esc_attr($settingsLabel) . '">'
                . esc_html_x('Settings', 'Page Builder', 'uncanny-automator') . '</a>',
        ];

        foreach ($actions as $key => $action) {
            if ($key === 'edit') {
                continue;
            }

            $updatedActions[$key] = $action;
        }

        return $updatedActions;
    }

    /**
     * WordPress reuses `get_edit_post_link` for save redirects, revisions,
     * dashboard links, and the admin bar. Restrict this override to the native
     * Pages list so Settings saves remain on the WordPress settings screen.
     */
    public function routeOwnedPageEditLink(string $location, int $postId, string $context = 'display'): string
    {
        if (
            !$this->isNativePagesListScreen()
            || $postId <= 0
            || !$this->supportsPostId($postId)
            || !$this->ownership->isOwned($postId)
        ) {
            return $location;
        }

        $canvasUrl = AdminCanvasEditorWindowedPage::editorUrl($postId);

        return $context === 'display'
            ? str_replace('&', '&amp;', $canvasUrl)
            : $canvasUrl;
    }

    /** @param array<string, string> $actions */
    private function hasWordPressEditAction(array $actions): bool
    {
        foreach (self::EDIT_PERMISSION_ACTION_KEYS as $actionKey) {
            if (array_key_exists($actionKey, $actions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    private function withoutDormantEditorActions(array $actions): array
    {
        unset($actions['inline hide-if-no-js']);

        foreach (self::DORMANT_EDITOR_ACTION_KEYS as $actionKey) {
            unset($actions[$actionKey]);
        }

        return $actions;
    }

    private function isNativePagesListScreen(): bool
    {
        if (($GLOBALS['pagenow'] ?? '') !== 'edit.php') {
            return false;
        }

        $screen = get_current_screen();

        return is_object($screen)
            && $this->supportsPostType->isSupported((string) ($screen->post_type ?? ''));
    }

    private function isOwnedPage(\WP_Post $post): bool
    {
        return $post->ID > 0
            && $this->ownership->isOwned($post->ID);
    }

    /**
     * Persisted ownership cannot override the administrator's current intent.
     * It remains dormant for recovery if the post type is enabled again.
     */
    private function isPresentedAsOwned(\WP_Post $post): bool
    {
        return $this->supportsPostType->isEnabledByAdministrator($post->post_type)
            && $this->isOwnedPage($post);
    }

    private function supportsPostId(int $postId): bool
    {
        $postType = get_post_type($postId);

        return is_string($postType)
            && $this->supportsPostType->isSupported($postType);
    }
}
