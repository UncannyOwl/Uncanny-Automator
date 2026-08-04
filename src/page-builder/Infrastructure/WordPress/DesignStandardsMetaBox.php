<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\GetAvailableFontFamilies;
use UncannyPageBuilder\Domain\DesignStandards\BootstrapTokenProfile;
use UncannyPageBuilder\Domain\DesignStandards\PageDesignOverrides;
use UncannyPageBuilder\Domain\Exception\ParkedDraftNotLoadedException;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Presentation\Settings\DesignSettingsFields;

final class DesignStandardsMetaBox
{
    private const NONCE_KEY    = 'upb_design_standards_page_overrides_nonce';
    private const NONCE_ACTION = 'upb_design_standards_save_page_overrides';
    private const NOTICE_TRANSIENT_PREFIX = 'upb_design_standards_page_overrides_notice_';

    public function __construct(
        private readonly DesignStandardsService $designStandardsService,
        private readonly SectionRepositoryInterface $sectionRepo,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly ?GetAvailableFontFamilies $availableFontFamilies = null,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
        private readonly ?PageSourceMutation $pageSource = null,
        private readonly ?PageStateRepositoryInterface $pageStates = null,
        private readonly ?SelectEditorPageSource $pageSources = null,
        private readonly ?NativePageSave $nativePageSave = null,
    ) {}

    public function register(\WP_Post $post): void
    {
        if (
            !$this->supportsPostType->isSupported($post->post_type)
            || !$this->sectionRepo->isOwnedPage($post->ID)
            || !$this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            return;
        }

        add_action('admin_notices', [$this, 'renderSaveNotice']);
        $this->enqueueSettingsShellAssets();

        // Two scoped mirrors of the Brand styles sections instead of one
        // catch-all box: clearer interface, less information at once.
        add_meta_box(
            'upb_page_text_styles',
            _x('Text styles', 'Page Builder', 'uncanny-automator'),
            [$this, 'renderTextStyles'],
            $post->post_type,
            'normal',
            'low'
        );

        add_meta_box(
            'upb_page_colors',
            _x('Colors', 'Page Builder', 'uncanny-automator'),
            [$this, 'renderColors'],
            $post->post_type,
            'normal',
            'low'
        );
    }

    public function renderTextStyles(\WP_Post $post): void
    {
        [
            'typographyRoles' => $typographyRoles,
            'typographyDefaults' => $typographyDefaults,
            'roleDefinitions' => $roleDefinitions,
            'fontFamilyCatalog' => $fontFamilyCatalog,
            'linkFields' => $linkFields,
            'lockedTypographyKeys' => $lockedTypographyKeys,
            'lockedTokenKeys' => $lockedTokenKeys,
            'nonceKey' => $nonceKey,
            'nonceValue' => $nonceValue,
        ] = $this->pageScopeViewData($post->ID);

        include __DIR__ . '/../../Presentation/DesignStandards/page-text-styles.php';
    }

    public function renderColors(\WP_Post $post): void
    {
        [
            'tokenGroups' => $tokenGroups,
            'lockedTokenKeys' => $lockedTokenKeys,
            'nonceKey' => $nonceKey,
            'nonceValue' => $nonceValue,
        ] = $this->pageScopeViewData($post->ID);

        include __DIR__ . '/../../Presentation/DesignStandards/page-colors.php';
    }

    /**
     * Shared Brand-styles partials view data, scoped to this page: values are
     * the sparse overrides, defaults the site-effective values they inherit.
     *
     * Both metaboxes embed the same nonce hidden field; the duplicate names
     * carry identical values, so either box can save alone when the other is
     * hidden via Screen Options.
     *
     * @return array<string, mixed>
     */
    private function pageScopeViewData(int $postId): array
    {
        $sitewide    = $this->designStandardsService->resolve();
        $overrides   = $this->designStandardsService->loadPageOverrides($postId);
        $auditResult = $this->designStandardsService->resolveForPageWithAudit($postId);
        $lockedKeys  = $auditResult->lockedKeys();

        $sitewideTokens = $sitewide->tokens()->toArray();
        $overrideTokens = $overrides->tokens();

        return [
            'typographyRoles' => $overrides->typography()->toRoleArray(),
            'typographyDefaults' => $sitewide->typography()->toRoleArray(),
            'roleDefinitions' => DesignSettingsFields::typographyRoleDefinitions(),
            'fontFamilyCatalog' => $this->availableFontFamilies?->catalog() ?? [],
            'linkFields' => DesignSettingsFields::linkFieldsForOverrides($overrideTokens, $sitewideTokens),
            'tokenGroups' => DesignSettingsFields::visibleTokenGroupsForOverrides($overrideTokens, $sitewideTokens),
            'lockedTypographyKeys' => is_array($lockedKeys['typography'] ?? null) ? $lockedKeys['typography'] : [],
            'lockedTokenKeys' => is_array($lockedKeys['tokens'] ?? null) ? $lockedKeys['tokens'] : [],
            'nonceKey' => self::nonceKey(),
            'nonceValue' => wp_create_nonce(self::nonceActionForPage($postId)),
        ];
    }

    /**
     * The metabox renders the Brand-styles partials, so it needs the color
     * picker on the page edit screen. Guarded so unit tests without a
     * WordPress runtime can call register() safely.
     *
     * The settings page styles those partials with Automator's components,
     * which this screen does not load, so the metabox carries its own scoped
     * layout for the same fields.
     */
    private function enqueueSettingsShellAssets(): void
    {
        if (!\function_exists('wp_enqueue_style') || !\defined('UNCANNY_PB_URL') || !\defined('UNCANNY_PB_PATH')) {
            return;
        }

        wp_enqueue_style('wp-color-picker');

        $stylePath = 'assets/css/admin-page-style-overrides.css';
        wp_enqueue_style(
            'uncanny-page-builder-admin-page-style-overrides',
            UNCANNY_PB_URL . $stylePath,
            ['wp-color-picker'],
            (string) filemtime(UNCANNY_PB_PATH . $stylePath),
        );

        if (\function_exists('wp_enqueue_script')) {
            wp_enqueue_script('wp-color-picker');
        }
    }

    public function save(int $postId, \WP_Post $post): void
    {
        if (
            !$this->supportsPostType->isSupported($post->post_type)
            || !$this->sectionRepo->isOwnedPage($postId)
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($postId)) {
            return;
        }

        if (
            !isset($_POST[self::nonceKey()])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::nonceKey()])),
                self::nonceActionForPage($postId)
            )
        ) {
            return;
        }

        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            return;
        }

        $lockedKeys = $this->designStandardsService->resolve()->lockedKeys();
        $tokens = $this->sanitizeTokens($_POST['upb_ds_token'] ?? [], $lockedKeys['tokens'] ?? []);
        $typographyRoles = TypographyRolesPostPayload::sparseOverrides(
            wp_unslash($_POST['upb_ds_typography']['roles'] ?? null),
        );

        try {
            $overrides = PageDesignOverrides::fromArray([
                'tokens' => $tokens,
                'typography' => ['roles' => $typographyRoles],
            ]);
        } catch (\InvalidArgumentException $exception) {
            // Domain validation rejected a submitted value (e.g. unsafe CSS).
            // Reject the shared native transaction so another valid metabox
            // cannot be committed while this page-owned value is discarded.
            if ($this->nativePageSave instanceof NativePageSave) {
                $this->nativePageSave->reject(
                    $postId,
                    _x('Page Builder settings were not saved because a page style value was invalid.', 'Page Builder', 'uncanny-automator'),
                );
            } else {
                $this->recordInvalidOverrideNotice($postId, $exception->getMessage());
            }
            return;
        }

        if ($this->designStandardsService->loadPageOverrides($postId)->toArray() === $overrides->toArray()) {
            delete_transient($this->noticeTransientKey($postId));
            return;
        }

        $save = fn() => $this->designStandardsService->savePageOverrides($postId, $overrides);
        if ($this->nativePageSave instanceof NativePageSave) {
            $expectedGeneration = $this->nativePageSave->postedGeneration();
            if ($expectedGeneration === null) {
                $this->nativePageSave->reject(
                    $postId,
                    _x('Page style overrides were not saved because the page draft identity is missing.', 'Page Builder', 'uncanny-automator'),
                );
                return;
            }
            $this->nativePageSave->stage(
                $postId,
                $expectedGeneration,
                function () use ($postId, $save): void {
                    $result = $save();
                    $this->recordSaveAuditNotice($postId, $result->rejectedKeys(), $result->lockedKeys());
                },
            );
            return;
        }

        try {
            $result = $this->pageSource instanceof PageSourceMutation
                && $this->pageStates instanceof PageStateRepositoryInterface
                ? $this->pageSource->runAsHumanSave(
                    $postId,
                    $save,
                    function () use ($postId): void {
                        $this->pageStates?->saveDraftResumePolicy(
                            $postId,
                            DraftResumePolicy::Parked,
                        );
                    },
                    fn() => $this->assertVisibleSourceCanBeSaved($postId),
                )
                : $save();
        } catch (ParkedDraftNotLoadedException) {
            $this->recordInvalidOverrideNotice(
                $postId,
                _x(
                    'load the newer saved draft in the Page Builder editor before changing page styles',
                    'Page Builder',
                    'uncanny-automator',
                ),
            );
            return;
        }

        $this->recordSaveAuditNotice($postId, $result->rejectedKeys(), $result->lockedKeys());
    }

    public function renderSaveNotice(): void
    {
        $postId = absint(sanitize_text_field(wp_unslash($_GET['post'] ?? '0')));
        if ($postId <= 0) {
            return;
        }

        $transientKey = $this->noticeTransientKey($postId);
        $notice = get_transient($transientKey);
        if (!is_array($notice)) {
            return;
        }

        delete_transient($transientKey);

        $message = is_string($notice['message'] ?? null) ? $notice['message'] : '';
        if ($message === '') {
            return;
        }

        // A stale rejection is a lost write, not advice — render it as an
        // error so it is not read as a side note to core's "Page updated."
        $noticeClass = ($notice['type'] ?? '') === 'error' ? 'notice-error' : 'notice-warning';

        echo '<div class="notice ' . $noticeClass . ' is-dismissible">';
        echo '<p>' . esc_html($message) . '</p>';

        $keys = $this->noticeKeys($notice);
        if ($keys !== []) {
            echo '<p><code>' . esc_html(implode(', ', $keys)) . '</code></p>';
        }

        echo '</div>';
    }

    // ── Form contract helpers ───────────────────────────

    public static function nonceKey(): string
    {
        return self::NONCE_KEY;
    }

    public static function nonceActionForPage(int $postId): string
    {
        return self::NONCE_ACTION . '_' . $postId;
    }

    private function recordInvalidOverrideNotice(int $postId, string $reason): void
    {
        set_transient($this->noticeTransientKey($postId), [
            'type' => 'error',
            'message' => sprintf(
                /* translators: %s: validation error message. */
                _x('Page style overrides were not saved: %s', 'Page Builder', 'uncanny-automator'),
                $reason,
            ),
        ], 60);
    }

    /**
     * Save notices are emitted after WordPress redirects back to the edit page.
     * The service is intentionally audit-first: rejected and locked keys are not
     * persisted, and this infrastructure layer turns that audit into feedback.
     *
     * @param array<string, string[]> $rejectedKeys
     * @param array<string, string[]> $lockedKeys
     */
    private function recordSaveAuditNotice(int $postId, array $rejectedKeys, array $lockedKeys): void
    {
        if ($this->allAuditBucketsEmpty($rejectedKeys) && $this->allAuditBucketsEmpty($lockedKeys)) {
            delete_transient($this->noticeTransientKey($postId));
            return;
        }

        set_transient($this->noticeTransientKey($postId), [
            'message' => _x(
                'Some page style changes were not saved. Protected site settings keep their default values, and unknown settings were ignored.',
                'Page Builder',
                'uncanny-automator',
            ),
            'rejected_keys' => $rejectedKeys,
            'locked_keys' => $lockedKeys,
        ], 60);
    }

    /** @param array<string, string[]> $audit */
    private function allAuditBucketsEmpty(array $audit): bool
    {
        foreach ($audit as $keys) {
            if ($keys !== []) {
                return false;
            }
        }

        return true;
    }

    private function noticeTransientKey(int $postId): string
    {
        return self::NOTICE_TRANSIENT_PREFIX . $postId . '_' . (int) get_current_user_id();
    }

    private function assertVisibleSourceCanBeSaved(int $postId): void
    {
        if (
            $this->pageSources instanceof SelectEditorPageSource
            && $this->pageSources->forPage($postId)->loadedSource() !== 'working'
        ) {
            throw new ParkedDraftNotLoadedException();
        }
    }

    /**
     * @param array<string, mixed> $notice
     * @return string[]
     */
    private function noticeKeys(array $notice): array
    {
        $keys = [];

        foreach (['locked_keys', 'rejected_keys'] as $bucketName) {
            $bucket = $notice[$bucketName] ?? [];
            if (!is_array($bucket)) {
                continue;
            }

            foreach ($bucket['tokens'] ?? [] as $key) {
                if (is_string($key) && $key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    // ── Sanitize helpers ────────────────────────────────

    /**
     * @param string[] $lockedTokenKeys
     * @return array<string, string>
     */
    private function sanitizeTokens(mixed $raw, array $lockedTokenKeys = []): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $colorKeys = BootstrapTokenProfile::colorTokenKeys();
        $rgbDerived = BootstrapTokenProfile::rgbDerivedTokens();
        $clean = [];

        foreach ($raw as $key => $value) {
            $key   = sanitize_text_field((string) $key);
            $value = trim(sanitize_text_field((string) $value));

            if ($value === '') {
                continue;
            }

            if (in_array($key, $colorKeys, true)) {
                $hex = sanitize_hex_color($value);
                $clean[$key] = ($hex !== null && $hex !== '') ? $hex : $value;
            } else {
                $clean[$key] = $value;
            }
        }

        // Auto-compute RGB triplets.
        foreach ($rgbDerived as $rgbKey => $parentKey) {
            if (isset($clean[$parentKey]) && !in_array($parentKey, $lockedTokenKeys, true)) {
                $rgb = BootstrapTokenProfile::hexToRgb($clean[$parentKey]);
                if ($rgb !== null) {
                    $clean[$rgbKey] = $rgb;
                }
            }
        }

        return $clean;
    }
}
