<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class AdminMenu
{
    // Main admin routes
    private const MENU_SLUG = 'uncanny-page-builder';
    private const NATIVE_PAGES_MENU_SLUG = 'edit.php?post_type=page';
    private const SETTINGS_NEW_SLUG = self::MENU_SLUG . '-settings';
    // Automator registers its top-level menu at position 40.
    private const MENU_POSITION_AFTER_AUTOMATOR = 41;

    private SectionRepositoryInterface $repository;
    private GetPageBuilderAllowedCapabilities $allowedCapabilities;
    private string $legacyPagesHookSuffix = '';
    private string $settingsNewHookSuffix = '';
    private ?AdminSettingsPage $settingsPage = null;
    private ?AdminContentTypesPage $contentTypesPage = null;
    private ?AdminSettingsJavaScriptPage $javascriptPage = null;
    private ?AdminSettingsToolsPage $toolsPage = null;
    private ?AdminBrandingPage $brandingPage = null;
    private ?AdminPersonalizationPage $personalizationPage = null;
    private ?AdminCanvasEditorWindowedPage $canvasEditorWindowedPage = null;
    private string $canvasEditorWindowedPageHookSuffix = '';
    private ?AdminCanvasEditorWindowedGlobalPartPage $canvasEditorWindowedGlobalPartPage = null;
    private string $canvasEditorWindowedGlobalPartPageHookSuffix = '';

    public function __construct(
        SectionRepositoryInterface $repository,
        GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly PageBuilderAvailabilityInterface $availability,
    ) {
        $this->repository = $repository;
        $this->allowedCapabilities = $allowedCapabilities;
    }

    public function setSettingsPage(AdminSettingsPage $settingsPage): void
    {
        $this->settingsPage = $settingsPage;
    }

    public function setContentTypesPage(AdminContentTypesPage $contentTypesPage): void
    {
        $this->contentTypesPage = $contentTypesPage;
    }

    public function setToolsPage(AdminSettingsToolsPage $toolsPage): void
    {
        $this->toolsPage = $toolsPage;
    }

    public function setJavaScriptPage(AdminSettingsJavaScriptPage $javascriptPage): void
    {
        $this->javascriptPage = $javascriptPage;
    }

    public function setBrandingPage(AdminBrandingPage $brandingPage): void
    {
        $this->brandingPage = $brandingPage;
    }

    public function setPersonalizationPage(AdminPersonalizationPage $personalizationPage): void
    {
        $this->personalizationPage = $personalizationPage;
    }

    public function setCanvasEditorWindowedPage(AdminCanvasEditorWindowedPage $canvasEditorWindowedPage): void
    {
        $this->canvasEditorWindowedPage = $canvasEditorWindowedPage;
    }

    public function setCanvasEditorWindowedGlobalPartPage(AdminCanvasEditorWindowedGlobalPartPage $canvasEditorWindowedGlobalPartPage): void
    {
        $this->canvasEditorWindowedGlobalPartPage = $canvasEditorWindowedGlobalPartPage;
    }

    public function register(): void
    {
        $this->registerHiddenEditorRoutes();

        if (!$this->availability->allowsNewPages()) {
            return;
        }

        $this->registerVisibleMenu();
        $this->reorderSubmenu();
    }

    private function registerVisibleMenu(): void
    {
        // Main navigation
        $icon_url = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="M42.56,21.82c-.27,2.32-.27,4.67,0,6.99.4,3.44,6.7,3.44,7.27,0,.37-2.31.37-4.65,0-6.96-.56-3.44-6.9-3.44-7.27-.03Z" style="fill:#f3f1f1;"/><path d="M13.97,21.86c-.37,2.31-.37,4.65,0,6.96.57,3.44,6.88,3.44,7.28,0,.27-2.32.27-4.67,0-6.99-.37-3.42-6.67-3.42-7.28.03Z" style="fill:#f3f1f1;"/><path d="M37,33.06c-3.13.82-6.41.82-9.54,0,.29,5.9,9.25,5.9,9.54,0Z" style="fill:#f3f1f1;"/><path d="M31.35,42.01c-8.54-.04-17.02-1.16-22.11-3.36-.78-.32-3.24-1.51-3.52-2.53-1.46-6.05-1.84-15.43-1.01-23.2.12-1.19.82-1.36,1.73-1.56,5.08-1.18,10.25-1.89,15.46-2.16.18-.01.36.06.5.18.13.12.21.29.24.47.37,2.86.99,5.67,1.84,8.43.62,1.23,4.58,1.76,7.42,1.76s6.84-.53,7.36-1.76c.85-2.75,1.48-5.57,1.85-8.43.02-.18.11-.35.25-.47.13-.11.31-.18.49-.18,5.2.24,10.35.96,15.42,2.16.94.2,1.64.37,1.76,1.56.68,6.4.54,13.9-.36,19.75,1.43.85,2.75,1.88,3.91,3.07.76-3.7,1.22-7.46,1.3-11.24.13-4.01-.02-8.03-.46-12.02-.34-3.07-2.25-4.75-5.15-5.4-8.67-1.82-17.51-2.69-26.38-2.58-8.84-.08-17.66.78-26.32,2.58C2.62,7.74.72,9.42.38,12.49,0,16.49-.15,20.5-.02,24.51c.09,4.27.61,8.52,1.56,12.69.65,2.73,3.6,4.49,6.05,5.52,5.4,2.31,13.91,3.53,22.61,3.67.21-1.52.6-2.99,1.15-4.37Z" style="fill:#f3f1f1;"/><path d="M64,49v.16c-.02,2.14-1.97,3.59-4.11,3.59h-5.74c-1.55,0-2.81,1.26-2.81,2.81,0,.2.02.39.06.58.12.6.38,1.17.63,1.75.36.81.71,1.61.71,2.46,0,1.86-1.27,3.56-3.13,3.63-.21,0-.41.01-.62.01-8.28,0-15-6.71-15-15s6.72-15,15-15,15,6.71,15,15ZM41.5,50.88c0-1.04-.84-1.87-1.87-1.87s-1.87.84-1.87,1.87.84,1.87,1.87,1.87,1.87-.84,1.87-1.87ZM41.5,45.25c1.04,0,1.87-.84,1.87-1.87s-.84-1.87-1.87-1.87-1.87.84-1.87,1.87.84,1.87,1.87,1.87ZM50.88,39.63c0-1.04-.84-1.87-1.87-1.87s-1.87.84-1.87,1.87.84,1.87,1.87,1.87,1.87-.84,1.87-1.87ZM56.5,45.25c1.04,0,1.87-.84,1.87-1.87s-.84-1.87-1.87-1.87-1.87.84-1.87,1.87.84,1.87,1.87,1.87Z" style="fill:#f3f1f1;"/></svg>');
        $this->legacyPagesHookSuffix = (string) add_menu_page(
            _x('Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
            _x('Page Builder', 'Page Builder', 'uncanny-automator'),
            PageBuilderAccessCapability::NAME,
            self::MENU_SLUG,
            [$this, 'renderPagesPage'],
            $icon_url,
            self::MENU_POSITION_AFTER_AUTOMATOR
        );

        if ($this->legacyPagesHookSuffix !== '') {
            add_action('load-' . $this->legacyPagesHookSuffix, [$this, 'redirectLegacyPagesRoute']);
        }

        $this->settingsNewHookSuffix = (string) add_submenu_page(
            self::MENU_SLUG,
            _x('Settings', 'Page Builder', 'uncanny-automator'),
            _x('Settings', 'Page Builder', 'uncanny-automator'),
            PageBuilderAccessCapability::NAME,
            self::SETTINGS_NEW_SLUG,
            [$this, 'renderSettingsNewPage']
        );

        if ($this->settingsNewHookSuffix !== '') {
            add_action('load-' . $this->settingsNewHookSuffix, [$this, 'prepareSettingsNewPage']);
        }
    }

    private function registerHiddenEditorRoutes(): void
    {
        // Hidden editor route
        $this->canvasEditorWindowedPageHookSuffix = (string) add_submenu_page(
            null,
            _x('Manual editor', 'Page Builder', 'uncanny-automator'),
            '',
            PageBuilderAccessCapability::NAME,
            AdminCanvasEditorWindowedPage::PAGE_SLUG,
            [$this, 'renderCanvasEditorWindowedPage']
        );

        if ($this->canvasEditorWindowedPageHookSuffix !== '') {
            add_action('load-' . $this->canvasEditorWindowedPageHookSuffix, [$this, 'prepareCanvasEditorWindowedPage']);
        }

        // Hidden editor route — reusables
        $this->canvasEditorWindowedGlobalPartPageHookSuffix = (string) add_submenu_page(
            null,
            _x('Manual editor', 'Page Builder', 'uncanny-automator'),
            '',
            PageBuilderAccessCapability::NAME,
            AdminCanvasEditorWindowedGlobalPartPage::PAGE_SLUG,
            [$this, 'renderCanvasEditorWindowedGlobalPartPage']
        );

        if ($this->canvasEditorWindowedGlobalPartPageHookSuffix !== '') {
            add_action('load-' . $this->canvasEditorWindowedGlobalPartPageHookSuffix, [$this, 'prepareCanvasEditorWindowedGlobalPartPage']);
        }

        // Hidden route menu state
        add_filter('parent_file', [$this, 'filterCanvasEditorWindowedParentFile']);
        add_filter('submenu_file', [$this, 'filterCanvasEditorWindowedSubmenuFile']);
        add_filter('parent_file', [$this, 'filterCanvasEditorWindowedGlobalPartParentFile']);
        add_filter('submenu_file', [$this, 'filterCanvasEditorWindowedGlobalPartSubmenuFile']);
    }

    public function renderPagesPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html_x('Pages', 'Page Builder', 'uncanny-automator') . '</h1>';
        echo '<hr class="wp-header-end">';
        echo '<p>' . esc_html_x('Page Builder pages now live in the standard WordPress Pages list.', 'Page Builder', 'uncanny-automator') . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(AdminCanvasEditorWindowedPage::pagesScreenUrl()) . '">'
            . esc_html_x('View all pages', 'Page Builder', 'uncanny-automator')
            . '</a></p>';
        echo '</div>';
    }

    public function redirectLegacyPagesRoute(): void
    {
        wp_safe_redirect(AdminCanvasEditorWindowedPage::pagesScreenUrl(), 302, 'Uncanny Page Builder');
        exit;
    }

    public function renderNativePageNotices(): void
    {
        if (!$this->isNativePagesScreen()) {
            return;
        }

        AdminImportNoticeStore::render(PageFactory::IMPORT_NOTICE_SCREEN);
        $this->renderCanvasEditorWindowedNotice();
    }

    public function renderNativePageImportForm(): void
    {
        if (
            !$this->availability->allowsNewPages()
            || !$this->isNativePagesScreen()
            || !$this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            return;
        }

        $pageImportFormId = 'upb-import-page-source-form';
        $pageImportFileInputId = 'upb-import-page-source-file';
        $pageImportTarget = '';
        $pageImportReturnContext = 'pages';
        $pageImportReturnPageId = 0;
        require dirname(__DIR__, 2) . '/templates/admin/page-source-import-form.php';

        /*
         * WordPress owns the Pages heading markup. Render the import action in
         * the footer, then move it beside the native Add Page action after core
         * has produced the title row.
         */
        echo '<button id="upb-import-page-source-trigger" type="button" class="page-title-action" hidden>'
            . esc_html_x('Import Uncanny Page Builder page', 'Page Builder', 'uncanny-automator')
            . '</button>';
        echo '<script>(function(){var trigger=document.getElementById("upb-import-page-source-trigger");var target=document.querySelector(".wrap .page-title-action");var input=document.getElementById("'
            . esc_js($pageImportFileInputId)
            . '");if(!trigger||!target||!input){return;}target.insertAdjacentElement("afterend",trigger);trigger.hidden=false;trigger.addEventListener("click",function(){input.value="";input.click();});})();</script>';
    }

    public function renderSettingsNewPage(): void
    {
        $tabs = $this->settingsTabs();
        $activeTab = $this->currentSettingsTab($tabs);
        $designSections = $activeTab === 'design' ? $this->designSections() : [];
        $activeDesignSection = $activeTab === 'design'
            ? $this->currentDesignSection($designSections)
            : '';
        $contentRenderer = function () use ($activeTab, $designSections, $activeDesignSection): void {
            if ($activeTab === 'design' && $this->brandingPage !== null) {
                if ($activeDesignSection === 'logo') {
                    $this->brandingPage->renderPlainBrandIdentitySettingsContent();
                    return;
                }

                if ($activeDesignSection === 'fonts') {
                    $this->brandingPage->renderPlainFontLibrarySettingsContent();
                    return;
                }

                $this->brandingPage->renderPlainColorsComponentsSettingsContent();
                return;
            }

            if ($activeTab === 'text-styles' && $this->brandingPage !== null) {
                $this->brandingPage->renderPlainTextStylesSettingsContent(
                    $this->currentSectionId($this->textStyleSections(), 'headings')
                );
                return;
            }

            if ($activeTab === 'layout') {
                if ($this->settingsPage === null) {
                    return;
                }

                $this->settingsPage->renderPlainLayoutSettingsContent();
                return;
            }

            if ($activeTab === 'javascript' && $this->javascriptPage !== null) {
                $this->javascriptPage->render();
                return;
            }

            if ($activeTab === 'post-types' && $this->contentTypesPage !== null) {
                $this->contentTypesPage->render();
                return;
            }

            if ($activeTab === 'tools' && $this->toolsPage !== null) {
                $this->toolsPage->render();
                return;
            }

            if ($activeTab === 'personalization' && $this->personalizationPage !== null) {
                $this->personalizationPage->renderPlainPersonalizationSettingsContent();
                return;
            }

            $settingsSection = $this->staticSettingsSection($activeTab);
            include __DIR__ . '/../../Presentation/Settings/static-settings-section.php';
        };

        $sidebar = $this->settingsSidebar($activeTab, $activeDesignSection);
        $sidebarItems = $sidebar['items'];
        $activeSidebar = $sidebar['active'];
        $sidebarParameter = $sidebar['parameter'];

        include __DIR__ . '/../../Presentation/Settings/settings-new-shell.php';
    }

    public function prepareSettingsNewPage(): void
    {
        $this->suppressAdminNotices();

        /*
         * Automator scopes its WP-admin chrome overrides (zeroing #wpcontent
         * padding, unfloating #wpbody-content, hiding the footer and screen
         * meta) to its own settings body class. Wearing that class here applies
         * the exact same rules from the already-compiled bundle, so this screen
         * lines up with Automator's settings page without a stylesheet of its
         * own. Added on the page's load- hook, so no other screen is affected.
         *
         * Note: those rules also hide `.notice`, which is why this screen
         * reports state with <uo-alert> instead of WordPress notice markup.
         */
        add_filter('admin_body_class', [$this, 'filterSettingsNewBodyClass']);
    }

    public function filterSettingsNewBodyClass(string $classes): string
    {
        return trim($classes . ' uo-recipe_page_uncanny-automator-config');
    }

    public function renderCanvasEditorWindowedPage(): void
    {
        if ($this->canvasEditorWindowedPage === null) {
            return;
        }

        $this->canvasEditorWindowedPage->render();
    }

    public function prepareCanvasEditorWindowedPage(): void
    {
        if ($this->canvasEditorWindowedPage === null) {
            return;
        }

        $this->canvasEditorWindowedPage->prepareAdminHost();
    }

    public function renderCanvasEditorWindowedGlobalPartPage(): void
    {
        if ($this->canvasEditorWindowedGlobalPartPage === null) {
            return;
        }

        $this->canvasEditorWindowedGlobalPartPage->render();
    }

    public function prepareCanvasEditorWindowedGlobalPartPage(): void
    {
        if ($this->canvasEditorWindowedGlobalPartPage === null) {
            return;
        }

        $this->canvasEditorWindowedGlobalPartPage->prepareAdminHost();
    }

    public function filterCanvasEditorWindowedParentFile(string $parentFile): string
    {
        if (!$this->isCanvasEditorWindowedRequest()) {
            return $parentFile;
        }

        // A normal canvas edits a WordPress page, so native Pages owns its admin menu state.
        $this->attachCanvasEditorToMenuState(
            self::NATIVE_PAGES_MENU_SLUG,
            AdminCanvasEditorWindowedPage::PAGE_SLUG,
        );

        return self::NATIVE_PAGES_MENU_SLUG;
    }

    public function filterCanvasEditorWindowedSubmenuFile(?string $submenuFile): ?string
    {
        if (!$this->isCanvasEditorWindowedRequest()) {
            return $submenuFile;
        }

        return self::NATIVE_PAGES_MENU_SLUG;
    }

    public function filterCanvasEditorWindowedGlobalPartParentFile(string $parentFile): string
    {
        if (!$this->isCanvasEditorWindowedGlobalPartRequest()) {
            return $parentFile;
        }

        $this->attachCanvasEditorToMenuState(
            self::MENU_SLUG,
            AdminCanvasEditorWindowedGlobalPartPage::PAGE_SLUG,
        );

        return self::MENU_SLUG;
    }

    public function filterCanvasEditorWindowedGlobalPartSubmenuFile(?string $submenuFile): ?string
    {
        if (!$this->isCanvasEditorWindowedGlobalPartRequest()) {
            return $submenuFile;
        }

        // Highlight the Reusables (Global Parts) submenu.
        return 'edit.php?post_type=upb_global_part';
    }

    public function enqueueSettingsNewAssets(string $hookSuffix): void
    {
        if (!str_ends_with($hookSuffix, '_page_' . self::SETTINGS_NEW_SLUG)) {
            return;
        }

        // Reuse Automator's compiled admin bundle so this screen renders with the
        // exact same chrome as the Automator settings page: the uap-settings
        // classes and the uo-tabs / uo-button / uo-icon web components all ship
        // in that bundle, so the Page Builder settings need no styling of their own.
        if (class_exists('\\Uncanny_Automator\\Utilities')) {
            wp_enqueue_style(
                'uap-admin-font',
                'https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap',
                [],
                \Uncanny_Automator\Utilities::automator_get_version()
            );

            \Uncanny_Automator\Utilities::enqueue_asset(
                'uap-admin',
                'main',
                [
                    'localize' => [
                        // Minimal backend shape the bundle expects on boot. The
                        // Page Builder settings page only uses the uo-* web
                        // components, but the shared bundle still reads ajax/rest
                        // nonces and the icon integration map during init.
                        'UncannyAutomatorBackend' => [
                            'ajax' => [
                                'url' => admin_url('admin-ajax.php'),
                                'nonce' => wp_create_nonce('uncanny_automator'),
                            ],
                            'rest' => [
                                'url' => esc_url_raw(rest_url()),
                                'base' => esc_url_raw(rest_url()),
                                'nonce' => wp_create_nonce('wp_rest'),
                            ],
                            'debugging' => ['enabled' => false],
                            'components' => ['icon' => ['integrations' => []]],
                        ],
                        'UncannyAutomator' => [],
                    ],
                ]
            );
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();
    }

    public function enqueueCanvasEditorWindowedPageAssets(string $hookSuffix): void
    {
        $isWindowedHost = $hookSuffix === $this->canvasEditorWindowedPageHookSuffix
            || $hookSuffix === $this->canvasEditorWindowedGlobalPartPageHookSuffix;
        if (!$isWindowedHost) {
            return;
        }

        $stylePath = 'assets/css/admin-canvas-editor-windowed-page.css';
        wp_enqueue_style(
            'uncanny-page-builder-admin-canvas-editor-windowed-page',
            UNCANNY_PB_URL . $stylePath,
            [],
            (string) filemtime(UNCANNY_PB_PATH . $stylePath),
        );

        // ── Native WordPress ownership modal ────────────────────────────────

        /*
         * The server-rendered dialog remains the no-JavaScript fallback. These
         * assets progressively enhance it with the same Modal and Button
         * components Gutenberg uses for post-lock ownership.
         */
        wp_enqueue_style('wp-base-styles');
        wp_enqueue_style('wp-components');

        $scriptPath = 'assets/js/editor-lock-dialog.js';
        wp_enqueue_script(
            'uncanny-page-builder-editor-lock-dialog',
            UNCANNY_PB_URL . $scriptPath,
            ['wp-element', 'wp-components'],
            (string) filemtime(UNCANNY_PB_PATH . $scriptPath),
            true,
        );
    }

    private function reorderSubmenu(): void
    {
        global $submenu;

        if (!isset($submenu[self::MENU_SLUG]) || !is_array($submenu[self::MENU_SLUG])) {
            return;
        }

        $ordered = [];
        // WordPress mirrors the top-level route into the submenu; keep the parent without a duplicate child.
        $remaining = array_filter(
            $submenu[self::MENU_SLUG],
            static fn (mixed $item): bool => !is_array($item) || ($item[2] ?? null) !== self::MENU_SLUG,
        );

        $desiredOrder = [
            'edit.php?post_type=upb_global_part',
            self::SETTINGS_NEW_SLUG,
        ];

        foreach ($desiredOrder as $slug) {
            foreach ($remaining as $index => $item) {
                if (($item[2] ?? null) !== $slug) {
                    continue;
                }

                $ordered[] = $item;
                unset($remaining[$index]);
                break;
            }
        }

        $submenu[self::MENU_SLUG] = array_values(array_merge($ordered, $remaining));
    }

    private function suppressAdminNotices(): void
    {
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
    }

    private function renderCanvasEditorWindowedNotice(): void
    {
        $errorCode = is_string($_GET[AdminCanvasEditorWindowedPage::ERROR_QUERY_ARG] ?? null)
            ? (string) $_GET[AdminCanvasEditorWindowedPage::ERROR_QUERY_ARG]
            : '';

        $pageId = absint($_GET[AdminCanvasEditorWindowedPage::ERROR_PAGE_ID_QUERY_ARG] ?? 0);
        $message = AdminCanvasEditorWindowedPage::errorNoticeHtml($errorCode, $pageId);
        if ($message === '') {
            return;
        }

        echo '<div class="notice notice-error"><p>' . wp_kses_post($message) . '</p></div>';
    }

    private function isNativePagesScreen(): bool
    {
        global $pagenow;

        return $pagenow === 'edit.php'
            && is_string($_GET['post_type'] ?? null)
            && $_GET['post_type'] === 'page';
    }

    private function isCanvasEditorWindowedRequest(): bool
    {
        return is_string($_GET['page'] ?? null)
            && $_GET['page'] === AdminCanvasEditorWindowedPage::PAGE_SLUG;
    }

    private function isCanvasEditorWindowedGlobalPartRequest(): bool
    {
        return is_string($_GET['page'] ?? null)
            && $_GET['page'] === AdminCanvasEditorWindowedGlobalPartPage::PAGE_SLUG;
    }

    private function attachCanvasEditorToMenuState(string $parentMenuSlug, string $pageSlug): void
    {
        global $submenu;

        $this->removeCanvasEditorFromHiddenSubmenuBucket($submenu, $pageSlug);

        if (!isset($submenu[$parentMenuSlug]) || !is_array($submenu[$parentMenuSlug])) {
            return;
        }

        foreach ($submenu[$parentMenuSlug] as $item) {
            if (($item[2] ?? null) === $pageSlug) {
                return;
            }
        }

        /*
         * WordPress recalculates the active parent after parent_file filters.
         * This non-renderable ghost item lets core preserve the owning menu
         * without exposing the hidden canvas host as a submenu row.
         */
        $submenu[$parentMenuSlug][] = [
            '',
            'do_not_allow',
            $pageSlug,
            _x('Manual editor', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @param array<string, mixed> $submenu
     */
    private function removeCanvasEditorFromHiddenSubmenuBucket(array &$submenu, string $pageSlug): void
    {
        if (!isset($submenu['']) || !is_array($submenu[''])) {
            return;
        }

        foreach ($submenu[''] as $index => $item) {
            if (is_array($item) && ($item[2] ?? null) === $pageSlug) {
                unset($submenu[''][$index]);
            }
        }

        if ($submenu[''] === []) {
            unset($submenu['']);
            return;
        }

        $submenu[''] = array_values($submenu['']);
    }

    /**
     * @return array<int, array{id: string, label: string, url: string}>
     */
    private function settingsTabs(): array
    {
        return [
            [
                'id' => 'design',
                'label' => _x('Brand styles', 'Page Builder', 'uncanny-automator'),
                'url' => $this->settingsTabUrl('design'),
            ],
            [
                'id' => 'text-styles',
                'label' => _x('Typography', 'Page Builder', 'uncanny-automator'),
                'url' => $this->settingsTabUrl('text-styles'),
            ],
            [
                'id' => 'personalization',
                'label' => _x('Design direction', 'Page Builder', 'uncanny-automator'),
                'url' => $this->settingsTabUrl('personalization'),
            ],
            [
                'id' => 'layout',
                'label' => _x('Page layout', 'Page Builder', 'uncanny-automator'),
                'url' => $this->settingsTabUrl('layout'),
            ],
            [
                'id' => 'post-types',
                'label' => _x('Post types', 'Page Builder', 'uncanny-automator'),
                'url' => $this->settingsTabUrl('post-types'),
            ],
            [
                'id' => 'javascript',
                'label' => _x('JavaScript', 'Page Builder', 'uncanny-automator'),
                'url' => $this->settingsTabUrl('javascript'),
            ],
            [
                'id' => 'tools',
                'label' => _x('Tools', 'Page Builder', 'uncanny-automator'),
                'url' => $this->settingsTabUrl('tools'),
            ],
        ];
    }

    /**
     * @param array<int, array{id: string, label: string, url: string}> $tabs
     */
    private function currentSettingsTab(array $tabs): string
    {
        $requested = isset($_GET['settings'])
            ? sanitize_key(wp_unslash($_GET['settings']))
            : 'design';
        $allowed = array_column($tabs, 'id');

        return in_array($requested, $allowed, true) ? $requested : 'design';
    }

    private function settingsTabUrl(string $settingsId): string
    {
        $url = admin_url('admin.php?page=' . self::SETTINGS_NEW_SLUG . '&settings=' . rawurlencode($settingsId));

        if ($settingsId === 'design') {
            return $url . '&section=logo';
        }

        return $url;
    }

    /**
     * @return array<int, array{id: string, label: string, url: string, icon: string}>
     */
    private function designSections(): array
    {
        return [
            [
                'id' => 'logo',
                'label' => _x('Logo', 'Page Builder', 'uncanny-automator'),
                'url' => $this->designSectionUrl('logo'),
                'icon' => 'image',
            ],
            [
                'id' => 'fonts',
                'label' => _x('Fonts', 'Page Builder', 'uncanny-automator'),
                'url' => $this->designSectionUrl('fonts'),
                'icon' => 'font',
            ],
            [
                'id' => 'colors-components',
                'label' => _x('Colors', 'Page Builder', 'uncanny-automator'),
                'url' => $this->designSectionUrl('colors-components'),
                'icon' => 'droplet',
            ],
        ];
    }

    /**
     * Text style groups, each its own sidebar section.
     *
     * Keys match the typography group keys the fields partial renders, with
     * links kept last since it is a token group rather than a typography role.
     *
     * @return array<int, array{id: string, label: string, url: string, icon: string}>
     */
    private function textStyleSections(): array
    {
        return [
            [
                'id' => 'headings',
                'label' => _x('Headings', 'Page Builder', 'uncanny-automator'),
                'url' => $this->textStyleSectionUrl('headings'),
                'icon' => 'text-size',
            ],
            [
                'id' => 'body',
                'label' => _x('Body text', 'Page Builder', 'uncanny-automator'),
                'url' => $this->textStyleSectionUrl('body'),
                'icon' => 'text',
            ],
            [
                'id' => 'small-text',
                'label' => _x('Small text', 'Page Builder', 'uncanny-automator'),
                'url' => $this->textStyleSectionUrl('small-text'),
                'icon' => 'text-height',
            ],
            [
                'id' => 'navigation',
                'label' => _x('Navigation & buttons', 'Page Builder', 'uncanny-automator'),
                'url' => $this->textStyleSectionUrl('navigation'),
                'icon' => 'list',
            ],
            [
                'id' => 'links',
                'label' => _x('Links', 'Page Builder', 'uncanny-automator'),
                'url' => $this->textStyleSectionUrl('links'),
                'icon' => 'code',
            ],
        ];
    }

    private function textStyleSectionUrl(string $sectionId): string
    {
        return admin_url(
            'admin.php?page=' . self::SETTINGS_NEW_SLUG
            . '&settings=text-styles&section=' . rawurlencode($sectionId)
        );
    }

    /**
     * @param array<int, array{id: string, label: string, url: string, icon: string}> $sections
     */
    private function currentSectionId(array $sections, string $default): string
    {
        $requested = isset($_GET['section'])
            ? sanitize_key(wp_unslash($_GET['section']))
            : $default;

        return in_array($requested, array_column($sections, 'id'), true) ? $requested : $default;
    }

    /**
     * Sidebar items for the active top-level tab.
     *
     * Brand styles and Text styles each expose their own sections; every other
     * tab mirrors the Automator settings pattern of a single default entry.
     *
     * @return array{items: array<int, array{id: string, label: string, url: string, icon: string}>, active: string, parameter: string}
     */
    private function settingsSidebar(string $activeTab, string $activeDesignSection): array
    {
        if ($activeTab === 'design') {
            return [
                'items' => $this->designSections(),
                'active' => $activeDesignSection,
                'parameter' => 'section',
            ];
        }

        if ($activeTab === 'text-styles') {
            $sections = $this->textStyleSections();

            return [
                'items' => $sections,
                'active' => $this->currentSectionId($sections, 'headings'),
                'parameter' => 'section',
            ];
        }

        return [
            'items' => [
                [
                    'id' => 'general',
                    'label' => _x('General', 'Page Builder', 'uncanny-automator'),
                    'url' => $this->settingsTabUrl($activeTab),
                    'icon' => 'gear',
                ],
            ],
            'active' => 'general',
            'parameter' => 'settings',
        ];
    }

    /**
     * @param array<int, array{id: string, label: string, url: string}> $sections
     */
    private function currentDesignSection(array $sections): string
    {
        $requested = isset($_GET['section'])
            ? sanitize_key(wp_unslash($_GET['section']))
            : 'logo';
        $allowed = array_column($sections, 'id');

        return in_array($requested, $allowed, true) ? $requested : 'logo';
    }

    private function designSectionUrl(string $sectionId): string
    {
        return admin_url(
            'admin.php?page=' . self::SETTINGS_NEW_SLUG
            . '&settings=design&section=' . rawurlencode($sectionId)
        );
    }

    /**
     * @return array{title: string, description: string}
     */
    private function staticSettingsSection(string $settingsId): array
    {
        return match ($settingsId) {
            'design' => [
                'title' => _x('Brand styles', 'Page Builder', 'uncanny-automator'),
                'description' => _x('Brand styles will live here.', 'Page Builder', 'uncanny-automator'),
            ],
            'personalization' => [
                'title' => _x('Design direction', 'Page Builder', 'uncanny-automator'),
                'description' => _x('Add design notes Uncanny Agent should follow when creating new pages.', 'Page Builder', 'uncanny-automator'),
            ],
            'javascript' => [
                'title' => _x('JavaScript', 'Page Builder', 'uncanny-automator'),
                'description' => _x('JavaScript runtime settings will live here.', 'Page Builder', 'uncanny-automator'),
            ],
            'tools' => [
                'title' => _x('Tools', 'Page Builder', 'uncanny-automator'),
                'description' => _x('Maintenance tools for Page Builder pages will live here.', 'Page Builder', 'uncanny-automator'),
            ],
            default => [
                'title' => _x('Settings', 'Page Builder', 'uncanny-automator'),
                'description' => _x('Settings will live here.', 'Page Builder', 'uncanny-automator'),
            ],
        };
    }
}
