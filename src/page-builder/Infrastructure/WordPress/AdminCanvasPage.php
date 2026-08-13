<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\EditorLock\EnterEditorOwnership;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipState;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipStatus;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Infrastructure\Rendering\CanvasRenderer;
use UncannyPageBuilder\Kernel\Providers\Integrations\Automator\McpAgentProvider;

/**
 * Full-screen canvas editor inside wp-admin.
 *
 * The canvas carries REST nonces. It explicitly sends
 * no-cache headers because browser history caches can still revive stale
 * editor documents even inside wp-admin.
 *
 * URL: wp-admin/admin.php?page=uncanny-page-builder-canvas&canvas_id={post_id}
 */
final class AdminCanvasPage
{
    public const PAGE_SLUG = 'uncanny-page-builder-canvas';

    private string $hookSuffix = '';

    /**
     * Admin URL for the canvas editor.
     *
     * Centralized so every link (list tables, editor UI, redirects) points
     * to the cache-proof wp-admin canvas instead of the frontend permalink.
     */
    public static function editorUrl(int $postId): string
    {
        return admin_url('admin.php?page=' . self::PAGE_SLUG . '&canvas_id=' . $postId);
    }

    public function __construct(
        private readonly SectionRepositoryInterface $repository,
        private readonly CanvasRenderer $canvasRenderer,
        private readonly MagicBridgeEnqueuer $bridgeEnqueuer,
        private readonly CanvasAssetAllowlist $assetAllowlist,
        private readonly CanvasAdminBar $canvasAdminBar,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly ?EnterEditorOwnership $enterOwnership = null,
        private readonly ?EditorLockStoreInterface $editorLockStore = null,
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
        private readonly ?EditorLockDialogRenderer $lockDialogRenderer = null,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    public function register(): void
    {
        $this->hookSuffix = (string) add_submenu_page(
            null, // Hidden — no parent menu entry.
            _x('Manual editor', 'Page Builder', 'uncanny-automator'),
            '',
            PageBuilderAccessCapability::NAME,
            self::PAGE_SLUG,
            '__return_null', // Never reached — we exit in the load hook.
        );

        if ($this->hookSuffix !== '') {
            add_action('load-' . $this->hookSuffix, [$this, 'handleLoad']);
        }
    }

    /**
     * Fires before any admin HTML output. Validates, renders canvas, exits.
     */
    public function handleLoad(): void
    {
        $postId = self::canvasIdFromRequest($_GET, $_POST);
        $editorMode = (string) ($_GET['editor_mode'] ?? 'fullscreen');
        $editorMode = $editorMode === 'windowed' ? 'windowed' : 'fullscreen';

        // The canvas is a long-lived editor surface, so it must never render as
        // a POST response. Accidental form submissions should land back on the
        // canonical GET editor URL to avoid browser resubmission prompts.
        $this->redirectPostLoadToCanonicalEditor($postId);

        if ($postId === 0) {
            wp_die(esc_html_x("We couldn't identify this page. Return to Pages and open it again.", 'Page Builder', 'uncanny-automator'), 400);
        }

        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            wp_die(esc_html_x('This page could not be found. It may have been moved or deleted.', 'Page Builder', 'uncanny-automator'), 404);
        }

        try {
            $hasAccess = $this->allowedCapabilities->currentUserHasAllowedCapability();
        } catch (\Throwable $failure) {
            $this->terminateLoadFailure($failure);
        }
        if (!$hasAccess) {
            wp_die(esc_html_x("You don't have permission to edit this page with Uncanny Page Builder. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'), 403);
        }

        // The Page Builder capability grants access to the product, while
        // WordPress remains the authority for access to this specific post.
        if (!current_user_can('edit_post', $postId)) {
            wp_die(esc_html_x("You don't have permission to edit this page with Uncanny Page Builder. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'), 403);
        }

        nocache_headers();

        $isGlobalPart = $post->post_type === 'upb_global_part';
        try {
            $isOwnedPage = $this->supportsPostType->isSupported($post->post_type)
                && $this->repository->isOwnedPage($postId);
        } catch (\Throwable $failure) {
            $this->terminateLoadFailure($failure);
        }
        if (!$isGlobalPart && !$isOwnedPage) {
            wp_die(esc_html_x("This page isn't set up for Uncanny Page Builder. Return to Pages and choose a Page Builder page.", 'Page Builder', 'uncanny-automator'), 403);
        }

        try {
            // The route that paints the canvas owns acquisition. Windowed hosts
            // only inspect so a failed iframe load cannot leave an orphan lease.
            $ownership = $this->enterOwnership?->execute($postId, (int) get_current_user_id())
                ?? EditorOwnershipState::available();
        } catch (\Throwable $failure) {
            $this->terminateLoadFailure($failure);
        }

        if (
            $ownership->status() === EditorOwnershipStatus::Blocked
            && $this->lockDialogRenderer instanceof EditorLockDialogRenderer
        ) {
            $this->lockDialogRenderer->terminateBlocked(
                $ownership,
                $postId,
                $isGlobalPart ? 'reusable' : 'page',
                $editorMode,
            );
        }

        try {
            $this->addEditorLockBridgeData($ownership, $post, $isGlobalPart);

            // Fake the global query so wp_head/wp_footer and the canvas behave
            // as if this were a singular frontend request for the canvas post.
            global $wp_query;
            $GLOBALS['post'] = $post;
            $wp_query->queried_object    = $post;
            $wp_query->queried_object_id = $postId;
            $wp_query->is_singular       = true;
            $wp_query->is_single         = !$isGlobalPart;
            $wp_query->is_page           = !$isGlobalPart;
            setup_postdata($post);

            // Enqueue Magic Bridge assets (bridge JS/CSS + localized config).
            add_action('wp_enqueue_scripts', [$this->bridgeEnqueuer, 'enqueue']);

            /*
             * Every mode renders the standalone canvas document — composition
             * pages show theme-shell placeholder strips instead of the theme's
             * header/footer (the faithful render lives at the permalink). So the
             * asset boundary is unconditional: nothing theme- or plugin-owned
             * prints into the editor.
             */
            $this->assetAllowlist->registerPrintGuards();
            $this->canvasAdminBar->removeFromCanvas();
            self::removeDeprecatedEmojiStyleCallbacks();
            self::restrictFooterOutputToCoreScripts();

            // Tell the Automator MCP chat launcher that this admin page is a
            // canvas context: use the frontend parent selector, not #wpbody.
            add_filter('automator_mcp_launcher_parent_selector', static function (): string {
                return '#uncanny-pb-editor-layout';
            });
            add_filter('automator_mcp_in_allowed_pages', function ($allowed = null): bool {
                try {
                    return $allowed === true || $this->allowedCapabilities->currentUserHasAllowedCapability();
                } catch (\Throwable $failure) {
                    error_log('[Uncanny Page Builder] Canvas Agent availability failed (' . $failure::class . ')');
                    return false;
                }
            });

            // The admin canvas exits before wp-admin reaches admin_footer, so it
            // explicitly mounts Automator's MCP launcher in the canvas footer.
            add_action('uncanny_page_builder_canvas_footer', [
                McpAgentProvider::class,
                'renderAutomatorLauncher',
            ], 5);

            // Render the full canvas and exit — no admin chrome.
            $this->canvasRenderer->render();
            exit;
        } catch (\Throwable $failure) {
            // This request has changed global query and hook state. It cannot
            // safely continue into the normal admin renderer after a failure.
            $this->terminateLoadFailure($failure);
        }
    }

    private function terminateLoadFailure(\Throwable $failure): never
    {
        error_log(sprintf(
            '[Uncanny Page Builder] Admin canvas load failed (%s).',
            $failure::class,
        ));

        $message = 'Uncanny Page Builder could not open this editor. Return to Pages and try again.';
        try {
            $message = _x(
                'Uncanny Page Builder could not open this editor. Return to Pages and try again.',
                'Page Builder',
                'uncanny-automator',
            );
        } catch (\Throwable) {
            // The controlled response must remain available when translation
            // callbacks fail during error handling.
        }

        wp_die(esc_html($message), '', ['response' => 500]);
    }

    private function addEditorLockBridgeData(
        EditorOwnershipState $ownership,
        \WP_Post $post,
        bool $isGlobalPart,
    ): void {
        if (
            !$this->editorLockStore instanceof EditorLockStoreInterface
            || !$this->sourceGenerations instanceof SourceGenerationStoreInterface
        ) {
            return;
        }

        try {
            $enabled = $this->editorLockStore->isEnabled((int) $post->ID);
        } catch (\Throwable) {
            $enabled = true;
            $ownership = EditorOwnershipState::unavailable('feature check unavailable');
        }

        try {
            $sourceGeneration = $isGlobalPart
                ? $this->sourceGenerations->globalGeneration()
                : $this->sourceGenerations->pageGeneration((int) $post->ID);
        } catch (\Throwable) {
            $sourceGeneration = -1;
            $ownership = EditorOwnershipState::unavailable('source generation unavailable');
        }

        if ($enabled && $ownership->status() === EditorOwnershipStatus::Unavailable) {
            error_log(sprintf(
                '[Uncanny Page Builder] Editor lock entry unavailable target=%d actor=%d scope=%s reason=ownership_unavailable',
                (int) $post->ID,
                (int) get_current_user_id(),
                $isGlobalPart ? 'reusable' : 'page',
            ));
        }

        $token = $ownership->token()?->raw() ?? '';
        $state = $enabled && $ownership->status() !== EditorOwnershipStatus::Owned
            ? 'degraded'
            : 'owned';
        $exitUrl = $isGlobalPart
            ? AdminCanvasEditorWindowedGlobalPartPage::reusablesScreenUrl()
            : AdminCanvasEditorWindowedPage::pagesScreenUrl();

        add_filter(
            'uncanny_page_builder_bridge_data',
            static function ($data = null) use (
                $enabled,
                $state,
                $post,
                $token,
                $sourceGeneration,
                $exitUrl,
            ): array {
                $data = is_array($data) ? $data : [];
                $data['editorLock'] = [
                    'enabled'           => $enabled,
                    'state'             => $state,
                    'postId'            => (int) $post->ID,
                    'token'             => $token,
                    'sourceGeneration'  => $sourceGeneration,
                    'ajaxUrl'           => esc_url_raw(admin_url('admin-ajax.php')),
                    'releaseNonce'      => wp_create_nonce('update-post_' . (int) $post->ID),
                    'exitUrl'           => esc_url_raw($exitUrl),
                ];

                return $data;
            },
            10,
            2,
        );
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $bodyParams
     */
    private static function canvasIdFromRequest(array $queryParams, array $bodyParams): int
    {
        $queryPostId = absint($queryParams['canvas_id'] ?? 0);

        if ($queryPostId > 0) {
            return $queryPostId;
        }

        return absint($bodyParams['canvas_id'] ?? 0);
    }

    private function redirectPostLoadToCanonicalEditor(int $postId): void
    {
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if (!self::shouldRedirectPostLoad($requestMethod, $postId)) {
            return;
        }

        wp_safe_redirect(self::editorUrl($postId), 303, 'Uncanny Page Builder');
        exit;
    }

    private static function shouldRedirectPostLoad(string $requestMethod, int $postId): bool
    {
        return $requestMethod === 'POST' && $postId > 0;
    }

    private static function removeDeprecatedEmojiStyleCallbacks(): void
    {
        /*
         * The admin canvas exits from load-* and then renders a frontend-like
         * document with wp_head(). WordPress 6.4's replacement enqueue helpers
         * remove these deprecated callbacks from admin hooks when is_admin() is
         * true, but they do not remove the frontend hook callbacks we trigger
         * manually here.
         */
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
    }

    private static function restrictFooterOutputToCoreScripts(): void
    {
        /*
         * The canvas template calls wp_footer() so enqueued scripts print —
         * that is wp_footer's ONLY job inside the editor document. Plugins
         * legitimately echo markup there for the public site (cookie banners,
         * footer widgets, integration directories), but the editor is an
         * application, not a page: foreign markup after the canvas is always
         * pollution. Asset print guards already police scripts/styles; this
         * polices direct output. Public renders keep wp_footer untouched.
         */
        remove_all_actions('wp_footer');
        add_action('wp_footer', 'wp_print_footer_scripts', 20);
    }
}
