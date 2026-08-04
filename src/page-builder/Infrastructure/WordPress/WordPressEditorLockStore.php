<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\EditorLock\EditorLockOwner;
use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorLockToken;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipState;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipStatus;

/**
 * WordPress `_edit_lock` adapter.
 *
 * Claims use compare-and-swap metadata writes and are always confirmed by a
 * fresh read. This keeps an entry-time race from granting two editors a false
 * sense of ownership.
 */
final class WordPressEditorLockStore implements EditorLockStoreInterface
{
    private const META_KEY = '_edit_lock';
    private const DEFAULT_WINDOW_SECONDS = 150;
    private const MAX_CLOCK_SKEW_SECONDS = 30;

    public function isEnabled(int $postId): bool
    {
        $enabled = (bool) apply_filters(
            'uncanny_page_builder_editor_locking_enabled',
            true,
            $postId,
        );

        /*
         * A zero lock window is WordPress's effective "no active locks"
         * configuration. Treat it as disabled so save-time ownership checks do
         * not demand a token that can never remain fresh.
         */
        return $enabled && $this->lockWindowSeconds() > 0;
    }

    public function inspect(int $postId, int $actorUserId): EditorOwnershipState
    {
        $post = $this->post($postId);
        $rawLock = $this->readRawLock($postId);

        if ($rawLock === '') {
            return EditorOwnershipState::available();
        }

        $token = EditorLockToken::parse($rawLock);
        $now = time();
        if (
            !$token instanceof EditorLockToken
            || $token->timestamp() > ($now + self::MAX_CLOCK_SKEW_SECONDS)
            || $token->isExpiredAt($now, $this->lockWindowSeconds())
        ) {
            return EditorOwnershipState::available();
        }

        if ($token->userId() === $actorUserId) {
            return EditorOwnershipState::owned($token);
        }

        $owner = get_userdata($token->userId());
        if (!$owner instanceof \WP_User || !user_can($owner, 'edit_post', $postId)) {
            return EditorOwnershipState::available();
        }

        /*
         * Match WordPress core's compatibility hooks. Suppressing the lock
         * dialog means the competing lock is advisory and may be replaced.
         */
        if (!(bool) apply_filters('show_post_locked_dialog', true, $post, $owner)) {
            return EditorOwnershipState::available();
        }

        return EditorOwnershipState::blocked(
            new EditorLockOwner(
                (int) $owner->ID,
                trim((string) $owner->display_name) !== ''
                    ? (string) $owner->display_name
                    : _x('Another user', 'Page Builder', 'uncanny-automator'),
                $this->avatarUrl((int) $owner->ID),
            ),
            (bool) apply_filters('override_post_lock', true, $post, $owner),
            $token,
        );
    }

    public function claim(int $postId, int $actorUserId): EditorOwnershipState
    {
        $state = $this->inspect($postId, $actorUserId);
        if ($state->status() !== EditorOwnershipStatus::Available) {
            return $state;
        }

        $previous = $this->readRawLock($postId);
        $confirmedAvailable = $this->inspect($postId, $actorUserId);
        if ($confirmedAvailable->status() !== EditorOwnershipStatus::Available) {
            return $confirmedAvailable;
        }

        $newToken = EditorLockToken::create(time(), $actorUserId);

        if ($previous === '') {
            add_post_meta($postId, self::META_KEY, $newToken->raw(), true);
        } else {
            update_post_meta($postId, self::META_KEY, $newToken->raw(), $previous);
        }

        return $this->inspect($postId, $actorUserId);
    }

    public function takeOver(int $postId, int $actorUserId): EditorOwnershipState
    {
        $state = $this->inspect($postId, $actorUserId);
        if ($state->status() === EditorOwnershipStatus::Blocked && !$state->takeoverAllowed()) {
            return $state;
        }

        if ($state->status() === EditorOwnershipStatus::Unavailable) {
            return $state;
        }

        if ($state->status() === EditorOwnershipStatus::Available) {
            return $this->claim($postId, $actorUserId);
        }

        $previous = $state->token()?->raw() ?? '';
        if ($previous === '') {
            return $this->inspect($postId, $actorUserId);
        }

        $newToken = EditorLockToken::create(time(), $actorUserId);
        update_post_meta($postId, self::META_KEY, $newToken->raw(), $previous);

        return $this->inspect($postId, $actorUserId);
    }

    public function refresh(
        int $postId,
        int $actorUserId,
        string $knownToken,
    ): EditorOwnershipState {
        unset($knownToken);

        $state = $this->inspect($postId, $actorUserId);
        if ($state->status() === EditorOwnershipStatus::Blocked) {
            return $state;
        }

        if ($state->status() === EditorOwnershipStatus::Unavailable) {
            return $state;
        }

        if ($state->status() === EditorOwnershipStatus::Available) {
            return $this->claim($postId, $actorUserId);
        }

        $previous = $state->token()?->raw() ?? '';
        if ($previous === '') {
            return $this->inspect($postId, $actorUserId);
        }

        $newToken = EditorLockToken::create(time(), $actorUserId);
        update_post_meta($postId, self::META_KEY, $newToken->raw(), $previous);

        return $this->inspect($postId, $actorUserId);
    }

    public function release(int $postId, int $actorUserId, string $knownToken): bool
    {
        $token = EditorLockToken::parse($knownToken);
        if (!$token instanceof EditorLockToken || $token->userId() !== $actorUserId) {
            return false;
        }

        if ($this->readRawLock($postId) !== $knownToken) {
            return false;
        }

        /*
         * Mirror WordPress's beacon release: expire only the exact token. A
         * suspended tab cannot release a newer lock acquired in another tab.
         */
        $expiredToken = EditorLockToken::create(
            time() - $this->lockWindowSeconds() - 1,
            $actorUserId,
        );
        update_post_meta($postId, self::META_KEY, $expiredToken->raw(), $knownToken);

        return $this->readRawLock($postId) !== $knownToken;
    }

    private function post(int $postId): \WP_Post
    {
        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            throw new \RuntimeException('Editor lock target is unavailable.');
        }

        return $post;
    }

    private function readRawLock(int $postId): string
    {
        $value = get_post_meta($postId, self::META_KEY, true);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function avatarUrl(int $userId): string
    {
        try {
            $url = get_avatar_url($userId, ['size' => 128]);

            if (!is_string($url)) {
                return '';
            }

            $sanitized = esc_url_raw($url, ['http', 'https']);

            return is_string($sanitized) ? $sanitized : '';
        } catch (\Throwable) {
            /*
             * Avatar providers are presentation integrations. A broken avatar
             * filter must not turn a confirmed competing owner into an
             * unavailable ownership check.
             */
            return '';
        }
    }

    private function lockWindowSeconds(): int
    {
        return max(0, (int) apply_filters(
            'wp_check_post_lock_window',
            self::DEFAULT_WINDOW_SECONDS,
        ));
    }
}
