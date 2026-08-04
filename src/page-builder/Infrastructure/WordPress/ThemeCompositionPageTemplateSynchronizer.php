<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ThemeCompositionPageTemplateSynchronizerInterface;

final class ThemeCompositionPageTemplateSynchronizer implements ThemeCompositionPageTemplateSynchronizerInterface
{
    private const PAGE_TEMPLATE_META_KEY = '_wp_page_template';

    public function needsPreparation(int $pageId): bool
    {
        if ($pageId <= 0) {
            return false;
        }

        $templateSlug = get_post_meta($pageId, self::PAGE_TEMPLATE_META_KEY, true);
        return is_string($templateSlug) && $templateSlug !== '' && $templateSlug !== 'default';
    }

    public function prepareForThemeComposition(int $pageId): void
    {
        if (!$this->needsPreparation($pageId)) {
            return;
        }

        // Theme composition relies on the theme's normal content pipeline.
        $this->clearDatabaseError();
        update_post_meta($pageId, self::PAGE_TEMPLATE_META_KEY, 'default');

        if ($this->databaseError() !== '' || $this->freshTemplate($pageId) !== 'default') {
            throw new \RuntimeException('WordPress could not prepare the page template for theme composition.');
        }
    }

    private function freshTemplate(int $pageId): mixed
    {
        if (\function_exists('wp_cache_delete')) {
            \wp_cache_delete($pageId, 'post_meta');
        }

        return get_post_meta($pageId, self::PAGE_TEMPLATE_META_KEY, true);
    }

    private function clearDatabaseError(): void
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (is_object($wpdb) && property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
    }

    private function databaseError(): string
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;

        return is_object($wpdb) && property_exists($wpdb, 'last_error')
            ? (string) $wpdb->last_error
            : '';
    }
}
