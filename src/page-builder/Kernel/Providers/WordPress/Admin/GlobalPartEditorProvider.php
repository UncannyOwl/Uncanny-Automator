<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\WordPress\Admin;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\Filesystem\LocalFilesystemPortInterface;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Application\Reusable\CreateReusableUseCase;
use UncannyPageBuilder\Application\SourcePackage\ReusableSourcePackageService;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Infrastructure\WordPress\AdminCanvasEditorWindowedGlobalPartPage;
use UncannyPageBuilder\Infrastructure\WordPress\CssSanitizationGate;
use UncannyPageBuilder\Infrastructure\WordPress\GlobalPartDeletionCleanup;
use UncannyPageBuilder\Infrastructure\WordPress\GlobalPartMetaBox;
use UncannyPageBuilder\Infrastructure\WordPress\ReusableFactory;
use UncannyPageBuilder\Infrastructure\WordPress\SourcePackageUploadReader;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostId;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressCallbackBoundary;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseGlobalPartRepository;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;

final class GlobalPartEditorProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->factory(GlobalPartMetaBox::class, static function (Container $c): GlobalPartMetaBox {
            return new GlobalPartMetaBox(
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(GlobalSourceMutation::class),
                $c->typed(FailureReporterInterface::class),
            );
        });

        $container->factory(GlobalPartDeletionCleanup::class, static function (Container $c): GlobalPartDeletionCleanup {
            return new GlobalPartDeletionCleanup(
                $c->typed(GlobalSourceMutation::class),
                $c->typed(WorkingCanvasRefreshScheduler::class),
                $c->typed(FailureReporterInterface::class),
            );
        });

        $container->factory(ReusableFactory::class, static function (Container $c): ReusableFactory {
            return new ReusableFactory(
                $c->typed(CreateReusableUseCase::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(ReusableSourcePackageService::class),
                $c->typed(FailureReporterInterface::class),
                new SourcePackageUploadReader($c->typed(LocalFilesystemPortInterface::class)),
            );
        });
    }

    public function boot(Container $container): void
    {
        $metaBox = $container->typed(GlobalPartMetaBox::class);
        $deletionCleanup = $container->typed(GlobalPartDeletionCleanup::class);
        $globalPartService = $container->typed(GlobalPartService::class);
        $javaScriptRuntime = $container->typed(PageJavaScriptRuntimeService::class);
        $toolSettingsAccess = $container->typed(ToolSettingsAccess::class);
        $allowedCapabilities = $container->typed(GetPageBuilderAllowedCapabilities::class);
        $reusableFactory = $container->typed(ReusableFactory::class);
        $callbacks = new WordPressCallbackBoundary();

        // 9. Global Part meta box
        add_action('add_meta_boxes', [$metaBox, 'register']);
        add_action('save_post_upb_global_part', $callbacks->action('reusable_metabox.save', [$metaBox, 'save']), 10, 2);
        add_action('admin_notices', $callbacks->action('reusable_metabox.notice', [$metaBox, 'renderSaveNotice']));
        add_filter('wp_insert_post_data', [$metaBox, 'protectPostData'], 20, 2);
        add_action('admin_post_' . ReusableFactory::CREATE_ACTION, [$reusableFactory, 'create']);
        add_action('admin_post_' . ReusableFactory::IMPORT_ACTION, [$reusableFactory, 'importReusable']);
        add_action('load-post-new.php', $callbacks->action('reusable.post_new_redirect', [$reusableFactory, 'redirectPostNewForReusable']));
        $deletionCleanup->register();

        add_action('admin_notices', $callbacks->action('reusable.import_notice', static function (): void {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen === null || $screen->base !== 'edit' || $screen->post_type !== 'upb_global_part') {
                return;
            }

            \UncannyPageBuilder\Infrastructure\WordPress\AdminImportNoticeStore::render(ReusableFactory::IMPORT_NOTICE_SCREEN);
        }));

        add_action('admin_footer-edit.php', $callbacks->action('reusable.import_form', static function () use ($allowedCapabilities): void {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen === null || $screen->post_type !== 'upb_global_part') {
                return;
            }
            if (!$allowedCapabilities->currentUserHasAllowedCapability()) {
                return;
            }

            $fileInputId = 'upb-import-reusable-source-file';

            /*
             * The post-type list heading is rendered by WordPress core, so this
             * form is printed in the footer and moved beside the native Add New
             * action after core has produced the title row.
             */
            echo '<form id="upb-import-reusable-source-form" method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:none;margin-left:4px;">';
            echo '<input type="hidden" name="action" value="' . esc_attr(ReusableFactory::IMPORT_ACTION) . '">';
            wp_nonce_field(ReusableFactory::IMPORT_ACTION);
            echo '<input id="' . esc_attr($fileInputId) . '" type="file" name="source_package" accept="application/json,.json" style="display:none;">';
            echo '<button type="button" class="page-title-action" data-upb-file-trigger="' . esc_attr($fileInputId) . '">'
                . esc_html_x('Import reusable', 'Page Builder', 'uncanny-automator')
                . '</button>';
            echo '</form>';
            echo '<script>(function(){var form=document.getElementById("upb-import-reusable-source-form");var target=document.querySelector(".wrap .page-title-action");var input=document.getElementById("' . esc_js($fileInputId) . '");if(!form||!target||!input){return;}target.insertAdjacentElement("afterend",form);form.style.display="inline-block";var trigger=form.querySelector("[data-upb-file-trigger]");if(!trigger){return;}trigger.addEventListener("click",function(){input.click();});input.addEventListener("change",function(){if(input.files&&input.files.length>0){form.submit();}});})();</script>';
        }));

        // 9a. Reusables are an always-published internal source surface: the
        // canvas hides the draft/publish lifecycle and every read path is
        // published-only. Coerce editorial draft/pending saves back to 'publish'
        // so a stray "Save Draft" can't strand a reusable that reads can no
        // longer find. Trashing is intentionally left intact.
        add_filter('wp_insert_post_data', static function ($data = null): array {
            $data = is_array($data) ? $data : [];

            if (($data['post_type'] ?? '') !== 'upb_global_part') {
                return $data;
            }

            if (($data['post_status'] ?? '') !== 'trash') {
                $data['post_status'] = 'publish';
            }

            return $data;
        });

        // 9b. Global parts list — approachable edit/settings actions.
        add_filter('post_row_actions', static function ($actions = null, $post = null): array {
            $actions = is_array($actions) ? $actions : [];
            if (!$post instanceof \WP_Post) {
                return $actions;
            }

            if ($post->post_type !== 'upb_global_part') {
                return $actions;
            }

            $canvasUrl = AdminCanvasEditorWindowedGlobalPartPage::editorUrl($post->ID);
            $settingsUrl = admin_url('post.php?post=' . $post->ID . '&action=edit');
            $isRenderableReusable = ($post->post_status ?? '') === 'publish';

            unset($actions['inline hide-if-no-js']); // Quick Edit

            $updatedActions = [
                'edit' => '<a href="' . esc_url($isRenderableReusable ? $canvasUrl : $settingsUrl) . '">'
                    . esc_html_x('Edit', 'Reusable list row action', 'uncanny-automator') . '</a>',
            ];

            if ($isRenderableReusable) {
                $updatedActions['settings'] = '<a href="' . esc_url($settingsUrl) . '">'
                    . esc_html_x('Settings', 'Reusable list row action', 'uncanny-automator') . '</a>';
            }

            if (isset($actions['trash'])) {
                $updatedActions['trash'] = $actions['trash'];
            }

            foreach ($actions as $key => $action) {
                if (in_array($key, ['edit', 'trash', 'inline hide-if-no-js'], true)) {
                    continue;
                }

                $updatedActions[$key] = $action;
            }

            return $updatedActions;
        }, 10, 2);

        add_filter('get_edit_post_link', static function ($location = null, $postId = null): string {
            $location = is_string($location) ? $location : '';
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null) {
                return $location;
            }

            $post = get_post($postId);
            if (!$post instanceof \WP_Post || $post->post_type !== 'upb_global_part') {
                return $location;
            }

            if (($post->post_status ?? '') !== 'publish') {
                return $location;
            }

            return AdminCanvasEditorWindowedGlobalPartPage::editorUrl($postId);
        }, 10, 2);

        // 9b-1. Global parts list — Type + Default badge columns.
        $gpDefaults = $container->typed(GlobalPartDefaultsService::class);

        add_filter('manage_upb_global_part_posts_columns', static function ($columns = null): array {
            $columns = is_array($columns) ? $columns : [];

            $out = [];
            foreach ($columns as $key => $label) {
                $out[$key] = $label;
                if ($key === 'title') {
                    $out['upb_type'] = _x('Type', 'Page Builder', 'uncanny-automator');
                }
            }
            return $out;
        });

        add_action('manage_upb_global_part_posts_custom_column', static function ($column = null, $postId = null) use ($gpDefaults): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($column !== 'upb_type' || $postId === null) {
                return;
            }

            $raw = get_post_meta($postId, '_upb_global_part_type', true);
            $type = GlobalPartType::fromString(is_string($raw) ? $raw : '');
            $label = ucfirst($type->value);

            $colors = match ($type) {
                GlobalPartType::Header  => 'background:#e8f0fe;color:#1a56db',
                GlobalPartType::Footer  => 'background:#fef3c7;color:#92400e',
                GlobalPartType::Section => 'background:#f3f4f6;color:#4b5563',
            };

            echo '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:12px;font-weight:500;' . $colors . '">'
                . esc_html($label) . '</span>';

            // Show "Default" badge for the active header/footer.
            if ($type === GlobalPartType::Header || $type === GlobalPartType::Footer) {
                $defaultId = $gpDefaults->getDefaultId($type);
                if ($defaultId === $postId) {
                    echo ' <span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534">'
                        . esc_html_x('Default', 'Reusable status badge', 'uncanny-automator') . '</span>';
                }
            }
        }, 10, 2);

        // 9b-2. Enqueue CodeMirror 6 editor on global-part edit screens.
        add_action('admin_enqueue_scripts', static function ($hook = null): void {
            if (!is_string($hook) || !in_array($hook, ['post.php', 'post-new.php'], true)) {
                return;
            }
            $screen = get_current_screen();
            if ($screen === null || $screen->post_type !== 'upb_global_part') {
                return;
            }
            wp_enqueue_script(
                'upb-admin-codemirror',
                UNCANNY_PB_URL . 'assets/js/admin-codemirror.js',
                [],
                (string) filemtime(UNCANNY_PB_PATH . 'assets/js/admin-codemirror.js'),
                true,
            );
        });

        // 9b-3. Global part post editor — trim generic WP publish metadata.
        add_action('admin_head', static function (): void {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen === null || $screen->base !== 'post' || $screen->post_type !== 'upb_global_part') {
                return;
            }

            /*
             * Reusable parts are an internal Page Builder source surface, not a
             * public publishing workflow. Hide the stock status/visibility/date
             * chrome so the screen does not show generic WordPress save guidance
             * that contradicts this editor's simpler update contract.
             */
            echo '<style id="upb-global-part-submitbox-cleanup">'
                . '#submitpost #misc-publishing-actions,'
                . '#submitpost #preview-action{display:none;}'
                . '#submitpost #minor-publishing-actions{padding:0;border-bottom:0;min-height:0;}'
                . '#submitpost #major-publishing-actions{border-top:0;}'
                . '</style>';
        });

        // 9c. Global part post editor — editable code editor meta box.
        add_action('add_meta_boxes_upb_global_part', $callbacks->action('reusable.code_metabox', static function ($post = null) use ($globalPartService, $javaScriptRuntime, $toolSettingsAccess, $allowedCapabilities): void {
            if (!$post instanceof \WP_Post) {
                return;
            }

            $sourceSection = $globalPartService->sourceSection($post->ID);
            if ($sourceSection === null) {
                return;
            }

            $part = $globalPartService->findById($post->ID);
            $sections = is_array($part) && is_array($part['sections'] ?? null) ? $part['sections'] : [];
            $legacySourceRowCount = count($sections);
            $runtimeJavaScriptEnabled = $toolSettingsAccess->globalPartCustomJavaScriptEnabled();
            $runtimeJavaScript = $javaScriptRuntime->readForGlobalPart($post->ID);

            add_meta_box(
                'upb_global_part_code',
                _x('Source code', 'Page Builder', 'uncanny-automator'),
                static function () use ($sourceSection, $legacySourceRowCount, $runtimeJavaScriptEnabled, $runtimeJavaScript, $post, $allowedCapabilities): void {
                    try {
                        $canEditCode = $allowedCapabilities->currentUserHasAllowedCapability();
                        include __DIR__ . '/../../../../Presentation/GlobalParts/code-editor.php';
                    } catch (\Throwable $failure) {
                        // Render nothing further; a metabox failure must not
                        // fail the complete WordPress edit screen.
                        error_log('[Uncanny Page Builder] Reusable code metabox render failed (' . $failure::class . ')');
                    }
                },
                'upb_global_part',
                'normal',
                'low'
            );
        }));

        // 9d. Save global part code on post save through the canonical source
        // row so the compiled projection stays in sync with the editor form.
        add_action('save_post_upb_global_part', $callbacks->action('reusable.code_save', static function ($postId = null, $post = null) use ($globalPartService, $javaScriptRuntime, $toolSettingsAccess, $allowedCapabilities): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null || !$post instanceof \WP_Post) {
                return;
            }

            // Guard against infinite loop: the compiled projection write calls
            // wp_update_post() which re-triggers save_post_upb_global_part.
            static $saving = [];
            if (isset($saving[$postId])) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }
            if (!isset($_POST['upb_gp_code_nonce'])) {
                return;
            }
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['upb_gp_code_nonce'])), 'upb_save_global_part_code_' . $postId)) {
                return;
            }
            if (!$allowedCapabilities->currentUserHasAllowedCapability()) {
                return;
            }

            $rawHtml = wp_unslash($_POST['upb_gp_html'] ?? null);
            if (!is_string($rawHtml)) {
                return;
            }
            $rawCss = wp_unslash($_POST['upb_gp_css'] ?? null);
            $rawJavaScript = wp_unslash($_POST['upb_gp_javascript'] ?? null);
            $rawName = wp_unslash($_POST['upb_gp_name'] ?? null);
            $name = sanitize_text_field(is_string($rawName) ? $rawName : '');
            $rawType = get_post_meta($postId, '_upb_global_part_type', true);

            $saving[$postId] = true;

            try {
                $globalPartService->replaceExistingSource(
                    globalPartId: $postId,
                    title: $post->post_title,
                    sectionData: [
                        'name'    => $name !== '' ? $name : $post->post_title,
                        'content' => [
                            'html' => $rawHtml,
                            // The code editor allows broad CSS regardless of the
                            // unfiltered_html capability, so apply the strict
                            // syntax filter before the capability-aware gate.
                            'css'  => CssSanitizationGate::filterDangerousSyntax(is_string($rawCss) ? $rawCss : ''),
                        ],
                    ],
                    type: GlobalPartType::fromString(is_string($rawType) ? $rawType : ''),
                );

                // Section: Reusable runtime lane
                if ($toolSettingsAccess->globalPartCustomJavaScriptEnabled() && is_string($rawJavaScript)) {
                    if (!current_user_can('unfiltered_html')) {
                        self::rememberCodeEditorError($postId, _x('The reusable JavaScript could not be saved because this user cannot publish unfiltered code.', 'Page Builder', 'uncanny-automator'));
                    } elseif (trim($rawJavaScript) === '') {
                        $javaScriptRuntime->clearForGlobalPart($postId);
                    } else {
                        $javaScriptRuntime->replaceForGlobalPart($postId, $rawJavaScript);
                    }
                }
            } catch (\InvalidArgumentException $e) {
                self::rememberCodeEditorError($postId, _x('The reusable code could not be saved.', 'Page Builder', 'uncanny-automator'));
            } catch (\RuntimeException $e) {
                self::rememberCodeEditorError($postId, _x('The reusable code could not be saved.', 'Page Builder', 'uncanny-automator'));
            } catch (\Throwable $failure) {
                // WordPress completed its own save before this observer ran.
                // Contain the failure and surface the standard editor notice.
                error_log('[Uncanny Page Builder] Reusable code save failed (' . $failure::class . ')');
                self::rememberCodeEditorError($postId, _x('The reusable code could not be saved.', 'Page Builder', 'uncanny-automator'));
            } finally {
                unset($saving[$postId]);
            }
        }), 20, 2);

        // 9e. Surface guarded-save failures on the next edit-screen load.
        add_action('admin_notices', $callbacks->action('reusable.code_notice', static function (): void {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen === null || $screen->base !== 'post' || $screen->post_type !== 'upb_global_part') {
                return;
            }

            $postId = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
            if ($postId <= 0) {
                return;
            }

            $message = get_transient(self::codeEditorErrorKey($postId));
            if (!is_string($message) || $message === '') {
                return;
            }
            delete_transient(self::codeEditorErrorKey($postId));

            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }));
    }

    private static function rememberCodeEditorError(int $postId, string $message): void
    {
        set_transient(self::codeEditorErrorKey($postId), $message, 60);
    }

    private static function codeEditorErrorKey(int $postId): string
    {
        return 'upb_gp_code_error_' . $postId . '_' . get_current_user_id();
    }
}
