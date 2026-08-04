<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\EditorLock\TakeOverEditorOwnership;
use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipState;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipStatus;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class EditorLockTakeoverAction
{
    public const ACTION = 'uncanny_page_builder_take_over_editor';

    public function __construct(
        private readonly TakeOverEditorOwnership $takeOverOwnership,
        private readonly EditorLockStoreInterface $editorLockStore,
        private readonly SectionRepositoryInterface $sectionRepository,
        private readonly EditorLockDialogRenderer $dialogRenderer,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    public static function nonceAction(int $postId): string
    {
        return self::ACTION . '_' . $postId;
    }

    public function handle(): never
    {
        $postId = absint($_POST['target_id'] ?? 0);
        $editorMode = sanitize_key((string) ($_POST['editor_mode'] ?? 'windowed'));
        $editorMode = in_array($editorMode, ['windowed', 'fullscreen'], true)
            ? $editorMode
            : 'windowed';

        check_admin_referer(self::nonceAction($postId));

        $post = get_post($postId);
        if (
            !$post instanceof \WP_Post
            || !$this->allowedCapabilities->currentUserHasAllowedCapability()
            || !current_user_can('edit_post', $postId)
            || !$this->isEditableTarget($post)
        ) {
            wp_die(
                esc_html_x("You don't have permission to take over this editor.", 'Page Builder', 'uncanny-automator'),
                esc_html_x('Takeover denied', 'Page Builder', 'uncanny-automator'),
                ['response' => 403],
            );
        }

        $targetKind = $post->post_type === 'upb_global_part' ? 'reusable' : 'page';

        try {
            $lockingEnabled = $this->editorLockStore->isEnabled($postId);
        } catch (\Throwable) {
            $state = EditorOwnershipState::unavailable('feature check unavailable');
            $this->logUnconfirmedTakeover($postId, $targetKind, 'feature_check_unavailable');
            $this->dialogRenderer->takeoverFailure($postId, $targetKind, $editorMode, $state);
        }

        /*
         * The emergency filter is an intentional compatibility bypass. Keep
         * the server-owned destination and authorization checks even when the
         * ownership feature itself is disabled.
         */
        if (!$lockingEnabled) {
            $this->redirectToEditorOrFail($postId, $targetKind, $editorMode);
        }

        $state = $this->takeOverOwnership->execute($postId, (int) get_current_user_id());

        if ($state->status() === EditorOwnershipStatus::Owned) {
            $this->redirectToEditorOrFail($postId, $targetKind, $editorMode);
        }

        $reason = $state->status() === EditorOwnershipStatus::Unavailable
            ? 'ownership_unavailable'
            : 'ownership_not_confirmed';
        $this->logUnconfirmedTakeover($postId, $targetKind, $reason);
        $this->dialogRenderer->takeoverFailure($postId, $targetKind, $editorMode, $state);
    }

    private function redirectToEditorOrFail(
        int $postId,
        string $targetKind,
        string $editorMode,
    ): never {
        if (
            wp_safe_redirect(
                $this->editorUrl($postId, $targetKind, $editorMode),
                303,
                'Uncanny Page Builder',
            )
        ) {
            exit;
        }

        $state = EditorOwnershipState::unavailable('editor redirect unavailable');
        $this->logUnconfirmedTakeover($postId, $targetKind, 'redirect_unavailable');
        $this->dialogRenderer->takeoverFailure($postId, $targetKind, $editorMode, $state);
    }

    private function isEditableTarget(\WP_Post $post): bool
    {
        if ($post->post_type === 'upb_global_part') {
            return true;
        }

        return $this->supportsPostType->isSupported($post->post_type)
            && $this->sectionRepository->isOwnedPage((int) $post->ID);
    }

    private function editorUrl(int $postId, string $targetKind, string $editorMode): string
    {
        if ($editorMode === 'fullscreen') {
            return AdminCanvasPage::editorUrl($postId);
        }

        return $targetKind === 'reusable'
            ? AdminCanvasEditorWindowedGlobalPartPage::editorUrl($postId)
            : AdminCanvasEditorWindowedPage::editorUrl($postId);
    }

    private function logUnconfirmedTakeover(int $postId, string $scope, string $reason): void
    {
        error_log(sprintf(
            '[Uncanny Page Builder] Editor takeover not confirmed target=%d actor=%d scope=%s reason=%s',
            $postId,
            (int) get_current_user_id(),
            $scope,
            $reason !== '' ? $reason : 'ownership_not_confirmed',
        ));
    }
}
