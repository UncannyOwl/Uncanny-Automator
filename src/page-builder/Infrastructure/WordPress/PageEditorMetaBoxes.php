<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Controls\ControlContext;
use UncannyPageBuilder\Application\Controls\ControlStateService;
use UncannyPageBuilder\Application\Editor\EditorStateService;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\Publishing\PageLiveStateReaderInterface;
use UncannyPageBuilder\Application\Rendering\PublishedPageReaderInterface;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Controls\CanvasArea;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Domain\Shell\ShellModeContext;
use UncannyPageBuilder\Infrastructure\Persistence\WpDesignStandardsRepository;
use UncannyPageBuilder\Infrastructure\Section\DynamicRegionToken;
use UncannyPageBuilder\Infrastructure\Section\SiteLogoImageNormalizer;

/**
 * Native WordPress admin surfaces for Page Builder-owned pages.
 *
 * Replaces the former React page-editor panel with ordinary metaboxes and a
 * slim core-button actions row, so the edit screen speaks wp-admin instead of
 * hosting an embedded app:
 *
 *  - "Page sections" (normal context): section list with Preview / Edit code
 *    row actions backed by the existing ThickBox + CodeMirror modals, plus a
 *    page-level custom JavaScript editor that writes to the runtime lane.
 *  - "Page layout" (side context): the shell-mode select; saves through the
 *    page's Update button via EditorEnvironmentProvider::handleSave().
 *  - "Website status" (side context): exact draft-versus-live relationship.
 *  - Actions row (edit_form_after_title): Open editor, return to WordPress,
 *    and admin controls such as Export HTML.
 */
final class PageEditorMetaBoxes
{
    public const SHELL_MODE_NONCE_KEY = 'uncanny_page_builder_page_shell_mode_nonce';
    public const SHELL_MODE_NONCE_ACTION = 'uncanny_page_builder_save_page_shell_mode';
    public const SHELL_MODE_FIELD = 'uncanny_page_builder_shell_mode';
    public const SOURCE_GENERATION_FIELD = 'uncanny_page_builder_source_generation';

    public function __construct(
        private readonly SectionRepositoryInterface $sectionRepo,
        private readonly ShellModeService $shellModeService,
        private readonly EditorStateService $editorStateService,
        private readonly ControlStateService $controlStateService,
        private readonly PageLiveStateReaderInterface $pageLiveState,
        private readonly PublishedPageReaderInterface $publishedPages,
        private readonly PermissionChecker $permissions,
        private readonly ?BindingRegistry $bindingRegistry = null,
        private readonly ?PageJavaScriptRuntimeService $javaScriptRuntime = null,
        private readonly ?ToolSettingsAccess $toolSettingsAccess = null,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
        private readonly ?SelectEditorPageSource $pageSources = null,
    ) {}

    public function register(\WP_Post $post): void
    {
        if (
            !$this->supportsPostType->isEnabledByAdministrator($post->post_type)
            || !$this->ownsPage($post)
        ) {
            return;
        }

        if (!$this->permissions->canEditPost($post->ID)) {
            add_meta_box(
                'upb_page_access',
                _x('Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
                [$this, 'renderAccessNotice'],
                $post->post_type,
                'normal',
                'high',
            );
            return;
        }

        if (!$this->supportsPostType->isSupported($post->post_type)) {
            add_meta_box(
                'upb_page_access',
                _x('Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
                [$this, 'renderUnsupportedPostTypeNotice'],
                $post->post_type,
                'normal',
                'high',
            );
            return;
        }

        add_meta_box(
            'upb_page_sections',
            _x('Page sections', 'Page Builder', 'uncanny-automator'),
            [$this, 'renderSections'],
            $post->post_type,
            'normal',
            'high'
        );

        add_meta_box(
            'upb_page_layout',
            _x('Page layout', 'Page Builder', 'uncanny-automator'),
            [$this, 'renderLayout'],
            $post->post_type,
            'side',
            'default'
        );

        add_meta_box(
            'upb_website_status',
            _x('Website status', 'Page Builder', 'uncanny-automator'),
            [$this, 'renderWebsiteStatus'],
            $post->post_type,
            'side',
            'default'
        );
    }

    /**
     * Slim row of core buttons under the title: Open editor plus any
     * admin-surface controls (e.g. Export HTML). Hooked on
     * edit_form_after_title.
     */
    public function renderActionsRow(\WP_Post $post): void
    {
        if (
            !$this->supportsPostType->isEnabledByAdministrator($post->post_type)
            || !$this->ownsPage($post)
            || !$this->permissions->canEditPost($post->ID)
        ) {
            return;
        }

        $showEditor = $this->supportsPostType->isSupported($post->post_type);
        $viewUrl = $showEditor ? AiPagesListTable::frontendEditorUrl($post->ID) : '';
        $adminControls = $showEditor ? $this->adminHeaderControls($post)['controls'] : [];
        $switchField = PageOwnershipActions::SWITCH_FIELD;
        $switchNonceField = PageOwnershipActions::NONCE_FIELD;
        $switchNonceAction = PageOwnershipActions::NONCE_ACTION;
        $switchConfirmMessage = _x(
            'Switch this page back to the WordPress editor? Page Builder sections will stop rendering. Your Page Builder work will be kept, and any saved WordPress page body will be restored.',
            'Page Builder',
            'uncanny-automator',
        );
        $canSwitchToWordPress = ($post->post_status ?? '') !== 'publish'
            || $this->permissions->canPublishPost($post->ID);

        include __DIR__ . '/../../Presentation/Pages/editor-actions.php';
    }

    public function renderAccessNotice(): void
    {
        echo '<p>' . esc_html_x(
            'This page is managed by Uncanny Page Builder. Ask a site administrator with Page Builder access to edit its content or design.',
            'Page Builder',
            'uncanny-automator',
        ) . '</p>';
    }

    public function renderUnsupportedPostTypeNotice(): void
    {
        echo '<p>' . esc_html_x(
            'This content is still managed by Uncanny Page Builder, but its post type is no longer eligible for Page Builder editing. Use “Switch to WordPress editor” above to restore the saved WordPress content safely.',
            'Page Builder',
            'uncanny-automator',
        ) . '</p>';
    }

    public function renderSections(\WP_Post $post): void
    {
        $editorState = $this->editorStateService
            ->buildForPage($post->ID, $this->capabilities($post->ID))
            ->toArray();
        $sectionStateById = $this->indexEditorSections($editorState['sections'] ?? []);
        $sourceState = is_array($editorState['source'] ?? null) ? $editorState['source'] : [];
        $sourceSelection = $this->pageSources?->forPage($post->ID);
        if (
            $sourceSelection !== null
            && (
                $sourceSelection->loadedSource() !== ($sourceState['loaded_source'] ?? null)
                || $sourceSelection->workingGeneration() !== ($sourceState['working_generation'] ?? null)
                || $sourceSelection->toArray()['loaded_snapshot_id'] !== ($sourceState['loaded_snapshot_id'] ?? null)
            )
        ) {
            echo '<p>' . esc_html_x(
                'This page changed while the editor was loading. Refresh before editing its source.',
                'Page Builder',
                'uncanny-automator',
            ) . '</p>';
            return;
        }
        $publishedSource = $sourceSelection?->loadedSource() === 'published'
            ? $sourceSelection->publishedSnapshot()?->source()
            : null;
        $sourceSections = is_array($publishedSource)
            ? array_values(array_filter($publishedSource['sections'] ?? [], 'is_array'))
            : $this->sectionRepo->findByPageId($post->ID)->toArray();

        $sectionRows = array_map(
            static fn(array $section, int $index): array => [
                'id' => (string) ((int) ($section['id'] ?? 0)),
                'index' => $index + 1,
                'name' => (string) ($section['name'] ?? '') !== ''
                    ? (string) $section['name']
                    : _x('Untitled section', 'Page Builder', 'uncanny-automator'),
            ],
            $sourceSections,
            array_keys($sourceSections),
        );

        $sectionCodeData = [];
        foreach ($sourceSections as $sourceSection) {
            $sectionId = (int) ($sourceSection['id'] ?? 0);
            $sectionState = $sectionStateById[$sectionId] ?? [];
            $content = is_array($sourceSection['content'] ?? null) ? $sourceSection['content'] : [];
            // Display-side canonicalization (nothing is persisted here): rows
            // saved before the current normalizer rules still show clean
            // binding tokens instead of stale resolved placeholders, and the
            // save path decodes the token right back — display, save, and
            // render always agree.
            $sectionCodeData[$sectionId] = [
                'html' => DynamicRegionToken::encodeForCodeEditor(
                    SiteLogoImageNormalizer::normalize((string) ($content['html'] ?? '')),
                    $this->bindingRegistry?->maskableRegionBindingIds(),
                ),
                'css'  => (string) ($content['css'] ?? ''),
                'name' => (string) ($sectionState['name'] ?? $sourceSection['name'] ?? ''),
            ];
        }

        $sectionRewriteControlId = $this->adminHeaderControls($post)['rewriteControlId'];

        // Section: Page runtime lane
        $pageRuntimeData = [
            'enabled' => ($this->toolSettingsAccess?->pageCustomJavaScriptEnabled() ?? true)
                && $this->permissions->canCapability('unfiltered_html'),
            'ownerId' => (int) $post->ID,
            'javascript' => is_array($publishedSource)
                ? (string) ($publishedSource['custom_javascript'] ?? '')
                : ($this->javaScriptRuntime?->readForPage($post->ID) ?? ''),
            'source' => [
                'loaded_source' => (string) ($sourceState['loaded_source'] ?? 'working'),
                'working_generation' => (int) ($sourceState['working_generation'] ?? 0),
                'snapshot_id' => isset($sourceState['loaded_snapshot_id'])
                    ? (int) $sourceState['loaded_snapshot_id']
                    : null,
            ],
        ];

        // Preview iframe dependencies.
        $bootstrapUrl = esc_url(UNCANNY_PB_URL . 'assets/css/bootstrap.min.css');
        $tokenCss = '';
        $tokens = (new WpDesignStandardsRepository())
            ->load()?->tokens()->toArray() ?? [];
        foreach ($tokens as $key => $val) {
            if (is_string($key) && is_string($val) && $val !== '' && str_starts_with($key, '--')) {
                $tokenCss .= esc_attr($key) . ':' . esc_attr($val) . ';';
            }
        }

        include __DIR__ . '/../../Presentation/Pages/page-sections-metabox.php';
    }

    public function renderLayout(\WP_Post $post): void
    {
        $workingShellCtx = $this->shellModeService->resolveForPage($post->ID);
        $sourceSelection = $this->pageSources?->forPage($post->ID);
        $workingGeneration = $sourceSelection?->workingGeneration()
            ?? $this->sectionRepo->findByPageId($post->ID)->generation();
        $publishedSource = $sourceSelection?->loadedSource() === 'published'
            ? $sourceSelection->publishedSnapshot()?->source()
            : null;
        $layoutReadOnly = is_array($publishedSource);
        $snapshotMode = $layoutReadOnly
            ? ShellMode::tryFrom((string) ($publishedSource['shell_mode'] ?? ''))
            : null;
        $shellCtx = $layoutReadOnly
            ? new ShellModeContext(
                mode: $snapshotMode ?? $workingShellCtx->mode,
                hasUncannyHeader: $workingShellCtx->hasUncannyHeader,
                hasUncannyFooter: $workingShellCtx->hasUncannyFooter,
                isExplicit: (bool) ($publishedSource['shell_mode_explicit'] ?? false),
            )
            : $workingShellCtx;

        include __DIR__ . '/../../Presentation/Pages/page-layout-metabox.php';
    }

    public function renderWebsiteStatus(\WP_Post $post): void
    {
        $pageLiveStatus = $this->pageLiveState->forPage($post->ID);
        $publicationRead = $this->publishedPages->read($post->ID);

        include __DIR__ . '/../../Presentation/Pages/website-status-metabox.php';
    }

    private function ownsPage(\WP_Post $post): bool
    {
        return $this->sectionRepo->isOwnedPage($post->ID);
    }

    /** @return array<string, bool> */
    private function capabilities(int $postId): array
    {
        return [
            'can_edit'    => $this->permissions->canEditPost($postId),
            'can_manage'  => $this->permissions->canManagePost($postId),
            'can_upload'  => $this->permissions->canUploadFiles(),
            'can_publish' => $this->permissions->canPublishPost($postId),
        ];
    }

    /**
     * Admin-surface controls (Export HTML and friends) plus the section
     * rewrite control id the code editor modal posts to.
     *
     * @return array{controls: array<int, array<string, string|bool>>, rewriteControlId: string}
     */
    private function adminHeaderControls(\WP_Post $post): array
    {
        $controlContext = ControlContext::forPage(
            (int) $post->ID,
            (int) get_current_user_id(),
            $this->capabilities($post->ID),
        );

        $hiddenControls = $this->controlStateService->controlsForArea($controlContext, CanvasArea::Hidden, false);
        $topBarRightControls = $this->controlStateService->controlsForArea($controlContext, CanvasArea::TopBarRight, false);

        $adminControls = [];
        $rewriteControlId = 'section.rewrite_source';

        foreach (array_merge($hiddenControls, $topBarRightControls) as $control) {
            $presentation = isset($control['presentation']) && is_array($control['presentation'])
                ? $control['presentation']
                : [];

            if (($control['id'] ?? null) === 'section.rewrite_source') {
                $rewriteControlId = (string) $control['id'];
            }

            if (($presentation['surface'] ?? null) !== 'admin_header' || !(bool) ($control['visible'] ?? true)) {
                continue;
            }

            if (($control['type'] ?? null) !== 'trigger' || ($control['client_hint'] ?? null) !== 'download_artifacts') {
                continue;
            }

            $controlId = (string) ($control['id'] ?? '');
            $adminControls[] = [
                'id' => $controlId,
                'label' => (string) ($presentation['button_label'] ?? $control['label'] ?? _x('Run', 'Page Builder', 'uncanny-automator')),
                'enabled' => (bool) ($control['enabled'] ?? true),
                'url' => rest_url('uncanny-page-builder/v1/editor/controls/' . rawurlencode($controlId) . '/invoke'),
                'pageId' => (string) $post->ID,
                'restNonce' => wp_create_nonce('wp_rest'),
                'busyLabel' => (string) ($presentation['busy_label'] ?? _x('Working...', 'Page Builder', 'uncanny-automator')),
                'successLabel' => (string) ($presentation['success_label'] ?? _x('Done', 'Page Builder', 'uncanny-automator')),
                'downloadName' => (string) ($presentation['download_name'] ?? 'uncanny-page-builder-export'),
            ];
        }

        return ['controls' => $adminControls, 'rewriteControlId' => $rewriteControlId];
    }

    /**
     * @param mixed $sections
     * @return array<int, array<string, mixed>>
     */
    private function indexEditorSections(mixed $sections): array
    {
        if (!is_array($sections)) {
            return [];
        }

        $indexed = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $id = (int) ($section['id'] ?? 0);
            if ($id > 0) {
                $indexed[$id] = $section;
            }
        }

        return $indexed;
    }
}
