<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\EditorLock\InspectEditorOwnership;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipState;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipStatus;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class AdminCanvasEditorWindowedPage
{
    public const PAGE_SLUG = 'uncanny-page-builder-canvas-editor-windowed';
    public const ERROR_QUERY_ARG = 'upb_canvas_editor_error';
    public const ERROR_PAGE_ID_QUERY_ARG = 'upb_canvas_editor_page_id';
    private const ROOT_ID = 'upb-canvas-editor-windowed-root';
    private const ERROR_MISSING_PAGE_ID = 'missing_canvas_id';
    private const ERROR_INVALID_PAGE_ID = 'invalid_canvas_id';

    private int $selectedPageId = 0;
    private ?EditorOwnershipState $ownership = null;

    public function __construct(
        private readonly SectionRepositoryInterface $sectionRepository,
        private readonly ?PageDetailsPortInterface $pageDetails = null,
        private readonly ?InspectEditorOwnership $inspectOwnership = null,
        private readonly ?EditorLockDialogRenderer $lockDialogRenderer = null,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    public static function editorUrl(int $postId): string
    {
        return admin_url('admin.php?page=' . self::PAGE_SLUG . '&canvas_id=' . $postId);
    }

    // Admin host cleanup
    public function prepareAdminHost(): void
    {
        // Request contract
        $this->selectedPageId = $this->requestedOwnedPageId();

        if ($this->selectedPageId <= 0) {
            $this->redirectToPagesScreen($this->requestedCanvasId() > 0
                ? self::ERROR_INVALID_PAGE_ID
                : self::ERROR_MISSING_PAGE_ID);
        }

        // Hidden admin routes must set their own title before admin-header resolves it.
        $GLOBALS['title'] = $this->adminTitle($this->selectedPageId);
        $this->ownership = $this->inspectOwnership?->execute(
            $this->selectedPageId,
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
        $selectedPageId = $this->selectedPageId > 0
            ? $this->selectedPageId
            : $this->requestedOwnedPageId();

        echo '<div id="' . esc_attr(self::ROOT_ID) . '" class="upb-canvas-editor-windowed-page">';
        AdminImportNoticeStore::render(PageFactory::IMPORT_NOTICE_SCREEN);
        if ($selectedPageId > 0) {
            if (
                $this->ownership?->status() === EditorOwnershipStatus::Blocked
                && $this->lockDialogRenderer instanceof EditorLockDialogRenderer
            ) {
                echo '<div class="upb-canvas-editor-windowed-page__blocking-state">';
                echo $this->lockDialogRenderer->blocked(
                    $this->ownership,
                    $selectedPageId,
                    'page',
                    'windowed',
                );
                echo '</div>';
                echo '</div>';
                return;
            }

            $editorUrl = add_query_arg('editor_mode', 'windowed', AdminCanvasPage::editorUrl($selectedPageId));

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
            'Open a page from All Pages to launch the manual editor.',
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

    public function addAdminBodyClass($classes = null): string
    {
        $classes = is_string($classes) ? $classes : '';

        return trim($classes . ' upb-canvas-editor-windowed-host');
    }

    public static function rootId(): string
    {
        return self::ROOT_ID;
    }

    public static function pagesScreenUrl(?string $errorCode = null, int $pageId = 0): string
    {
        $url = admin_url('edit.php?post_type=page');

        if ($errorCode === null || $errorCode === '') {
            return $url;
        }

        $url = add_query_arg(self::ERROR_QUERY_ARG, $errorCode, $url);

        if ($pageId <= 0) {
            return $url;
        }

        return add_query_arg(self::ERROR_PAGE_ID_QUERY_ARG, (string) $pageId, $url);
    }

    public static function errorNoticeHtml(string $errorCode, int $pageId = 0): string
    {
        return match ($errorCode) {
            self::ERROR_MISSING_PAGE_ID => sprintf(
                /* translators: %s: link to the Page Builder pages list. */
                _x('Select a valid page from <a href="%s">this list</a>.', 'Page Builder', 'uncanny-automator'),
                esc_url(self::pagesScreenUrl())
            ),
            self::ERROR_INVALID_PAGE_ID => sprintf(
                /* translators: 1: page ID, 2: link to the Page Builder pages list. */
                _x('Page %1$d does not exist or is no longer available. Select a valid page from <a href="%2$s">this list</a>.', 'Page Builder', 'uncanny-automator'),
                $pageId,
                esc_url(self::pagesScreenUrl())
            ),
            default => '',
        };
    }

    public function filterHostAgentSurface($shouldRender = null, $surface = null): bool
    {
        if ($shouldRender !== true || !is_string($surface)) {
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

    private function requestedOwnedPageId(): int
    {
        $requestedPageId = $this->requestedCanvasId();

        if ($requestedPageId <= 0) {
            return 0;
        }

        // The hidden host route has a product-level capability gate, but the
        // requested page must still pass WordPress object authorization.
        if (!current_user_can('edit_post', $requestedPageId)) {
            return 0;
        }

        $postType = get_post_type($requestedPageId);
        if (!is_string($postType) || !$this->supportsPostType->isSupported($postType)) {
            return 0;
        }

        return $this->sectionRepository->isOwnedPage($requestedPageId)
            ? $requestedPageId
            : 0;
    }

    private function redirectToPagesScreen(string $errorCode): never
    {
        wp_safe_redirect(
            self::pagesScreenUrl($errorCode, $this->requestedCanvasId()),
            303,
            'Uncanny Page Builder'
        );
        exit;
    }

    private function adminTitle(int $pageId): string
    {
        return $this->pageTitle($pageId);
    }

    private function pageTitle(int $pageId): string
    {
        $draftTitle = $this->pageDetails?->find($pageId)?->title();
        if (is_string($draftTitle) && $draftTitle !== '') {
            return $draftTitle;
        }

        $title = get_the_title($pageId);

        if ($title !== '') {
            return (string) $title;
        }

        return sprintf(
            /* translators: %d: The WordPress page ID. */
            _x('Untitled page #%d', 'Page Builder', 'uncanny-automator'),
            $pageId,
        );
    }
}
