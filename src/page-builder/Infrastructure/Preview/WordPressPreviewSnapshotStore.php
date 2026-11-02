<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Preview;

use UncannyPageBuilder\Application\Preview\PreviewSnapshotStoreInterface;

/** One expiring snapshot per page; issuing another link replaces the old one. */
final class WordPressPreviewSnapshotStore implements PreviewSnapshotStoreInterface
{
    public function save(int $pageId, string $html, int $ttl): array
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + $ttl;
        $snapshot = [
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expires,
            'html' => $html,
        ];
        if (!set_transient($this->key($pageId), $snapshot, $ttl)) {
            throw new \RuntimeException('The preview snapshot could not be saved.');
        }
        return [
            'url' => rest_url('uncanny-page-builder/v1/page-preview/' . $pageId . '/' . $token),
            'expires_at' => $expires,
        ];
    }

    public function read(int $pageId, string $token): ?string
    {
        if ($pageId <= 0 || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return null;
        }
        $snapshot = get_transient($this->key($pageId));
        if (
            !is_array($snapshot)
            || !is_int($snapshot['expires_at'] ?? null)
            || $snapshot['expires_at'] <= time()
            || !is_string($snapshot['token_hash'] ?? null)
            || !hash_equals($snapshot['token_hash'], hash('sha256', $token))
            || !is_string($snapshot['html'] ?? null)
        ) {
            return null;
        }
        return $snapshot['html'];
    }

    private function key(int $pageId): string
    {
        return 'upb_preview_snapshot_' . $pageId;
    }
}
