<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Canvas\OriginalPageContentStoreInterface;
use UncannyPageBuilder\Application\Canvas\OriginalPageContentReaderInterface;

/**
 * Keeps the exact WordPress body available while Page Builder owns the page.
 *
 * The backup is removed only after its content has been restored successfully.
 * A structured value distinguishes a valid empty body from a missing backup.
 */
final class WpOriginalPageContentStore implements OriginalPageContentStoreInterface, OriginalPageContentReaderInterface
{
    public const META_KEY = '_uncanny_page_builder_original_post_content';

    private const BACKUP_VERSION = 1;

    private static int $writeDepth = 0;

    public static function isWriting(): bool
    {
        return self::$writeDepth > 0;
    }

    // Section: Backup lifecycle

    public function preserve(int $pageId): string
    {
        $content = $this->readPostContent($pageId);

        if (metadata_exists('post', $pageId, self::META_KEY)) {
            $existing = $this->readBackup($pageId);
            if ($existing !== $content) {
                throw new \RuntimeException(
                    'A previous Page Builder editor change did not finish. The saved WordPress content was left untouched.',
                );
            }

            return $content;
        }

        $backup = [
            'version' => self::BACKUP_VERSION,
            'content' => $content,
        ];

        update_post_meta($pageId, self::META_KEY, wp_slash($backup));

        if (!metadata_exists('post', $pageId, self::META_KEY) || $this->readBackup($pageId) !== $content) {
            throw new \RuntimeException('The WordPress page body could not be preserved.');
        }

        return $content;
    }

    public function restore(int $pageId): void
    {
        $hasBackup = metadata_exists('post', $pageId, self::META_KEY);
        $activeContent = $this->readPostContent($pageId);
        $content = $hasBackup ? $this->readBackup($pageId) : $activeContent;

        // Pages created by Page Builder have no WordPress backup. Clear only
        // a recognizable Page Builder artifact; legacy owned pages may still
        // contain valuable pre-adoption WordPress content from older releases.
        if (!$hasBackup && !$this->isPageBuilderArtifact($activeContent)) {
            return;
        }

        if (!$hasBackup) {
            $content = '';
        }

        if ($activeContent === $content) {
            return;
        }

        $this->writePostContent($pageId, $content);
    }

    public function discardBackup(int $pageId): void
    {
        if (!metadata_exists('post', $pageId, self::META_KEY)) {
            return;
        }

        delete_post_meta($pageId, self::META_KEY);
        if (metadata_exists('post', $pageId, self::META_KEY)) {
            throw new \RuntimeException('The restored WordPress page body backup could not be cleared.');
        }
    }

    public function publicContent(int $pageId): string
    {
        if (metadata_exists('post', $pageId, self::META_KEY)) {
            return $this->readBackup($pageId);
        }

        $activeContent = $this->readPostContent($pageId);

        return $this->isPageBuilderArtifact($activeContent) ? '' : $activeContent;
    }

    // Section: Stored backup

    private function readBackup(int $pageId): string
    {
        $backup = get_post_meta($pageId, self::META_KEY, true);
        if (
            !is_array($backup)
            || ($backup['version'] ?? null) !== self::BACKUP_VERSION
            || !array_key_exists('content', $backup)
            || !is_string($backup['content'])
        ) {
            throw new \RuntimeException('The saved WordPress page body is invalid and cannot be replaced safely.');
        }

        return $backup['content'];
    }

    private function readPostContent(int $pageId): string
    {
        $content = get_post_field('post_content', $pageId, 'raw');
        if (!is_string($content)) {
            throw new \RuntimeException('The WordPress page body could not be read.');
        }

        return $content;
    }

    /**
     * Recognize only a complete Page Builder Gutenberg projection. A marker
     * copied into ordinary page content must never authorize clearing the body.
     */
    private function isPageBuilderArtifact(string $content): bool
    {
        return preg_match(
            '/\A\s*<!-- wp:html -->\s*<div\b[^>]*\bdata-uncanny-page-builder-artifact="1"[^>]*>[\s\S]*<\/div>\s*<!-- \/wp:html -->\s*\z/',
            $content,
        ) === 1;
    }

    // Section: Active post content

    private function writePostContent(int $pageId, string $content): void
    {
        global $wpdb;

        self::$writeDepth++;

        try {
            // The value was already trusted and stored by WordPress before
            // adoption. A direct database write preserves it byte-for-byte
            // even when the current editor lacks unfiltered_html.
            if (isset($wpdb) && is_object($wpdb) && isset($wpdb->posts) && method_exists($wpdb, 'update')) {
                $updated = $wpdb->update(
                    $wpdb->posts,
                    ['post_content' => $content],
                    ['ID' => $pageId],
                    ['%s'],
                    ['%d'],
                );

                if ($updated === false) {
                    throw new \RuntimeException('The WordPress page body could not be updated.');
                }

                if (function_exists(__NAMESPACE__ . '\\clean_post_cache') || function_exists('clean_post_cache')) {
                    clean_post_cache($pageId);
                }
            } else {
                $result = wp_update_post([
                    'ID' => $pageId,
                    'post_content' => wp_slash($content),
                ], true);

                if (is_wp_error($result) || (int) $result !== $pageId) {
                    throw new \RuntimeException('The WordPress page body could not be updated.');
                }
            }
        } finally {
            self::$writeDepth--;
        }

        if ($this->readPostContent($pageId) !== $content) {
            throw new \RuntimeException('The WordPress page body update could not be verified.');
        }
    }
}
