<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\WordPress\Admin;

use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\Canvas\AdoptPageUseCase;
use UncannyPageBuilder\Application\Canvas\ReturnPageToWordPressUseCase;
use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Controls\ControlStateService;
use UncannyPageBuilder\Application\Editor\EditorStateService;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\Publishing\PageLiveStateReaderInterface;
use UncannyPageBuilder\Application\Rendering\PublishedPageReaderInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshQueueInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Infrastructure\WordPress\BlockEditorButton;
use UncannyPageBuilder\Infrastructure\WordPress\PageEditorMetaBoxes;
use UncannyPageBuilder\Infrastructure\WordPress\PageOwnershipActions;
use UncannyPageBuilder\Infrastructure\WordPress\NativePageSave;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostId;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressCallbackBoundary;
use UncannyPageBuilder\Infrastructure\WordPress\WpOriginalPageContentStore;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\Exception\ParkedDraftNotLoadedException;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;

final class EditorEnvironmentProvider implements ServiceProviderInterface
{
    private const SHELL_MODE_NONCE_KEY = PageEditorMetaBoxes::SHELL_MODE_NONCE_KEY;
    private const SHELL_MODE_NONCE_ACTION = PageEditorMetaBoxes::SHELL_MODE_NONCE_ACTION;

    /** @var array<int, true> */
    private static array $nativeTrashLifecyclePages = [];

    public function register(Container $container): void
    {
        $container->factory(NativePageSave::class, static function (Container $c): NativePageSave {
            return new NativePageSave(
                $c->typed(PageSourceMutation::class),
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(SelectEditorPageSource::class),
            );
        });

        $container->factory(BlockEditorButton::class, static function (Container $c): BlockEditorButton {
            return new BlockEditorButton(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(AdoptPageUseCase::class),
                $c->typed(PageBuilderAvailabilityInterface::class),
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(PageEditorMetaBoxes::class, static function (Container $c): PageEditorMetaBoxes {
            return new PageEditorMetaBoxes(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(ShellModeService::class),
                $c->typed(EditorStateService::class),
                $c->typed(ControlStateService::class),
                $c->typed(PageLiveStateReaderInterface::class),
                $c->typed(PublishedPageReaderInterface::class),
                $c->typed(PermissionChecker::class),
                $c->typed(BindingRegistry::class),
                $c->typed(PageJavaScriptRuntimeService::class),
                $c->typed(ToolSettingsAccess::class),
                $c->typed(SupportsPostTypeUseCase::class),
                $c->typed(SelectEditorPageSource::class),
            );
        });

        $container->factory(PageOwnershipActions::class, static function (Container $c): PageOwnershipActions {
            return new PageOwnershipActions(
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(ReturnPageToWordPressUseCase::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        $sectionRepo      = $container->typed(DatabaseSectionRepository::class);
        $shellModeService = $container->typed(ShellModeService::class);
        $pageEditorMetaBoxes = $container->typed(PageEditorMetaBoxes::class);
        $workingCanvas = $container->typed(WorkingCanvasRefresherInterface::class);
        $workingCanvasRefreshQueue = $container->has(WorkingCanvasRefreshQueueInterface::class)
            ? $container->typed(WorkingCanvasRefreshQueueInterface::class)
            : null;
        $allowedCapabilities = $container->typed(GetPageBuilderAllowedCapabilities::class);
        $supportsPostType = $container->typed(SupportsPostTypeUseCase::class);
        $blockEditorButton = $container->typed(BlockEditorButton::class);
        $pageOwnershipActions = $container->typed(PageOwnershipActions::class);
        $pageSource = $container->has(PageSourceMutation::class)
            ? $container->typed(PageSourceMutation::class)
            : null;
        $pageStates = $container->has(PageStateRepositoryInterface::class)
            ? $container->typed(PageStateRepositoryInterface::class)
            : null;
        $pageSources = $container->has(SelectEditorPageSource::class)
            ? $container->typed(SelectEditorPageSource::class)
            : null;
        $nativePageSave = $container->has(NativePageSave::class)
            ? $container->typed(NativePageSave::class)
            : null;
        $failureReporter = $container->has(FailureReporterInterface::class)
            ? $container->typed(FailureReporterInterface::class)
            : null;
        $callbacks = new WordPressCallbackBoundary();

        add_action('enqueue_block_editor_assets', $callbacks->action('block_editor.enqueue', [$blockEditorButton, 'enqueue']));
        add_action('admin_post_' . BlockEditorButton::ACTION, [$blockEditorButton, 'open']);
        add_action('admin_post_' . PageOwnershipActions::ACTION, [$pageOwnershipActions, 'switchNow']);
        add_action('edit_form_after_title', [$blockEditorButton, 'renderClassicEditorButton']);
        add_filter('redirect_post_location', $callbacks->filter('block_editor.redirect', [$blockEditorButton, 'redirectClassicEditorSave']), 10, 2);
        add_filter('redirect_post_location', $callbacks->filter('page_ownership.redirect', [$pageOwnershipActions, 'redirectAfterSave']), 20, 2);

        // A stale WordPress editor can submit after Page Builder takes active
        // ownership. Protect public fields only while administrator intent
        // keeps Page Builder active for this post type.
        add_filter('wp_insert_post_data', static function ($data = null, $postarr = null) use ($sectionRepo, $supportsPostType): array {
            $data = is_array($data) ? $data : [];
            $postarr = is_array($postarr) ? $postarr : [];

            return self::protectOwnedPublicFields($data, $postarr, $sectionRepo, $supportsPostType);
        }, 10, 2);

        /*
         * Trash and restore call wp_update_post(), which also fires the normal
         * save_post hook. Track that native WordPress lifecycle window so
         * a visibility-only action never rebuilds the working Page Builder
         * canvas or enters its publication transaction.
         */
        add_action('wp_trash_post', static function ($postId = null): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null) {
                return;
            }
            self::$nativeTrashLifecyclePages[$postId] = true;
        }, 1);
        add_action('untrash_post', static function ($postId = null): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null) {
                return;
            }
            self::$nativeTrashLifecyclePages[$postId] = true;
        }, 1);
        add_action('trashed_post', static function ($postId = null): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null) {
                return;
            }
            unset(self::$nativeTrashLifecyclePages[$postId]);
        }, PHP_INT_MAX);
        add_action('untrashed_post', static function ($postId = null): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null) {
                return;
            }
            unset(self::$nativeTrashLifecyclePages[$postId]);
        }, PHP_INT_MAX);

        add_filter('use_block_editor_for_post', $callbacks->filter('block_editor.eligibility', static function ($useBlockEditor = null, $post = null) use ($sectionRepo, $supportsPostType): bool {
            if (
                $post instanceof \WP_Post
                && $supportsPostType->isEnabledByAdministrator($post->post_type)
                && $sectionRepo->isOwnedPage($post->ID)
            ) {
                return false;
            }
            return (bool) $useBlockEditor;
        }), 10, 2);

        // Third-party admin pages can dispatch this hook without a post object.
        add_action('add_meta_boxes', $callbacks->action('page_metaboxes.register', static function ($postType = null, $post = null) use ($pageEditorMetaBoxes, $sectionRepo, $supportsPostType): void {
            if (
                is_string($postType)
                && $post instanceof \WP_Post
                && $supportsPostType->isEnabledByAdministrator($postType)
                && (
                    $supportsPostType->isSupported($postType)
                    || $sectionRepo->isOwnedPage($post->ID)
                )
            ) {
                $pageEditorMetaBoxes->register($post);
            }
        }), 10, 2);

        /*
         * WordPress fires generic add_meta_boxes before the post-type-specific
         * hook. Schedule pruning on that later hook so boxes registered through
         * either lane are present before the allowlist is enforced.
         */
        add_action('add_meta_boxes', $callbacks->action('page_metaboxes.prune_schedule', static function ($postType = null, $post = null) use ($sectionRepo, $supportsPostType): void {
            if (!is_string($postType) || !$post instanceof \WP_Post) {
                return;
            }

            static $scheduledPostTypes = [];
            if (isset($scheduledPostTypes[$postType])) {
                return;
            }
            $scheduledPostTypes[$postType] = true;

            add_action('add_meta_boxes_' . $postType, static function ($dynamicPost = null) use ($postType, $sectionRepo, $supportsPostType): void {
                try {
                    if (
                        !$dynamicPost instanceof \WP_Post
                        || !$supportsPostType->isEnabledByAdministrator($postType)
                        || !$sectionRepo->isOwnedPage($dynamicPost->ID)
                    ) {
                        return;
                    }

                    self::pruneOwnedPostMetaBoxes($postType);
                    self::pruneForeignEditFormCallbacks();
                } catch (\Throwable $failure) {
                    error_log('[Uncanny Page Builder] Page metabox pruning failed (' . $failure::class . ')');
                }
            }, PHP_INT_MAX);
        }), PHP_INT_MAX, 2);

        add_action('admin_enqueue_scripts', $callbacks->action('page_editor.assets', static function ($hook = null) use ($sectionRepo, $supportsPostType): void {
            if (!is_string($hook) || ($hook !== 'post.php' && $hook !== 'post-new.php')) {
                return;
            }
            $post = get_post();
            if (
                !$post
                || !$supportsPostType->isSupported($post->post_type)
                || !$sectionRepo->isOwnedPage($post->ID)
            ) {
                return;
            }
            wp_enqueue_script('thickbox');
            wp_enqueue_style('thickbox');
            wp_enqueue_script(
                'upb-admin-codemirror',
                UNCANNY_PB_URL . 'assets/js/admin-codemirror.js',
                [],
                (string) filemtime(UNCANNY_PB_PATH . 'assets/js/admin-codemirror.js'),
                true,
            );
        }));

        add_action('edit_form_after_title', [$pageEditorMetaBoxes, 'renderActionsRow']);
        if ($nativePageSave instanceof NativePageSave) {
            add_action('admin_notices', [$nativePageSave, 'renderNotice']);
        }

        add_action('save_post', $callbacks->action('native_page.save', static function (
            $postId = null,
            $post = null
        ) use (
            $sectionRepo,
            $shellModeService,
            $workingCanvas,
            $workingCanvasRefreshQueue,
            $allowedCapabilities,
            $supportsPostType,
            $pageSource,
            $pageStates,
            $pageSources,
            $nativePageSave,
            $failureReporter,
        ): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null || !$post instanceof \WP_Post) {
                return;
            }

            self::handleSave(
                $postId,
                $post,
                $sectionRepo,
                $shellModeService,
                $allowedCapabilities,
                $workingCanvas,
                $supportsPostType,
                $pageSource,
                $pageStates,
                $pageSources,
                $nativePageSave,
                $workingCanvasRefreshQueue,
                $failureReporter,
            );
        }), 100, 2);
    }

    /**
     * Edit-form output hooks third parties commonly use to inject their own
     * editor buttons and banners into the post screen.
     *
     * @var string[]
     */
    private const EDIT_FORM_OUTPUT_HOOKS = [
        'edit_form_top',
        'edit_form_before_permalink',
        'edit_form_after_title',
        'edit_form_after_editor',
    ];

    /**
     * Keep only Page Builder's owned-post metabox surface.
     */
    private static function pruneOwnedPostMetaBoxes(string $postType): void
    {
        remove_post_type_support($postType, 'editor');

        global $wp_meta_boxes;
        $allowed = [
            'submitdiv',
            'upb_page_access',
            'upb_page_sections',
            'upb_page_layout',
            'upb_website_status',
            'upb_page_text_styles',
            'upb_page_colors',
            'upb_section_order',
            'upb_global_part_selector',
        ];
        if (!isset($wp_meta_boxes[$postType]) || !is_array($wp_meta_boxes[$postType])) {
            return;
        }

        foreach ($wp_meta_boxes[$postType] as $context => $priorities) {
            if (!is_array($priorities)) {
                continue;
            }
            foreach ($priorities as $boxes) {
                if (!is_array($boxes)) {
                    continue;
                }
                foreach (array_keys($boxes) as $boxId) {
                    if (!in_array($boxId, $allowed, true)) {
                        remove_meta_box($boxId, $postType, $context);
                    }
                }
            }
        }
    }

    /**
     * Remove every edit-form output callback that is not Page Builder's own
     * actions row, so foreign plugins cannot render into owned pages.
     */
    private static function pruneForeignEditFormCallbacks(): void
    {
        global $wp_filter;

        foreach (self::EDIT_FORM_OUTPUT_HOOKS as $hookName) {
            $hook = $wp_filter[$hookName] ?? null;
            if (!is_object($hook) || !isset($hook->callbacks) || !is_array($hook->callbacks)) {
                continue;
            }

            foreach ($hook->callbacks as $priority => $callbacks) {
                if (!is_array($callbacks)) {
                    continue;
                }

                foreach ($callbacks as $callback) {
                    $function = is_array($callback) ? ($callback['function'] ?? null) : null;
                    if ($function === null) {
                        continue;
                    }

                    if (is_array($function) && ($function[0] ?? null) instanceof PageEditorMetaBoxes) {
                        continue;
                    }

                    remove_filter($hookName, $function, $priority);
                }
            }
        }
    }

    private static function handleSave(
        int $postId,
        \WP_Post $post,
        DatabaseSectionRepository $sectionRepo,
        ShellModeService $shellModeService,
        GetPageBuilderAllowedCapabilities $allowedCapabilities,
        ?WorkingCanvasRefresherInterface $workingCanvas = null,
        ?SupportsPostTypeUseCase $supportsPostType = null,
        ?PageSourceMutation $pageSource = null,
        ?PageStateRepositoryInterface $pageStates = null,
        ?SelectEditorPageSource $pageSources = null,
        ?NativePageSave $nativePageSave = null,
        ?WorkingCanvasRefreshQueueInterface $workingCanvasRefreshQueue = null,
        ?FailureReporterInterface $failureReporter = null,
    ): void {
        $supportsPostType ??= new SupportsPostTypeUseCase();

        if (WpOriginalPageContentStore::isWriting()) {
            return;
        }
        if (isset(self::$nativeTrashLifecyclePages[$postId])) {
            unset(self::$nativeTrashLifecyclePages[$postId]);
            return;
        }
        if (
            !$supportsPostType->isSupported($post->post_type)
            || !$sectionRepo->isOwnedPage($postId)
        ) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($postId)) {
            return;
        }
        if (!$allowedCapabilities->currentUserHasAllowedCapability()) {
            return;
        }

        try {
            $hasShellNonce = self::hasValidPostedNonce(self::SHELL_MODE_NONCE_KEY, self::SHELL_MODE_NONCE_ACTION);
            $isSwitchingToWordPress = self::hasPostedValue(PageOwnershipActions::SWITCH_FIELD, '1')
                && self::hasValidPostedNonce(PageOwnershipActions::NONCE_FIELD, PageOwnershipActions::NONCE_ACTION);
            if ($hasShellNonce) {
                $modeValue = sanitize_text_field((string) ($_POST['uncanny_page_builder_shell_mode'] ?? ''));
                $mode = ShellMode::tryFrom($modeValue);

                if ($mode !== null) {
                    $saveShellMode = fn() => $shellModeService->setForPage($postId, $mode);
                    $currentMode = $shellModeService->resolveForPage($postId);
                    if ($currentMode->mode === $mode && $currentMode->isExplicit) {
                        // The native Update form submitted the already-persisted
                        // explicit mode; do not create a new draft generation.
                    } elseif ($nativePageSave instanceof NativePageSave) {
                        $expectedGeneration = $nativePageSave->postedGeneration();
                        if ($expectedGeneration === null) {
                            $nativePageSave->reject(
                                $postId,
                                _x('Page layout was not saved because the page draft identity is missing.', 'Page Builder', 'uncanny-automator'),
                            );
                        } else {
                            $nativePageSave->stage($postId, $expectedGeneration, $saveShellMode);
                        }
                    } elseif (
                        $pageSource instanceof PageSourceMutation
                        && $pageStates instanceof PageStateRepositoryInterface
                    ) {
                        try {
                            $pageSource->runAsHumanSave(
                                $postId,
                                $saveShellMode,
                                function () use ($pageStates, $postId): void {
                                    $pageStates->saveDraftResumePolicy(
                                        $postId,
                                        DraftResumePolicy::Parked,
                                    );
                                },
                                static function () use ($pageSources, $postId): void {
                                    if (
                                        $pageSources instanceof SelectEditorPageSource
                                        && $pageSources->forPage($postId)->loadedSource() !== 'working'
                                    ) {
                                        throw new ParkedDraftNotLoadedException();
                                    }
                                },
                            );
                        } catch (ParkedDraftNotLoadedException) {
                            // The corresponding metabox is read-only while a newer
                            // parked draft is hidden. A stale browser form cannot
                            // write through that source boundary.
                        }
                    } else {
                        $saveShellMode();
                    }
                } elseif ($nativePageSave instanceof NativePageSave) {
                    $nativePageSave->reject(
                        $postId,
                        _x('Page Builder settings were not saved because the page layout choice was invalid.', 'Page Builder', 'uncanny-automator'),
                    );
                }
            }

            if ($nativePageSave instanceof NativePageSave && !$nativePageSave->commit($postId)) {
                return;
            }

            // The ownership transition restores post_content after this normal
            // WordPress save. Do not replace legacy or saved WordPress content
            // with a fresh Page Builder artifact immediately before restoration.
            if ($isSwitchingToWordPress) {
                return;
            }

            // Run after page-owned metaboxes save so the working canvas sees the
            // newest shell, design override, and header/footer selection. This
            // refresh never creates or selects public output.
            if ($workingCanvas instanceof WorkingCanvasRefresherInterface) {
                self::refreshWorkingCanvas($postId, $workingCanvas, $workingCanvasRefreshQueue, $failureReporter);
            }
        } catch (\Throwable $failure) {
            // WordPress completed its own save before this observer ran. A
            // Page Builder failure must not turn that save into a fatal
            // response for the shared save_post request.
            error_log('[Uncanny Page Builder] Native page save observer failed (' . $failure::class . ')');
            self::rejectNativeSaveAfterObserverFailure($postId, $nativePageSave);
        }
    }

    private static function rejectNativeSaveAfterObserverFailure(
        int $postId,
        ?NativePageSave $nativePageSave,
    ): void {
        if (!$nativePageSave instanceof NativePageSave) {
            return;
        }

        $message = 'Page Builder settings were not saved because an unexpected error occurred. Reload the page and try again.';
        try {
            $message = _x(
                'Page Builder settings were not saved because an unexpected error occurred. Reload the page and try again.',
                'Page Builder',
                'uncanny-automator',
            );
        } catch (\Throwable) {
            // Keep the fixed fallback message for the save notice.
        }

        try {
            $nativePageSave->reject($postId, $message);
            $nativePageSave->commit($postId);
        } catch (\Throwable $noticeFailure) {
            error_log('[Uncanny Page Builder] Native page save failure notice failed (' . $noticeFailure::class . ')');
        }
    }

    private static function refreshWorkingCanvas(
        int $postId,
        WorkingCanvasRefresherInterface $workingCanvas,
        ?WorkingCanvasRefreshQueueInterface $workingCanvasRefreshQueue,
        ?FailureReporterInterface $failureReporter,
    ): void {
        try {
            $workingCanvas->refresh($postId);
        } catch (\Throwable $failure) {
            // WordPress has already saved the page. The working canvas is
            // derived state, so record the failure and queue a safe retry
            // instead of making the shared save_post request fatal.
            self::reportWorkingCanvasFailure($postId, 'native_save.working_canvas.refresh', $failure, $failureReporter);

            if (!$workingCanvasRefreshQueue instanceof WorkingCanvasRefreshQueueInterface) {
                return;
            }

            try {
                $workingCanvasRefreshQueue->enqueuePages([$postId]);
            } catch (\Throwable $queueFailure) {
                self::reportWorkingCanvasFailure($postId, 'native_save.working_canvas.enqueue', $queueFailure, $failureReporter);
            }
        }
    }

    private static function reportWorkingCanvasFailure(
        int $postId,
        string $step,
        \Throwable $failure,
        ?FailureReporterInterface $failureReporter,
    ): void {
        try {
            if ($failureReporter instanceof FailureReporterInterface) {
                $failureReporter->report('native page save', $postId, $step, $failure);
                return;
            }
        } catch (\Throwable) {
            // A reporting failure cannot escape the shared save_post request.
        }

        error_log(sprintf(
            '[Uncanny Page Builder] Native save for page %d completed, but %s failed (%s).',
            $postId,
            $step,
            $failure::class,
        ));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    private static function protectOwnedPublicFields(
        array $data,
        array $postarr,
        DatabaseSectionRepository $sectionRepo,
        ?SupportsPostTypeUseCase $supportsPostType = null,
    ): array {
        if (WpOriginalPageContentStore::isWriting()) {
            return $data;
        }

        $postId = (int) ($postarr['ID'] ?? 0);
        $postType = (string) ($data['post_type'] ?? '');
        if ($postType === '' && $postId > 0 && function_exists('get_post_type')) {
            $resolvedPostType = get_post_type($postId);
            $postType = is_string($resolvedPostType) ? $resolvedPostType : '';
        }
        $supportsPostType ??= new SupportsPostTypeUseCase();
        if (
            $postId <= 0
            || !$supportsPostType->isEnabledByAdministrator($postType)
            || !$sectionRepo->isOwnedPage($postId)
        ) {
            return $data;
        }

        // Ordinary WordPress saves may edit working settings, but they cannot
        // move Page Builder-owned public content or identity fields.
        foreach (['post_content', 'post_title', 'post_name'] as $publicField) {
            $stored = get_post_field($publicField, $postId, 'raw');
            if (is_string($stored)) {
                $data[$publicField] = $stored;
            }
        }

        /*
         * WordPress owns trash and restore bookkeeping. Let transitions to or
         * from trash pass through this filter; freezing them leaves comments
         * and trash metadata changed while the page itself remains live.
         * All other status changes stay on Page Builder's explicit lifecycle
         * controls and are restored to their persisted value here.
         */
        $storedStatus = get_post_field('post_status', $postId, 'raw');
        $requestedStatus = (string) ($data['post_status'] ?? '');
        $isTrashLifecycle = is_string($storedStatus)
            && $storedStatus !== $requestedStatus
            && ($storedStatus === 'trash' || $requestedStatus === 'trash');
        if (is_string($storedStatus) && !$isTrashLifecycle) {
            $data['post_status'] = $storedStatus;
        }

        return $data;
    }

    private static function hasValidPostedNonce(string $key, string $action): bool
    {
        if (!isset($_POST[$key])) {
            return false;
        }

        return (bool) wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$key])), $action);
    }

    private static function hasPostedValue(string $key, string $expected): bool
    {
        $value = $_POST[$key] ?? null;

        return is_scalar($value) && wp_unslash((string) $value) === $expected;
    }
}
