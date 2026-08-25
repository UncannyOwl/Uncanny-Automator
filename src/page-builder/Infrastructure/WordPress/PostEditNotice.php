<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * One-shot, per-post, per-user admin notice storage and markup.
 *
 * Several post-edit metaboxes stage a save-failure message on `save_post`,
 * then render and consume it once on the next `admin_notices` pass. Their
 * transient key shape and markup were duplicated across five classes; this
 * centralizes only that mechanical part. Each caller keeps its own payload
 * shape (string, sentinel, or array) — this class does not interpret it.
 */
final class PostEditNotice
{
    private const TTL = 60;

    public static function remember(string $keyPrefix, int $postId, mixed $payload): void
    {
        set_transient(self::key($keyPrefix, $postId), $payload, self::TTL);
    }

    public static function forget(string $keyPrefix, int $postId): void
    {
        delete_transient(self::key($keyPrefix, $postId));
    }

    public static function read(string $keyPrefix, int $postId): mixed
    {
        return get_transient(self::key($keyPrefix, $postId));
    }

    /**
     * @param string[] $codes Optional short codes shown under the message
     *                        (e.g. rejected or locked setting keys).
     */
    public static function render(string $message, string $level = 'error', array $codes = []): void
    {
        if ($message === '') {
            return;
        }

        $noticeClass = 'warning' === $level ? 'notice-warning' : 'notice-error';

        echo '<div class="notice ' . esc_attr($noticeClass) . ' is-dismissible">';
        echo '<p>' . esc_html($message) . '</p>';

        if ($codes !== []) {
            echo '<p><code>' . esc_html(implode(', ', $codes)) . '</code></p>';
        }

        echo '</div>';
    }

    private static function key(string $keyPrefix, int $postId): string
    {
        return $keyPrefix . $postId . '_' . (int) get_current_user_id();
    }
}
