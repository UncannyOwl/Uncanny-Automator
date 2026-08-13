<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipState;
use UncannyPageBuilder\Domain\EditorLock\EditorLockOwner;

/**
 * Server-rendered ownership UI.
 *
 * The takeover and exit paths deliberately remain plain HTML forms and links
 * so a script failure cannot trap a user on the editor route.
 */
final class EditorLockDialogRenderer
{
    public function blocked(
        EditorOwnershipState $state,
        int $postId,
        string $targetKind,
        string $editorMode,
        string $errorMessage = '',
    ): string {
        $owner = $state->owner();
        $ownerName = $owner?->displayName() ?? _x('Another user', 'Page Builder', 'uncanny-automator');
        $ownerAvatar = $owner?->avatarUrl() ?? '';
        $backUrl = $this->backUrl($targetKind);
        $backLabel = _x('Go back', 'Page Builder', 'uncanny-automator');
        $previewUrl = $this->previewUrl($postId);
        $previewLabel = _x('Preview', 'Page Builder', 'uncanny-automator');
        $title = _x('This item is already being edited', 'Page Builder', 'uncanny-automator');
        /* translators: %s: display name of the current editor owner. */
        $ownerSummary = _x(
            '%s is currently editing this uncanny page builder page. Do you want to take over?',
            'Page Builder',
            'uncanny-automator',
        );
        $takeoverLabel = _x('Take over', 'Page Builder', 'uncanny-automator');
        $takeoverFormId = $state->takeoverAllowed()
            ? 'upb-editor-lock-takeover-' . max(0, $postId)
            : '';

        ob_start();
        ?>
        <?php if ($takeoverFormId !== '') : ?>
            <form
                id="<?php echo esc_attr($takeoverFormId); ?>"
                method="post"
                target="_top"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            >
                <input type="hidden" name="action" value="<?php echo esc_attr(EditorLockTakeoverAction::ACTION); ?>">
                <input type="hidden" name="target_id" value="<?php echo esc_attr((string) $postId); ?>">
                <input type="hidden" name="editor_mode" value="<?php echo esc_attr($editorMode); ?>">
                <?php wp_nonce_field(EditorLockTakeoverAction::nonceAction($postId)); ?>
            </form>
        <?php endif; ?>
        <div
            data-upb-editor-lock-component
            data-title="<?php echo esc_attr($title); ?>"
            data-owner-name="<?php echo esc_attr($ownerName); ?>"
            data-owner-summary="<?php echo esc_attr($ownerSummary); ?>"
            data-avatar-url="<?php echo esc_url($ownerAvatar); ?>"
            data-back-url="<?php echo esc_url($backUrl); ?>"
            data-back-label="<?php echo esc_attr($backLabel); ?>"
            data-preview-url="<?php echo esc_url($previewUrl); ?>"
            data-preview-label="<?php echo esc_attr($previewLabel); ?>"
            data-takeover-label="<?php echo esc_attr($takeoverFormId !== '' ? $takeoverLabel : ''); ?>"
            data-takeover-form-id="<?php echo esc_attr($takeoverFormId); ?>"
        ></div>
        <div class="upb-editor-lock-screen" data-upb-editor-lock-fallback>
            <div
                class="upb-editor-lock-dialog<?php echo $ownerAvatar !== '' ? ' upb-editor-lock-dialog--has-avatar' : ''; ?>"
                role="dialog"
                aria-modal="true"
                aria-label="<?php echo esc_attr($title); ?>"
            >
                <?php if ($ownerAvatar !== '') : ?>
                    <img class="upb-editor-lock-avatar" src="<?php echo esc_url($ownerAvatar); ?>" alt="" width="64" height="64">
                <?php endif; ?>
                <div class="upb-editor-lock-content">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: display name of the user who owns the editor. */
                            esc_html($ownerSummary),
                            esc_html($ownerName),
                        );
                        ?>
                    </p>
                    <?php if ($errorMessage !== '') : ?>
                        <div class="notice notice-error inline" role="alert">
                            <p><?php echo esc_html($errorMessage); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="upb-editor-lock-actions">
                        <a class="button upb-editor-lock-back" target="_top" href="<?php echo esc_url($backUrl); ?>">
                            <?php echo esc_html($backLabel); ?>
                        </a>
                        <?php if ($previewUrl !== '') : ?>
                            <a
                                class="button upb-editor-lock-preview"
                                target="_blank"
                                rel="noopener noreferrer"
                                href="<?php echo esc_url($previewUrl); ?>"
                            >
                                <?php echo esc_html($previewLabel); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($takeoverFormId !== '') : ?>
                            <button
                                type="submit"
                                class="button button-primary"
                                form="<?php echo esc_attr($takeoverFormId); ?>"
                            >
                                <?php echo esc_html($takeoverLabel); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return $this->styles() . (string) ob_get_clean();
    }

    public function takeoverFailure(
        int $postId,
        string $targetKind,
        string $editorMode,
        EditorOwnershipState $state,
    ): never {
        try {
            $message = _x(
                "We couldn't confirm the takeover. No ownership change was reported. Try again or leave the editor.",
                'Page Builder',
                'uncanny-automator',
            );
            $retryState = $state->owner() !== null
                ? $state
                : EditorOwnershipState::blocked(
                    new EditorLockOwner(0, _x('Another editor', 'Page Builder', 'uncanny-automator'), ''),
                    true,
                );
            $body = $this->blocked($retryState, $postId, $targetKind, $editorMode, $message);
            $title = esc_html_x('Editing takeover not confirmed', 'Page Builder', 'uncanny-automator');
        } catch (\Throwable $failure) {
            error_log('[Uncanny Page Builder] Editor takeover failure dialog failed (' . $failure::class . ')');
            $body = 'Uncanny Page Builder could not confirm the editor takeover. Return to Pages and try again.';
            $title = 'Editing takeover not confirmed';
        }

        wp_die($body, $title, ['response' => 409]);
    }

    public function terminateBlocked(
        EditorOwnershipState $state,
        int $postId,
        string $targetKind,
        string $editorMode,
    ): never {
        try {
            $body = $this->blocked($state, $postId, $targetKind, $editorMode);
            $title = esc_html_x('Item is already being edited', 'Page Builder', 'uncanny-automator');
        } catch (\Throwable $failure) {
            error_log('[Uncanny Page Builder] Editor ownership dialog failed (' . $failure::class . ')');
            $body = 'This item is already being edited. Return to Pages and try again.';
            $title = 'Item is already being edited';
        }

        wp_die($body, $title, ['response' => 409]);
    }

    private function backUrl(string $targetKind): string
    {
        return $targetKind === 'reusable'
            ? AdminCanvasEditorWindowedGlobalPartPage::reusablesScreenUrl()
            : AdminCanvasEditorWindowedPage::pagesScreenUrl();
    }

    private function previewUrl(int $postId): string
    {
        if (!function_exists('get_preview_post_link')) {
            return '';
        }

        try {
            $previewUrl = \get_preview_post_link($postId);
        } catch (\Throwable) {
            // Preview is optional; a broken preview filter must not blank the
            // ownership dialog or remove its safe exit/takeover actions.
            return '';
        }

        return is_string($previewUrl) ? $previewUrl : '';
    }

    private function styles(): string
    {
        return <<<'HTML'
<style>
/*
 * No-JavaScript fallback. The normal windowed route replaces this surface
 * with @wordpress/components Modal and Button after a successful mount.
 */
[data-upb-editor-lock-fallback][hidden]{display:none!important}
.upb-editor-lock-screen{
    position:fixed;
    inset:0;
    z-index:100000;
    display:flex;
    align-items:center;
    justify-content:center;
    box-sizing:border-box;
    min-height:100%;
    padding:24px;
    overflow:auto;
    background:rgba(0,0,0,.35)
}

.upb-editor-lock-dialog{
    display:grid;
    grid-template-columns:minmax(0,1fr);
    column-gap:24px;
    width:min(100%,560px);
    max-height:calc(100vh - 48px);
    padding:24px;
    overflow:auto;
    border:0;
    border-radius:8px;
    background:#fff;
    box-shadow:0 3px 30px rgba(0,0,0,.2);
    box-sizing:border-box;
    color:#1e1e1e;
    text-align:left
}
.upb-editor-lock-dialog--has-avatar{grid-template-columns:64px minmax(0,1fr)}
.upb-editor-lock-content{min-width:0}
.upb-editor-lock-dialog p{margin:0;color:#50575e;font-size:13px;line-height:1.4}
.upb-editor-lock-avatar{width:64px;height:64px;border-radius:50%;object-fit:cover}
.upb-editor-lock-actions{display:flex;flex-wrap:wrap;justify-content:flex-start;gap:8px;margin-top:16px}
.upb-editor-lock-actions form{margin:0}
.upb-editor-lock-actions .button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-sizing:border-box;
    min-height:40px;
    height:auto;
    padding:6px 16px;
    white-space:normal;
    text-align:center
}
.upb-editor-lock-dialog .notice{margin:20px 0 0}
.upb-editor-lock-dialog .notice p{margin:.5em 0;color:#1d2327}

@media (max-width:480px){
    .upb-editor-lock-screen{align-items:flex-end;padding:16px}
    .upb-editor-lock-dialog{max-height:calc(100vh - 32px);column-gap:16px}
    .upb-editor-lock-dialog--has-avatar{grid-template-columns:48px minmax(0,1fr)}
    .upb-editor-lock-avatar{width:48px;height:48px}
    .upb-editor-lock-actions{flex-direction:column-reverse;align-items:stretch}
    .upb-editor-lock-actions form,.upb-editor-lock-actions .button{width:100%}
}
</style>
HTML;
    }
}
