<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Preview;

interface PreviewSnapshotStoreInterface
{
    /** @return array{url: string, expires_at: int} */
    public function save(int $pageId, string $html, int $ttl): array;

    public function read(int $pageId, string $token): ?string;
}
