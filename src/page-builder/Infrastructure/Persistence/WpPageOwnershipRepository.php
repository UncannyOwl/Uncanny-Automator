<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\Canvas\PageOwnershipRepositoryInterface;

/**
 * WordPress post-meta persistence for the page's active editor.
 */
final class WpPageOwnershipRepository implements PageOwnershipRepositoryInterface
{
    public const META_ACTIVE = '_uncanny_page_builder_active';
    public const META_OWNED = '_uncanny_page_builder_owned';

    // Section: Ownership state

    public function isOwned(int $pageId): bool
    {
        return $pageId > 0
            && (bool) get_post_meta($pageId, self::META_OWNED, true);
    }

    public function markOwned(int $pageId): void
    {
        $this->writeFlag($pageId, self::META_OWNED);
    }

    /**
     * The active flag predates explicit ownership and is still written by the
     * section save path. Clear both flags when WordPress resumes management.
     */
    public function markWordPressManaged(int $pageId): void
    {
        $this->deleteFlag($pageId, self::META_ACTIVE);
        $this->deleteFlag($pageId, self::META_OWNED);
    }

    public function markActive(int $pageId): void
    {
        $this->writeFlag($pageId, self::META_ACTIVE);
    }

    // Section: Verified post-meta writes

    private function writeFlag(int $pageId, string $key): void
    {
        if ((string) get_post_meta($pageId, $key, true) === '1') {
            return;
        }

        update_post_meta($pageId, $key, '1');

        if ((string) get_post_meta($pageId, $key, true) !== '1') {
            throw new \RuntimeException('Page ownership could not be saved.');
        }
    }

    private function deleteFlag(int $pageId, string $key): void
    {
        if (!metadata_exists('post', $pageId, $key)) {
            return;
        }

        delete_post_meta($pageId, $key);

        if (metadata_exists('post', $pageId, $key)) {
            throw new \RuntimeException('Page ownership could not be cleared.');
        }
    }
}
