<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Publishing\PageDraftStatusPortInterface;

/** WordPress adapter for page visibility independent of Page Builder content. */
final class WordPressPageDraftStatusPort implements PageDraftStatusPortInterface
{
    public function currentStatus(int $pageId): string
    {
        global $wpdb;

        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT post_status FROM {$postsTable} WHERE ID = %d LIMIT 1",
            $pageId,
        ));
        if (!is_string($status) || $status === '') {
            throw new \RuntimeException('Page not found.');
        }

        return $status;
    }

    public function setDraft(int $pageId): void
    {
        $this->setStatus($pageId, 'draft');
    }

    public function setPublished(int $pageId): void
    {
        $this->setStatus($pageId, 'publish');
    }

    private function setStatus(int $pageId, string $status): void
    {
        global $wpdb;

        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $updated = $wpdb->update(
            $postsTable,
            ['post_status' => $status],
            ['ID' => $pageId],
            ['%s'],
            ['%d'],
        );
        if ($updated === false) {
            throw new \RuntimeException('Page status update failed.');
        }

        clean_post_cache($pageId);
    }
}
