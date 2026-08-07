<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;

final class WorkingCanvasAdminActions
{
    public const REFRESH_ALL_ACTION = 'uncanny_page_builder_refresh_all_working_canvases';
    public const CLEAR_FAILURES_ACTION = 'uncanny_page_builder_clear_working_canvas_failures';
    public const RESULT_QUERY_ARG = 'upb_refresh_result';
    public const COUNT_QUERY_ARG = 'upb_refresh_count';
    public const PROCESSED_QUERY_ARG = 'upb_refresh_processed';
    public const PENDING_QUERY_ARG = 'upb_refresh_pending';
    public const FAILED_QUERY_ARG = 'upb_refresh_failed';
    public const RESULT_REFRESHED_ALL = 'refreshed_all';
    private const INLINE_REBUILD_LIMIT = 25;

    public function __construct(
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly WorkingCanvasRefreshScheduler $scheduler,
        private readonly ?WpCronWorkingCanvasRefreshRunner $inlineRefreshRunner = null,
        private readonly ?WpCronWorkingCanvasRefreshQueue $refreshQueue = null,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::REFRESH_ALL_ACTION, [$this, 'refreshAll']);
        add_action('admin_post_' . self::CLEAR_FAILURES_ACTION, [$this, 'clearFailures']);
    }

    // Settings tools entry point
    public function actionUrl(): string
    {
        return admin_url('admin-post.php');
    }

    // Hidden admin-post controller
    public function refreshAll(): never
    {
        $this->assertPostRequest();

        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            wp_die(esc_html_x("You don't have permission to refresh Page Builder previews. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'));
        }

        check_admin_referer(self::REFRESH_ALL_ACTION);

        $queued = $this->scheduler->enqueueAll();
        $processed = $this->runInlineRefreshBatch()['processed'];
        $pending = $this->refreshQueue?->pendingCount() ?? max(0, $queued - $processed);
        $failed = count($this->refreshQueue?->terminalFailures() ?? []);

        wp_safe_redirect(
            $this->refreshAllRedirectUrl($queued, $processed, $pending, $failed),
            303,
            'Uncanny Page Builder'
        );
        exit;
    }

    public function clearFailures(): never
    {
        $this->assertPostRequest();

        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            wp_die(esc_html_x("You don't have permission to clear Page Builder preview failures. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'));
        }

        check_admin_referer(self::CLEAR_FAILURES_ACTION);
        $this->refreshQueue?->clearTerminalFailures();

        $redirectUrl = wp_get_referer();
        if (!is_string($redirectUrl) || $redirectUrl === '') {
            $redirectUrl = admin_url('admin.php?page=uncanny-page-builder');
        }

        wp_safe_redirect($redirectUrl, 303, 'Uncanny Page Builder');
        exit;
    }

    public function refreshAllRedirectUrl(int $queued, int $processed = 0, int $pending = 0, int $failed = 0): string
    {
        return add_query_arg(
            [
                'page' => 'uncanny-page-builder-settings',
                'settings' => 'tools',
                self::RESULT_QUERY_ARG => self::RESULT_REFRESHED_ALL,
                self::COUNT_QUERY_ARG => (string) max(0, $queued),
                self::PROCESSED_QUERY_ARG => (string) max(0, $processed),
                self::PENDING_QUERY_ARG => (string) max(0, $pending),
                self::FAILED_QUERY_ARG => (string) max(0, $failed),
            ],
            admin_url('admin.php')
        );
    }

    /**
     * @return array{processed: int, completed: int, requeued: int, terminal: int}
     */
    private function runInlineRefreshBatch(): array
    {
        if (!$this->inlineRefreshRunner instanceof WpCronWorkingCanvasRefreshRunner) {
            return ['processed' => 0, 'completed' => 0, 'requeued' => 0, 'terminal' => 0];
        }

        return $this->inlineRefreshRunner->runBatch(self::INLINE_REBUILD_LIMIT);
    }

    private function assertPostRequest(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') {
            return;
        }

        wp_die(
            esc_html_x('This maintenance action requires a POST request.', 'Page Builder', 'uncanny-automator'),
            esc_html_x('Invalid request', 'Page Builder', 'uncanny-automator'),
            ['response' => 405],
        );
    }
}
