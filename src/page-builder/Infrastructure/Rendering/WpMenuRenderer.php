<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

/**
 * Render data-ai-dynamic="wp_menu" regions using wp_nav_menu().
 *
 * WordPress handles all nesting, depth, active-item classes, and
 * accessibility. The AI owns the wrapper and CSS, targeting WordPress's
 * standard menu classes (.menu, .menu-item, .sub-menu, .current-menu-item,
 * etc.) plus any class copied from the placeholder <ul>.
 *
 * Every documented markup-shaping nav menu hook is temporarily suspended
 * during rendering so the output is clean WordPress-standard markup — the
 * binding's Rendered HTML contract that agents author CSS against. Themes
 * inject through all of them: Astra adds toggle buttons via
 * walker_nav_menu_start_el, NewsX wraps titles in icon spans via
 * nav_menu_item_title, and wp_nav_menu_args can force a custom walker that
 * rewrites everything. Content-shaping hooks (wp_get_nav_menu_items,
 * wp_nav_menu_objects, the_title) stay live on purpose: plugins legitimately
 * add or remove menu *items* there, and the contract governs markup shape,
 * not item membership.
 */
final class WpMenuRenderer implements SectionRendererInterface
{
    /**
     * @param string               $cardTemplate Ignored — wp_nav_menu() owns the markup.
     * @param array<string, mixed> $args         Use 'menu_id' for an exact menu, or 'menu_location' for a theme location.
     *                                           Optional: 'menu_class', 'items_wrap_id'.
     */
    public function render(string $cardTemplate, array $args): string
    {
        $menuId = (int) ($args['menu_id'] ?? 0);
        $menuLocation = trim((string) ($args['menu_location'] ?? ''));
        if ($menuId <= 0 && $menuLocation === '') {
            return '<!-- wp_menu: no menu_id or menu_location specified -->';
        }

        $menuClass = trim((string) ($args['menu_class'] ?? ''));
        $itemsWrapId = trim((string) ($args['items_wrap_id'] ?? ''));

        $menuArgs = [
            'depth'       => 0,
            'container'   => false,
            'echo'        => false,
            'fallback_cb' => false,
        ];
        if ($menuId > 0) {
            $menuArgs['menu'] = $menuId;
        } else {
            $menuArgs['theme_location'] = $menuLocation;
        }

        // Pass template classes to wp_nav_menu so the <ul> keeps them.
        if ($menuClass !== '') {
            $menuArgs['menu_class'] = $menuClass;
        }
        if ($itemsWrapId !== '') {
            $menuArgs['items_wrap'] = '<ul id="' . esc_attr($itemsWrapId) . '" class="%2$s">%3$s</ul>';
        }

        $savedFilters = [];
        try {
            $savedFilters = $this->suspendThemeMenuFilters();
            $output = wp_nav_menu($menuArgs);
        } finally {
            $this->restoreThemeMenuFilters($savedFilters);
        }

        if (!is_string($output) || trim($output) === '') {
            if ($menuId > 0) {
                return '<!-- wp_menu: no menu found for id "' . esc_attr((string) $menuId) . '" -->';
            }
            return '<!-- wp_menu: no menu assigned to location "' . esc_attr($menuLocation) . '" -->';
        }

        return $output;
    }

    /**
     * Temporarily remove all third-party filters from nav menu hooks
     * so wp_nav_menu() produces clean WordPress-standard markup.
     *
     * @return array<string, mixed> Saved filter state for restoration.
     */
    private function suspendThemeMenuFilters(): array
    {
        global $wp_filter;

        $hooks = [
            'pre_wp_nav_menu',
            'wp_nav_menu_args',
            'walker_nav_menu_start_el',
            'nav_menu_item_args',
            'nav_menu_item_title',
            'nav_menu_link_attributes',
            'nav_menu_css_class',
            'nav_menu_item_id',
            'nav_menu_submenu_css_class',
            'nav_menu_submenu_attributes',
            'wp_nav_menu_items',
            'wp_nav_menu',
        ];

        $saved = [];
        foreach ($hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                $saved[$hook] = $wp_filter[$hook];
                unset($wp_filter[$hook]);
            }
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $savedFilters
     */
    private function restoreThemeMenuFilters(array $savedFilters): void
    {
        global $wp_filter;

        foreach ($savedFilters as $hook => $filter) {
            $wp_filter[$hook] = $filter;
        }
    }
}
