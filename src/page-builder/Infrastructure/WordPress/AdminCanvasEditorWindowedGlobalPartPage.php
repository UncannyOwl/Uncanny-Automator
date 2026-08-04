<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\EditorLock\InspectEditorOwnership;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipState;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipStatus;

/**
 * Windowed canvas editor host for reusable parts (upb_global_part).
 *
 * Parallels {@see AdminCanvasEditorWindowedPage} (the page host): it renders
 * WordPress admin chrome around an <iframe> that loads the standalone canvas.
 * The inner canvas ({@see AdminCanvasPage}) already accepts reusables, so this
 * host only adds the admin-chrome shell and reusable-aware request validation.
 *
 * URL: wp-admin/admin.php?page=uncanny-page-builder-canvas-editor-windowed-reusable&canvas_id={global_part_id}
 */
final class AdminCanvasEditorWindowedGlobalPartPage
{
    public const PAGE_SLUG = 'uncanny-page-builder-canvas-editor-windowed-reusable';
    private const GLOBAL_PART_CPT = 'upb_global_part';
    private const ROOT_ID = 'upb-canvas-editor-windowed-root';

    private int $selectedGlobalPartId = 0;
    private ?EditorOwnershipState $ownership = null;

    public function __construct(
        private readonly ?InspectEditorOwnership $inspectOwnership = null,
        private readonly ?EditorLockDialogRenderer $lockDialogRenderer = null,
    ) {}

    public static function editorUrl(int $globalPartId): string
    {
        return admin_url('admin.php?page=' . self::PAGE_SLUG . '&canvas_id=' . $globalPartId);
    }

    public static function reusablesScreenUrl(): string
    {
        return admin_url('edit.php?post_type=' . self::GLOBAL_PART_CPT);
    }

    public static function rootId(): string
    {
        return self::ROOT_ID;
    }

    // Admin host cleanup
    public function prepareAdminHost(): void
    {
        // Request contract
        $this->selectedGlobalPartId = $this->requestedGlobalPartId();

        if ($this->selectedGlobalPartId <= 0) {
            $this->redirectToReusablesScreen();
        }

        // Hidden admin routes must set their own title before admin-header resolves it.
        $GLOBALS['title'] = $this->adminTitle($this->selectedGlobalPartId);
        $this->ownership = $this->inspectOwnership?->execute(
            $this->selectedGlobalPartId,
            (int) get_current_user_id(),
        );

        // Remove admin chrome payload that normally prints ahead of editor screens.
        foreach (
            [
            'network_admin_notices',
            'user_admin_notices',
            'admin_notices',
            'all_admin_notices',
            ] as $hookName
        ) {
            remove_all_actions($hookName);
        }

        add_filter('screen_options_show_screen', '__return_false');
        add_filter('admin_body_class', [$this, 'addAdminBodyClass']);
        add_filter('automator_mcp_should_render_surface', [$this, 'filterHostAgentSurface'], 10, 2);
    }

    public function render(): void
    {
        $selectedGlobalPartId = $this->selectedGlobalPartId > 0
            ? $this->selectedGlobalPartId
            : $this->requestedGlobalPartId();

        echo '<div id="' . esc_attr(self::ROOT_ID) . '" class="upb-canvas-editor-windowed-page">';
        if ($selectedGlobalPartId > 0) {
            if (
                $this->ownership?->status() === EditorOwnershipStatus::Blocked
                && $this->lockDialogRenderer instanceof EditorLockDialogRenderer
            ) {
                echo '<div class="upb-canvas-editor-windowed-page__blocking-state">';
                echo $this->lockDialogRenderer->blocked(
                    $this->ownership,
                    $selectedGlobalPartId,
                    'reusable',
                    'windowed',
                );
                echo '</div>';
                echo '</div>';
                return;
            }

            $editorUrl = add_query_arg('editor_mode', 'windowed', AdminCanvasPage::editorUrl($selectedGlobalPartId));

            echo '<div class="upb-canvas-editor-windowed-page__frame-stage">';
            echo '<iframe class="upb-canvas-editor-windowed-page__frame" src="' . esc_url($editorUrl) . '" title="' . esc_attr_x('Embedded Uncanny Page Builder manual editor', 'Page Builder', 'uncanny-automator') . '"></iframe>';
            echo '</div>';
            echo $this->renderHostCanvasRefreshBridge();
            echo '</div>';
            return;
        }

        echo '<div class="upb-canvas-editor-windowed-page__empty-state">';
        echo '<h2 class="upb-canvas-editor-windowed-page__empty-title">' . esc_html_x('Manual editor unavailable', 'Page Builder', 'uncanny-automator') . '</h2>';
        echo '<p class="upb-canvas-editor-windowed-page__empty-copy">' . esc_html_x(
            'Open a reusable from the Reusables list to launch the manual editor.',
            'Page Builder',
            'uncanny-automator'
        ) . '</p>';
        echo '</div>';
        echo '</div>';
    }

    private function renderHostCanvasRefreshBridge(): string
    {
        // Expose the same public refresh shim on the outer wp-admin host so
        // humans do not need to switch DevTools into the iframe context.
        return <<<'HTML'
<script>
(function() {
    if (window.UncannyPageBuilderCanvas && typeof window.UncannyPageBuilderCanvas.refresh === 'function') {
        return;
    }

    function resolveCanvasApi() {
        var frame = document.querySelector('#upb-canvas-editor-windowed-root .upb-canvas-editor-windowed-page__frame');
        var frameWindow = frame && frame.contentWindow ? frame.contentWindow : null;
        var api = frameWindow && frameWindow.UncannyPageBuilderCanvas ? frameWindow.UncannyPageBuilderCanvas : null;

        if (api && typeof api.refresh === 'function') {
            return api;
        }

        return null;
    }

    window.UncannyPageBuilderCanvas = {
        refresh: function(options) {
            var api = resolveCanvasApi();

            if (!api) {
                return Promise.resolve({
                    ok: false,
                    refreshed: false,
                    reason: 'canvas_unavailable',
                    message: 'Canvas frame API is not ready yet.'
                });
            }

            return api.refresh(options || {});
        }
    };
})();
</script>
HTML;
    }

    public function addAdminBodyClass(string $classes): string
    {
        return trim($classes . ' upb-canvas-editor-windowed-host');
    }

    public function filterHostAgentSurface(bool $shouldRender, string $surface): bool
    {
        if (!$shouldRender) {
            return false;
        }

        return !in_array($surface, [
            'admin_sdk',
            'admin_launcher',
            'admin_bar_quicklink',
            'admin_bar_quicklink_styles',
        ], true);
    }

    private function requestedCanvasId(): int
    {
        return absint($_GET['canvas_id'] ?? 0);
    }

    private function requestedGlobalPartId(): int
    {
        $requestedCanvasId = $this->requestedCanvasId();

        if ($requestedCanvasId <= 0) {
            return 0;
        }

        if (get_post_type($requestedCanvasId) !== self::GLOBAL_PART_CPT) {
            return 0;
        }

        if (!current_user_can('edit_post', $requestedCanvasId)) {
            return 0;
        }

        // Mirror the repository's renderable-reusable rule (publish-only): a
        // draft/pending/trashed reusable cannot be hydrated, so it would boot an
        // empty canvas. Reject it here and let prepareAdminHost() redirect to the
        // reusables list instead.
        return get_post_status($requestedCanvasId) === 'publish'
            ? $requestedCanvasId
            : 0;
    }

    private function redirectToReusablesScreen(): never
    {
        wp_safe_redirect(
            self::reusablesScreenUrl(),
            303,
            'Uncanny Page Builder'
        );
        exit;
    }

    private function adminTitle(int $globalPartId): string
    {
        return $this->globalPartTitle($globalPartId);
    }

    private function globalPartTitle(int $globalPartId): string
    {
        $title = get_the_title($globalPartId);

        if (is_string($title) && $title !== '') {
            return $title;
        }

        return _x('Untitled reusable', 'Page Builder', 'uncanny-automator');
    }
}
