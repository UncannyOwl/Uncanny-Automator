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

    public function __construct(
        private readonly WordPressPublishedFallbackParser $fallbackParser = new WordPressPublishedFallbackParser(),
    ) {}

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
        $content = $hasBackup ? $this->readBackup($pageId) : '';

        if ($activeContent === $content) {
            return;
        }

        // Releases before the versioned suffix either kept post_content empty
        // or stored one complete legacy Page Builder artifact. Both states are
        // unambiguous and safe to restore without deleting user edits.
        if ($hasBackup && $activeContent === '') {
            $this->writePostContent($pageId, $content);

            return;
        }
        if (
            $this->fallbackParser->isLegacyArtifact($activeContent)
        ) {
            $this->writePostContent($pageId, $hasBackup ? $content : '');

            return;
        }

        $fallback = $this->fallbackParser->parse($activeContent);
        if ($fallback instanceof WordPressPublishedFallbackContent) {
            if ($hasBackup && $fallback->originalContent() !== $content) {
                throw new InvalidPublishedFallbackContent(
                    'The WordPress-owned content prefix changed and cannot be restored safely.',
                );
            }
            if (!$hasBackup && $fallback->originalContent() !== '') {
                throw new InvalidPublishedFallbackContent(
                    'A Page Builder-created page contains an unexpected WordPress content prefix.',
                );
            }

            $this->writePostContent($pageId, $hasBackup ? $content : '');

            return;
        }

        // A Page Builder-created page can contain pre-adoption WordPress data.
        // Without a backup, leave ordinary content byte-for-byte untouched.
        if (!$hasBackup) {
            return;
        }

        // An adopted page has an authoritative backup. Any unexplained
        // divergence can be a manual edit, so block instead of overwriting it.
        if (!$this->isPageBuilderArtifact($activeContent)) {
            throw new InvalidPublishedFallbackContent(
                'The WordPress page body changed and cannot be restored safely.',
            );
        }

        $content = $hasBackup ? $content : '';

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

        if ($this->fallbackParser->isLegacyArtifact($activeContent)) {
            return '';
        }

        $fallback = $this->fallbackParser->parse($activeContent);
        if ($fallback instanceof WordPressPublishedFallbackContent) {
            return $fallback->originalContent();
        }

        return $this->isPageBuilderArtifact($activeContent) ? '' : $activeContent;
    }

    /**
     * Read and lock the exact adoption backup inside the publication transaction.
     * A missing row identifies a page that Page Builder created with an empty body.
     */
    public function originalContentForPublication(int $pageId): string
    {
        global $wpdb;

        $postmetaTable = isset($wpdb->postmeta)
            ? (string) $wpdb->postmeta
            : (string) $wpdb->prefix . 'postmeta';
        if (!method_exists($wpdb, 'get_results')) {
            throw new \RuntimeException('The WordPress content backup cannot be locked for publication.');
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_id, meta_value FROM {$postmetaTable}
             WHERE post_id = %d AND meta_key = %s
             ORDER BY meta_id ASC LIMIT 2 FOR UPDATE",
            $pageId,
            self::META_KEY,
        ));
        if (!is_array($rows)) {
            throw new \RuntimeException('The saved WordPress page body could not be read for publication.');
        }
        if ($rows === []) {
            return '';
        }
        if (count($rows) !== 1 || !is_object($rows[0]) || !isset($rows[0]->meta_value)) {
            throw new \RuntimeException('The saved WordPress page body backup is ambiguous.');
        }

        $backup = $this->unserializeMetadata((string) $rows[0]->meta_value);
        if (
            !is_array($backup)
            || ($backup['version'] ?? null) !== self::BACKUP_VERSION
            || !array_key_exists('content', $backup)
            || !is_string($backup['content'])
        ) {
            throw new \RuntimeException('The saved WordPress page body is invalid and cannot be published safely.');
        }

        return $backup['content'];
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

    private function unserializeMetadata(string $value): mixed
    {
        if (function_exists('maybe_unserialize')) {
            return maybe_unserialize($value);
        }

        if (!preg_match('/^(?:a|s|i|b|d|O|C|E|N):/', $value)) {
            return $value;
        }

        return @unserialize($value, ['allowed_classes' => false]);
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
            if (isset($wpdb) && is_object($wpdb) && isset($wpdb->posts) && method_exists($wpdb, 'query')) {
                $activeContent = $this->readPostContent($pageId);
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->posts}
                     SET post_content = %s
                     WHERE ID = %d AND post_content = %s",
                    $content,
                    $pageId,
                    $activeContent,
                ));

                if ($updated === false) {
                    throw new \RuntimeException('The WordPress page body could not be updated.');
                }
                if ($updated === 0 && $this->readPostContent($pageId) !== $content) {
                    throw new \RuntimeException('The WordPress page body changed during restoration.');
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
