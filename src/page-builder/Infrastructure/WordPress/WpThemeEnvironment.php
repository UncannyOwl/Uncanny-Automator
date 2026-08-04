<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

final class WpThemeEnvironment
{
    public function isBlockTheme(): bool
    {
        return wp_is_block_theme();
    }

    public function activeThemeName(): string
    {
        return wp_get_theme()->get('Name');
    }

    public function themeExists(): bool
    {
        return wp_get_theme()->exists();
    }

    public function isElementorActive(): bool
    {
        return defined('ELEMENTOR_VERSION');
    }

    public function isElementorPage(int $pageId): bool
    {
        return $this->isElementorActive()
            && get_post_meta($pageId, '_elementor_edit_mode', true) !== '';
    }
}
