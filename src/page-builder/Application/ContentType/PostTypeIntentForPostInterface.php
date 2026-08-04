<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\ContentType;

/**
 * Resolves saved administrator intent for one concrete WordPress post.
 */
interface PostTypeIntentForPostInterface
{
    public function isEnabledForPost(int $postId): bool;
}
