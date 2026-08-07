<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;

final class WorkingCanvasRefreshNotice
{
    public function __construct(
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly ?WpCronWorkingCanvasRefreshQueue $refreshQueue = null,
    ) {
    }

    public function register(): void
    {
        add_action('admin_notices', [$this, 'render']);
    }

    public function render(): void
    {
        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            return;
        }

        if (!$this->shouldRenderOnCurrentScreen()) {
            return;
        }

        $this->renderTerminalRefreshFailuresNotice();
    }

    private function shouldRenderOnCurrentScreen(): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if ($screen === null) {
            return false;
        }

        if (
            in_array($screen->id ?? '', [
            'toplevel_page_uncanny-page-builder',
            'uncanny-page-builder_page_uncanny-page-builder-settings',
            ], true)
        ) {
            return true;
        }

        return ($screen->post_type ?? '') === 'upb_global_part';
    }

    private function renderTerminalRefreshFailuresNotice(): void
    {
        $failures = $this->refreshQueue?->terminalFailures() ?? [];
        if ($failures === []) {
            return;
        }

        echo '<div class="notice notice-info"><p>';
        echo esc_html_x(
            'Uncanny Page Builder could not update some background preview information. You can clear this notice.',
            'Page Builder',
            'uncanny-automator',
        );
        echo ' <strong>';
        echo esc_html_x('Your pages are safe.', 'Page Builder', 'uncanny-automator');
        echo '</strong>';
        echo '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(WorkingCanvasAdminActions::CLEAR_FAILURES_ACTION) . '">';
        wp_nonce_field(WorkingCanvasAdminActions::CLEAR_FAILURES_ACTION);
        echo '<button type="submit" class="button button-small">';
        echo esc_html_x('Clear this notice', 'Page Builder', 'uncanny-automator');
        echo '</button></form>';
        echo '</div>';
    }
}
