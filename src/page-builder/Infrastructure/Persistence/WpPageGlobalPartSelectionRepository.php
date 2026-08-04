<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelection;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelectionRepositoryInterface;

/**
 * Stores page header/footer overrides in WordPress post meta.
 */
final class WpPageGlobalPartSelectionRepository implements PageGlobalPartSelectionRepositoryInterface
{
    private const META_HEADER = '_uncanny_engine_page_header_id';
    private const META_FOOTER = '_uncanny_engine_page_footer_id';

    public function loadForPage(int $pageId): PageGlobalPartSelection
    {
        $this->assertPageId($pageId);

        return new PageGlobalPartSelection(
            $this->readOverride($pageId, self::META_HEADER),
            $this->readOverride($pageId, self::META_FOOTER),
        );
    }

    public function saveForPage(int $pageId, PageGlobalPartSelection $selection): void
    {
        $this->assertPageId($pageId);

        $this->writeOverride($pageId, self::META_HEADER, $selection->headerOverrideId());
        $this->writeOverride($pageId, self::META_FOOTER, $selection->footerOverrideId());

        if (!$this->freshSelection($pageId)->equals($selection)) {
            throw new WordPressWriteVerificationException('WordPress did not persist the page header/footer selection.');
        }
    }

    private function readOverride(int $pageId, string $metaKey): ?int
    {
        $value = get_post_meta($pageId, $metaKey, true);
        if ($value === '' || $value === false || $value === null) {
            return null;
        }

        $overrideId = (int) $value;

        return $overrideId === -1 || $overrideId > 0 ? $overrideId : null;
    }

    private function writeOverride(int $pageId, string $metaKey, ?int $overrideId): void
    {
        $this->clearDatabaseError();
        if ($overrideId === null) {
            delete_post_meta($pageId, $metaKey);
        } else {
            update_post_meta($pageId, $metaKey, $overrideId);
        }

        if ($this->databaseError() !== '') {
            throw new WordPressWriteVerificationException('WordPress did not persist the page header/footer selection.');
        }
    }

    private function freshSelection(int $pageId): PageGlobalPartSelection
    {
        if (\function_exists('wp_cache_delete')) {
            \wp_cache_delete($pageId, 'post_meta');
        }

        return $this->loadForPage($pageId);
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

    private function assertPageId(int $pageId): void
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be positive.');
        }
    }
}
