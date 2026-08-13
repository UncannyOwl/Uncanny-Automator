<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\GetAvailableFontFamilies;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\DesignStandards\BootstrapTokenProfile;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsProfile;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\i18\PageBuilderJsStrings;

final class MagicBridgeEnqueuer
{
    private const DESIGN_LENS_SCRIPT_HANDLE = 'uncanny-page-builder-design-lens';
    private const BRIDGE_SCRIPT_HANDLE = 'uncanny-page-builder-bridge';

    private const EDITOR_SCRIPT_DEPENDENCIES = [
        'wp-a11y',
        'wp-element',
        'wp-components',
        'wp-block-editor',
        'heartbeat',
    ];

    private const BRAND_COLOR_LABELS = [
        '--bs-primary' => 'Primary',
        '--bs-secondary' => 'Secondary',
        '--bs-success' => 'Success',
        '--bs-info' => 'Info',
        '--bs-warning' => 'Warning',
        '--bs-danger' => 'Danger',
        '--bs-light' => 'Light',
        '--bs-dark' => 'Dark',
        '--bs-body-color' => 'Body color',
        '--bs-body-bg' => 'Body background',
        '--bs-link-color' => 'Link color',
        '--bs-link-hover-color' => 'Link hover color',
        '--bs-border-color' => 'Border color',
        '--bs-heading-color' => 'Heading color',
        '--bs-emphasis-color' => 'Emphasis color',
    ];

    private const EDITOR_STYLE_DEPENDENCIES = [
        // The background image picker opens WordPress's media modal from a
        // standalone canvas document. Treat the modal stylesheet as editor
        // chrome so hidden headings, title positioning, and attachment grid
        // rules are present whenever the bridge is loaded.
        'media-views',

        // Defines --wp-admin-theme-color (scoped to body.admin-color-*). The
        // canvas is an admin standalone document that exits before
        // admin-header.php, so this baseline is never printed unless we pull
        // it in explicitly — without it, @wordpress/components falls back to
        // its hardcoded accent color (#3858e9).
        'wp-base-styles',
        'wp-components',
        'wp-block-editor',
    ];

    public function __construct(
        private readonly string $pluginUrl,
        private readonly string $version,
        private readonly SectionRepositoryInterface $repository,
        private readonly ?ShellModeService $shellModeService = null,
        private readonly ?GlobalPartDefaultsService $globalPartDefaultsService = null,
        private readonly ?GlobalPartRepositoryInterface $globalPartRepo = null,
        private readonly ?DesignStandardsService $designStandardsService = null,
        private readonly ?GetPageBuilderAllowedCapabilities $allowedCapabilities = null,
        private readonly ?GetAvailableFontFamilies $availableFontFamilies = null,
        private readonly ?PageBuilderJsStrings $jsStrings = null,
        private readonly ?PageDetailsPortInterface $pageDetails = null,
    ) {}

    public function enqueue(): void
    {
        try {
            if ($this->isPreviewRequest()) {
                return;
            }

            if (!is_singular()) {
                return;
            }

            $postId = WordPressPostId::fromCurrentQuery(get_queried_object_id());
            if ($postId === null) {
                return;
            }

            // Global part canvas — bypass page ownership check.
            $isGlobalPart = is_singular('upb_global_part');

            if (!$isGlobalPart && !$this->repository->isOwnedPage($postId)) {
                return;
            }

            if (!$this->canUsePageBuilder()) {
                return;
            }

            $this->enqueueAssets();

            // The canvas exits before admin-header.php, so the admin color scheme
            // baseline (which @wordpress/components reads for its accent color) is
            // never applied to <body>. Re-add the admin shell body classes so the
            // body.admin-color-{scheme} rules in wp-base-styles resolve.
            add_filter('body_class', [$this, 'addAdminBaselineBodyClasses']);

            if ($isGlobalPart) {
                $this->localizeForGlobalPart($postId);
            } else {
                $this->localizeForPage($postId);
            }
        } catch (\Throwable $failure) {
            // wp_enqueue_scripts is a shared WordPress surface. A Page
            // Builder failure must not terminate the request.
            error_log('[Uncanny Page Builder] Magic Bridge enqueue failed (' . $failure::class . ')');
        }
    }

    private function enqueueAssets(): void
    {
        wp_enqueue_media();

        // ── Standalone canvas Heartbeat bootstrap ────────────────────────────

        /*
         * The canvas is an admin request, but it exits before admin-header.php.
         * Core therefore omits both heartbeatSettings.ajaxurl and the normal
         * window.ajaxurl global. Define the latter before Heartbeat initializes
         * so editor-lock requests reach admin-ajax.php instead of POSTing back
         * to the canvas document.
         */
        $heartbeatAjaxUrl = wp_json_encode(admin_url('admin-ajax.php'));
        if (is_string($heartbeatAjaxUrl)) {
            wp_add_inline_script(
                'heartbeat',
                'window.ajaxurl = window.ajaxurl || ' . $heartbeatAjaxUrl . ';',
                'before',
            );
        }

        // ── Editor chrome stylesheet ──────────────────────────────────────────

        $bridgeStyle = 'assets/css/magic-bridge.css';

        /*
         * WordPress silently skips a style (and everything depending on it)
         * when ANY listed dependency handle is unregistered — one optional
         * handle missing on an older core (wp-base-styles) would drop the
         * entire chrome chain, wp-components included, and render the editor
         * naked. Filter to registered handles so a missing one degrades to
         * its own small loss instead.
         */
        wp_enqueue_style(
            'uncanny-page-builder-bridge',
            $this->pluginUrl . $bridgeStyle,
            self::registeredStyleHandles(self::EDITOR_STYLE_DEPENDENCIES),
            $this->assetVersion($bridgeStyle)
        );

        // ── Design Lens runtime ──────────────────────────────────────────────

        $designLensScript = 'assets/js/design-lens.iife.min.js';

        wp_enqueue_script(
            self::DESIGN_LENS_SCRIPT_HANDLE,
            $this->pluginUrl . $designLensScript,
            [],
            $this->assetVersion($designLensScript),
            true
        );

        // ── Editor client SDK ─────────────────────────────────────────────────

        $bridgeScript = 'assets/js/uncanny-pb-sdk.js';

        wp_enqueue_script(
            self::BRIDGE_SCRIPT_HANDLE,
            $this->pluginUrl . $bridgeScript,
            array_merge(
                self::registeredScriptHandles(self::EDITOR_SCRIPT_DEPENDENCIES),
                [self::DESIGN_LENS_SCRIPT_HANDLE]
            ),
            $this->assetVersion($bridgeScript),
            true
        );
    }

    /**
     * @param list<string> $handles
     * @return list<string>
     */
    private static function registeredStyleHandles(array $handles): array
    {
        return array_values(array_filter(
            $handles,
            static fn (string $handle): bool => wp_style_is($handle, 'registered'),
        ));
    }

    /**
     * @param list<string> $handles
     * @return list<string>
     */
    private static function registeredScriptHandles(array $handles): array
    {
        return array_values(array_filter(
            $handles,
            static fn (string $handle): bool => wp_script_is($handle, 'registered'),
        ));
    }

    private function assetVersion(string $relativePath): string
    {
        if (!defined('UNCANNY_PB_PATH')) {
            return $this->version;
        }

        $path = UNCANNY_PB_PATH . ltrim($relativePath, '/');
        if (!is_file($path)) {
            return $this->version;
        }

        $mtime = filemtime($path);
        return $mtime ? $this->version . '-' . $mtime : $this->version;
    }

    private function isPreviewRequest(): bool
    {
        return isset($_GET['upb_preview']);
    }

    private function localizeForPage(int $postId): int
    {
        $collection = $this->repository->findByPageId($postId);

        $sectionData = [];
        foreach ($collection->all() as $section) {
            $sectionData[] = [
                'id'   => $section->id(),
                'name' => $section->name(),
            ];
        }

        $shellMode = 'none';
        $shellModeLabel = ShellMode::None->label();
        $themeName = '';
        $shellProvider = 'theme';
        if ($this->shellModeService !== null) {
            $ctx = $this->shellModeService->resolveForPage($postId);
            $shellMode = $ctx->mode->value;
            $shellModeLabel = $ctx->mode->label();
            $themeName = wp_get_theme()->get('Name');
            $shellProvider = $this->shellModeService->detectProviderForPage($postId)->value;
        }

        $bridgeData = [
            'editorScope'   => 'page',
            'pageId'        => $postId,
            'pageTitle'     => $this->draftPageTitle($postId),
            'dashboardUrl'  => admin_url('admin.php?page=uncanny-page-builder'),
            'restUrl'       => esc_url_raw(rest_url()),
            'nonce'         => wp_create_nonce('wp_rest'),
            'nonceRefreshUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonceRefreshAction' => RestNonceRefresher::ACTION,
            'sections'      => $sectionData,
            'canManage'     => $this->canUsePageBuilder(),
            'shellMode'     => $shellMode,
            'shellModeLabel' => $shellModeLabel,
            'canvasTypeLabel' => 'Page',
            'themeName'     => $themeName,
            'shellProvider' => $shellProvider,
            'postStatus'    => get_post_status($postId) ?: 'draft',
            'hasDefaultHeader' => $this->hasDefault(GlobalPartType::Header),
            'hasDefaultFooter' => $this->hasDefault(GlobalPartType::Footer),
            'siteName'         => get_bloginfo('name') ?: 'My Site',
            'strings'        => $this->bridgeStrings(),
        ];

        $filteredBridgeData = apply_filters('uncanny_page_builder_bridge_data', $bridgeData, $postId);
        if (is_array($filteredBridgeData)) {
            $bridgeData = $filteredBridgeData;
        }

        $this->addBrandingInlineScript($postId);
        $this->addTypographyInlineScript();
        wp_localize_script(self::BRIDGE_SCRIPT_HANDLE, 'uncannyBridge', $bridgeData);

        return count($sectionData);
    }

    private function localizeForGlobalPart(int $globalPartId): int
    {
        $sectionData = [];
        $globalPartType = '';

        if ($this->globalPartRepo !== null) {
            $part = $this->globalPartRepo->findById($globalPartId);
            if ($part !== null) {
                $globalPartType = $part['type'] ?? '';
                foreach ($part['sections'] ?? [] as $section) {
                    $html = $section['content']['html'] ?? '';
                    $css  = $section['content']['css'] ?? '';
                    $sectionData[] = [
                        'id'      => $section['id'],
                        'name'    => $section['name'] ?? '',
                        'content' => ['html' => $html, 'css' => $css],
                    ];
                }
            }
        }

        $bridgeData = [
            'editorScope'     => 'global_part',
            'pageId'          => 0,
            'globalPartId'    => $globalPartId,
            'globalPartType'  => $globalPartType,
            'globalPartEditUrl' => admin_url('post.php?post=' . $globalPartId . '&action=edit'),
            'globalPartListUrl' => admin_url('edit.php?post_type=upb_global_part'),
            'pageTitle'       => $this->postTitle($globalPartId, false),
            'dashboardUrl'    => admin_url('admin.php?page=uncanny-page-builder'),
            'restUrl'         => esc_url_raw(rest_url()),
            'nonce'           => wp_create_nonce('wp_rest'),
            'nonceRefreshUrl'  => esc_url_raw(admin_url('admin-ajax.php')),
            'nonceRefreshAction' => RestNonceRefresher::ACTION,
            'sections'        => $sectionData,
            'canManage'       => $this->canUsePageBuilder(),
            'shellMode'       => 'uncanny_native',
            'shellModeLabel'   => ShellMode::UncannyNative->label(),
            'canvasTypeLabel'  => $this->globalPartCanvasTypeLabel($globalPartType),
            'themeName'       => '',
            'shellProvider'   => '',
            'postStatus'      => get_post_status($globalPartId) ?: 'publish',
            'hasDefaultHeader' => false,
            'hasDefaultFooter' => false,
            'siteName'         => get_bloginfo('name') ?: 'My Site',
            'strings'         => $this->bridgeStrings(),
        ];

        $filteredBridgeData = apply_filters('uncanny_page_builder_bridge_data', $bridgeData, $globalPartId);
        if (is_array($filteredBridgeData)) {
            $bridgeData = $filteredBridgeData;
        }

        $this->addBrandingInlineScript(0);
        $this->addTypographyInlineScript();
        wp_localize_script(self::BRIDGE_SCRIPT_HANDLE, 'uncannyBridge', $bridgeData);

        return count($sectionData);
    }

    private function postTitle(int $postId, bool $isPage): string
    {
        $title = get_the_title($postId);
        if (!is_string($title) || $title === '') {
            return $isPage
                ? sprintf(
                    /* translators: %d: Page ID. */
                    _x('Untitled page #%d', 'Page Builder', 'uncanny-automator'),
                    $postId,
                )
                : 'Untitled';
        }

        // The bridge payload is JSON consumed by browser UI. Decode WordPress'
        // HTML display entities before the title reaches text-only renderers.
        return html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    }

    private function draftPageTitle(int $pageId): string
    {
        return $this->pageDetails?->find($pageId)?->title()
            ?? $this->postTitle($pageId, true);
    }

    private function canUsePageBuilder(): bool
    {
        return $this->allowedCapabilities?->currentUserHasAllowedCapability() ?? false;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function bridgeStrings(): array
    {
        return ($this->jsStrings ?? new PageBuilderJsStrings())->bridgePayload();
    }

    private function globalPartCanvasTypeLabel(string $globalPartType): string
    {
        $label = trim(str_replace('_', ' ', $globalPartType));

        return $label !== ''
            ? 'Reusable ' . strtolower($label)
            : 'Reusable section';
    }

    private function addBrandingInlineScript(int $pageId): void
    {
        $config = $this->buildBrandingPaletteConfig($pageId);
        $json = wp_json_encode($config);

        if (!is_string($json) || $json === '') {
            return;
        }

        // This is a UI runtime seed, not persisted state. It must be available
        // before the module bundle evaluates so workspace color controls can
        // render the active brand palette on first paint.
        wp_add_inline_script(
            self::BRIDGE_SCRIPT_HANDLE,
            'window.uncannyPageBuilderBranding = ' . $json . ';',
            'before'
        );
    }

    private function addTypographyInlineScript(): void
    {
        $config = $this->buildTypographyRuntimeConfig();
        $json = wp_json_encode($config);

        if (!is_string($json) || $json === '') {
            return;
        }

        wp_add_inline_script(
            self::BRIDGE_SCRIPT_HANDLE,
            'window.uncannyPageBuilderTypography = ' . $json . ';',
            'before'
        );
    }

    /**
     * @return array{colors: list<array{name: string, slug: string, color: string, token: string}>, gradients: list<array{name: string, slug: string, gradient: string}>, settingsUrl: string}
     */
    private function buildBrandingPaletteConfig(int $pageId): array
    {
        $profile = $this->designStandardsService instanceof DesignStandardsService
            ? $this->designStandardsService->resolveForPage($pageId)
            : DesignStandardsProfile::defaults();

        $colors = $this->buildBrandColors($profile->tokens()->toArray());

        return [
            'colors' => $colors,
            'gradients' => $this->buildBrandGradients($colors),
            'settingsUrl' => admin_url(
                'admin.php?page=uncanny-page-builder-settings&settings=text-styles'
            ),
        ];
    }

    /**
     * @return array{fontFamilyCatalog: list<array{key: string, label: string, options: list<array{label: string, value: string, source: string}>}>}
     */
    private function buildTypographyRuntimeConfig(): array
    {
        return [
            'fontFamilyCatalog' => $this->availableFontFamilies?->catalog() ?? [],
        ];
    }

    /**
     * @param array<string, string> $tokens
     * @return list<array{name: string, slug: string, color: string, token: string}>
     */
    private function buildBrandColors(array $tokens): array
    {
        $colors = [];
        $seen = [];

        foreach (BootstrapTokenProfile::colorTokenKeys() as $token) {
            $value = $tokens[$token] ?? '';
            if (!is_string($value) || !$this->isPaletteColorValue($value)) {
                continue;
            }

            $colorKey = strtolower(preg_replace('/\s+/', '', $value) ?: $value);
            if (isset($seen[$colorKey])) {
                continue;
            }

            $seen[$colorKey] = true;
            $colors[] = [
                'name' => self::BRAND_COLOR_LABELS[$token] ?? $this->labelFromToken($token),
                'slug' => $this->slugFromToken($token),
                'color' => $value,
                'token' => $token,
            ];
        }

        return $colors;
    }

    /**
     * @param list<array{name: string, slug: string, color: string, token: string}> $colors
     * @return list<array{name: string, slug: string, gradient: string}>
     */
    private function buildBrandGradients(array $colors): array
    {
        $byToken = [];
        foreach ($colors as $color) {
            $byToken[$color['token']] = $color['color'];
        }

        $gradients = [];
        $this->addBrandGradient(
            $gradients,
            'Primary blend',
            'primary-blend',
            $byToken['--bs-primary'] ?? '',
            $byToken['--bs-secondary'] ?? ''
        );
        $this->addBrandGradient(
            $gradients,
            'Brand wash',
            'brand-wash',
            $byToken['--bs-primary'] ?? '',
            $byToken['--bs-body-bg'] ?? ''
        );
        $this->addBrandGradient(
            $gradients,
            'Deep brand',
            'deep-brand',
            $byToken['--bs-dark'] ?? '',
            $byToken['--bs-primary'] ?? ''
        );

        return $gradients;
    }

    /**
     * @param list<array{name: string, slug: string, gradient: string}> $gradients
     */
    private function addBrandGradient(array &$gradients, string $name, string $slug, string $from, string $to): void
    {
        if (!$this->isPaletteColorValue($from) || !$this->isPaletteColorValue($to) || $from === $to) {
            return;
        }

        $gradients[] = [
            'name' => $name,
            'slug' => $slug,
            'gradient' => sprintf('linear-gradient(135deg, %s 0%%, %s 100%%)', $from, $to),
        ];
    }

    private function isPaletteColorValue(string $value): bool
    {
        $value = trim($value);

        return (bool) preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)
            || (bool) preg_match('/^(?:rgb|rgba|hsl|hsla|color)\(/i', $value)
            || $value === 'transparent';
    }

    private function labelFromToken(string $token): string
    {
        $label = preg_replace('/^--bs-/', '', $token) ?: $token;
        $label = str_replace('-', ' ', $label);

        return ucwords($label);
    }

    private function slugFromToken(string $token): string
    {
        $slug = preg_replace('/^--bs-/', '', $token) ?: $token;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?: 'color';
        $slug = strtolower(trim($slug, '-'));

        return $slug !== '' ? $slug : 'color';
    }

    /**
     * Add the wp-admin shell body classes the standalone canvas document
     * skips by exiting before admin-header.php. `wp-core-ui` scopes core
     * component styling; `admin-color-{scheme}` makes the wp-base-styles
     * rules that define --wp-admin-theme-color resolve to the user's scheme.
     *
     * @param array<int, string> $classes
     * @return array<int, string>
     */
    public function addAdminBaselineBodyClasses($classes = null): array
    {
        $classes = is_array($classes) ? $classes : [];

        $classes[] = 'wp-core-ui';

        $scheme = get_user_option('admin_color');
        $scheme = is_string($scheme) && $scheme !== '' ? $scheme : 'fresh';
        $classes[] = 'admin-color-' . sanitize_html_class($scheme);

        return $classes;
    }

    private function hasDefault(GlobalPartType $type): bool
    {
        if ($this->globalPartDefaultsService === null) {
            return false;
        }

        // Check if a specific default is set and still valid.
        if ($this->globalPartDefaultsService->resolveForType($type) !== null) {
            return true;
        }

        // Fallback: check if ANY global part of this type exists.
        $parts = $this->globalPartDefaultsService->listByType($type);
        return $parts !== [];
    }
}
