<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\Access\PageBuilderDisabledException;
use UncannyPageBuilder\Application\Canvas\AdoptPageUseCase;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Adds Page Builder entry points to WordPress editors and adopts the page.
 *
 * Gutenberg needs a client-side toolbar mount. Classic Editor can use the
 * native post form, which lets WordPress save pending title/content changes
 * before this class redirects into the Page Builder editor.
 */
final class BlockEditorButton
{
    public const ACTION = 'uncanny_page_builder_adopt_page';
    public const CLASSIC_OPEN_FIELD = 'uncanny_page_builder_open_after_save';
    public const CLASSIC_NONCE_FIELD = 'uncanny_page_builder_classic_nonce';
    public const CLASSIC_BUTTON_ID = 'uncanny-page-builder-classic-editor-button';

    private const SCRIPT_HANDLE = 'uncanny-page-builder-block-editor-button';

    public function __construct(
        private readonly SectionRepositoryInterface $sections,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly AdoptPageUseCase $adoptPage,
        private readonly PageBuilderAvailabilityInterface $availability,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    // Section: Gutenberg entry point

    public function enqueue(): void
    {
        if (!$this->availability->allowsNewPages()) {
            return;
        }

        $post = get_post();
        if (!$this->isEditablePage($post) || $this->sections->isOwnedPage((int) $post->ID)) {
            return;
        }

        $this->enqueueScript((int) $post->ID, 'block');
    }

    /**
     * Load the shared WordPress component entry point for one editor lane.
     */
    private function enqueueScript(int $postId, string $editorMode): void
    {
        $assetPath = UNCANNY_PB_PATH . 'assets/js/block-editor-button.js';
        $version = is_file($assetPath)
            ? (string) filemtime($assetPath)
            : UNCANNY_PB_VERSION;

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            UNCANNY_PB_URL . 'assets/js/block-editor-button.js',
            ['wp-components', 'wp-data', 'wp-dom-ready', 'wp-element'],
            $version,
            true,
        );

        wp_localize_script(self::SCRIPT_HANDLE, 'uncannyPageBuilderBlockEditor', [
            'actionUrl'          => admin_url('admin-post.php'),
            'action'             => self::ACTION,
            'nonce'              => wp_create_nonce(self::ACTION),
            'nonceField'         => '_wpnonce',
            'postId'             => $postId,
            'editorMode'         => $editorMode,
            'label'              => _x('Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
            'busyLabel'          => _x('Opening...', 'Page Builder', 'uncanny-automator'),
            'confirmPrimaryMessage' => _x('Editing with Uncanny Page Builder replaces the current WordPress editor content with the saved Uncanny Page Builder layout.', 'Page Builder', 'uncanny-automator'),
            'confirmPrimaryEmphasis' => _x('replaces', 'Page Builder', 'uncanny-automator'),
            'confirmSecondaryMessage' => _x('The existing WordPress editor content is preserved as a separate version and can be restored later.', 'Page Builder', 'uncanny-automator'),
            'confirmSecondaryEmphasis' => _x('preserved', 'Page Builder', 'uncanny-automator'),
            'confirmAccessMessage' => _x('Editing this page with Uncanny Page Builder is available to administrators only because Uncanny Agent requires administrator permissions.', 'Page Builder', 'uncanny-automator'),
            'confirmAccessEmphasis' => _x('administrators only', 'Page Builder', 'uncanny-automator'),
            'confirmButtonLabel' => _x('Edit with Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
            'cancelButtonLabel'  => _x('Cancel', 'Page Builder', 'uncanny-automator'),
            'dialogErrorMessage' => _x('The WordPress confirmation dialog is unavailable. Reload the editor and try again.', 'Page Builder', 'uncanny-automator'),
            'saveErrorMessage'   => _x('Save this page before opening Uncanny Page Builder.', 'Page Builder', 'uncanny-automator'),
        ]);
    }

    // Section: Classic Editor entry point

    public function renderClassicEditorButton($post = null): void
    {
        if (!$post instanceof \WP_Post) {
            return;
        }

        /*
         * Gutenberg fires edit_form_after_title while scraping hidden fields
         * from Classic Editor integrations. Do not let that compatibility pass
         * overwrite the already-localized block-editor runtime configuration.
         */
        if (!$this->availability->allowsNewPages() || use_block_editor_for_post($post)) {
            return;
        }

        if (!$this->isEditablePage($post) || $this->sections->isOwnedPage((int) $post->ID)) {
            return;
        }

        $openField = self::CLASSIC_OPEN_FIELD;
        $nonceField = self::CLASSIC_NONCE_FIELD;

        // Classic Editor does not load Gutenberg presentation assets itself.
        // Enqueue both halves so ConfirmDialog retains WordPress behavior and
        // appearance while the existing post form remains the save boundary.
        wp_enqueue_style('wp-components');
        $this->enqueueScript((int) $post->ID, 'classic');

        include __DIR__ . '/../../Presentation/Pages/classic-editor-button.php';
    }

    /**
     * Continue to Page Builder only after Classic Editor finishes its normal
     * save request. Invalid or forged requests fall back to WordPress's own
     * redirect without changing Page Builder ownership.
     */
    public function redirectClassicEditorSave($location = null, $postId = null): string
    {
        $location = is_string($location) ? $location : '';
        $postId = WordPressPostId::fromMixed($postId);
        if ($postId === null) {
            return $location;
        }

        $requested = $_POST[self::CLASSIC_OPEN_FIELD] ?? null;
        if (!is_scalar($requested) || wp_unslash((string) $requested) !== '1') {
            return $location;
        }

        $postedNonce = $_POST[self::CLASSIC_NONCE_FIELD] ?? null;
        $nonce = is_scalar($postedNonce)
            ? wp_unslash((string) $postedNonce)
            : '';

        if ($nonce === '' || wp_verify_nonce($nonce, self::ACTION) === false) {
            return $location;
        }

        $post = $postId > 0 ? get_post($postId) : null;
        if (!$this->availability->allowsNewPages() || !$this->isEditablePage($post)) {
            return $location;
        }

        return $this->adoptAndGetEditorUrl($postId);
    }

    // Section: Shared adoption action

    public function open(): void
    {
        check_admin_referer(self::ACTION);

        wp_safe_redirect(
            $this->resolveOpenRedirect($_POST),
            303,
            'Uncanny Page Builder',
        );
        exit;
    }

    /**
     * Resolve the validated redirect target for the Gutenberg admin-post lane.
     *
     * @param array<string, mixed> $request
     */
    public function resolveOpenRedirect(array $request): string
    {
        $postedPageId = $request['post_id'] ?? null;
        $postId = is_scalar($postedPageId)
            ? absint(wp_unslash((string) $postedPageId))
            : 0;
        $post = $postId > 0 ? get_post($postId) : null;

        if (
            !$post instanceof \WP_Post
            || !$this->supportsPostType->isSupported($post->post_type)
        ) {
            wp_die(
                esc_html_x('Select a valid page to open in Uncanny Page Builder.', 'Page Builder', 'uncanny-automator'),
                esc_html_x('Page unavailable', 'Page Builder', 'uncanny-automator'),
                ['response' => 400],
            );
        }

        if (!$this->isEditablePage($post)) {
            wp_die(
                esc_html_x('You do not have permission to open this page in Uncanny Page Builder. Ask a site administrator for access.', 'Page Builder', 'uncanny-automator'),
                esc_html_x('Permission denied', 'Page Builder', 'uncanny-automator'),
                ['response' => 403],
            );
        }

        return $this->adoptAndGetEditorUrl($postId);
    }

    private function adoptAndGetEditorUrl(int $postId): string
    {
        try {
            $adopted = ($this->adoptPage)($postId);
        } catch (PageBuilderDisabledException) {
            wp_die(
                esc_html_x(
                    'Uncanny Page Builder is disabled. Enable it in Automator settings to create a new Page Builder page.',
                    'Page Builder',
                    'uncanny-automator',
                ),
                esc_html_x('Uncanny Page Builder is disabled', 'Page Builder', 'uncanny-automator'),
                ['response' => 403],
            );
        } catch (\Throwable $error) {
            do_action('uncanny_page_builder_page_adoption_failed', $postId, $error);
            wp_die(
                esc_html_x(
                    'Page Builder could not safely take over this page. Review the page before trying again.',
                    'Page Builder',
                    'uncanny-automator',
                ),
                esc_html_x('The page could not be switched', 'Page Builder', 'uncanny-automator'),
                ['response' => 500, 'back_link' => true],
            );
        }

        if ($adopted) {
            do_action('uncanny_page_builder_page_adopted', $postId);
        }

        return AdminCanvasEditorWindowedPage::editorUrl($postId);
    }

    private function isEditablePage(mixed $post): bool
    {
        return $post instanceof \WP_Post
            && $post->ID > 0
            && $this->supportsPostType->isSupported($post->post_type)
            && $this->allowedCapabilities->currentUserHasAllowedCapability()
            && current_user_can('edit_post', $post->ID);
    }
}
