<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Removes WordPress admin-bar behavior from the canvas editor.
 *
 * The canvas runs from wp-admin but renders a frontend-like document. WordPress
 * still treats admin-bar state as active, so the canvas must remove the toolbar,
 * body classes, and admin-bar assets at this infrastructure boundary.
 */
final class CanvasAdminBar
{
    private const HANDLE = 'admin-bar';

    public function removeFromCanvas(): void
    {
        $this->removeRenderHooks();
        $this->removeHeadHooks();
        $this->removeEnqueueHooks();
        $this->removeAssetsWhenPrinted();

        add_filter('body_class', [$this, 'removeBodyClasses'], 1000);
    }

    /**
     * @param array<int, mixed> $classes
     * @return array<int, mixed>
     */
    public function removeBodyClasses(array $classes): array
    {
        $blockedClasses = [
            'admin-bar'            => true,
            'no-customize-support' => true,
        ];

        return array_values(array_filter(
            $classes,
            static fn(mixed $class): bool => !is_string($class) || !isset($blockedClasses[$class])
        ));
    }

    public function removeAssets(): void
    {
        wp_dequeue_style(self::HANDLE);
        wp_dequeue_script(self::HANDLE);
    }

    private function removeRenderHooks(): void
    {
        remove_action('in_admin_header', 'wp_admin_bar_render', 0);
        remove_action('wp_body_open', 'wp_admin_bar_render', 0);
        remove_action('wp_footer', 'wp_admin_bar_render', 1000);
    }

    private function removeHeadHooks(): void
    {
        remove_action('wp_head', 'wp_admin_bar_header');
        remove_action('admin_head', 'wp_admin_bar_header');
        remove_action('wp_head', '_admin_bar_bump_cb');
    }

    private function removeEnqueueHooks(): void
    {
        remove_action('wp_enqueue_scripts', 'wp_enqueue_admin_bar_bump_styles');
        remove_action('wp_enqueue_scripts', 'wp_enqueue_admin_bar_header_styles');
        remove_action('admin_enqueue_scripts', 'wp_enqueue_admin_bar_header_styles');
    }

    private function removeAssetsWhenPrinted(): void
    {
        add_action('wp_print_styles', [$this, 'removeAssets'], PHP_INT_MAX);
        add_action('wp_print_scripts', [$this, 'removeAssets'], PHP_INT_MAX);
        add_action('wp_print_footer_scripts', [$this, 'removeAssets'], PHP_INT_MAX);
    }
}
