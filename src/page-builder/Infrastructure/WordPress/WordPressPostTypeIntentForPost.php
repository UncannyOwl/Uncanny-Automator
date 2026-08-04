<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\PostTypeIntentForPostInterface;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;

/**
 * Maps a concrete WordPress post back to the administrator's saved intent.
 */
final class WordPressPostTypeIntentForPost implements PostTypeIntentForPostInterface
{
    public function __construct(
        private readonly SupportsPostTypeUseCase $supportsPostType,
    ) {}

    public function isEnabledForPost(int $postId): bool
    {
        if ($postId <= 0) {
            return false;
        }

        $postType = get_post_type($postId);

        return is_string($postType)
            && $this->supportsPostType->isEnabledByAdministrator($postType);
    }
}
