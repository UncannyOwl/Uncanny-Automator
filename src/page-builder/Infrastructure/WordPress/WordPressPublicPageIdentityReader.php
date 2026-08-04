<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Rendering\PublicPageIdentity;
use UncannyPageBuilder\Application\Rendering\PublicPageIdentityReaderInterface;

/**
 * Reads the raw WordPress fields committed beside a publication pointer.
 */
final class WordPressPublicPageIdentityReader implements PublicPageIdentityReaderInterface
{
    public function read(int $pageId): ?PublicPageIdentity
    {
        if ($pageId <= 0) {
            return null;
        }

        $post = get_post($pageId);
        if (!is_object($post) || (int) ($post->ID ?? 0) !== $pageId) {
            return null;
        }

        return new PublicPageIdentity(
            pageId: $pageId,
            status: (string) ($post->post_status ?? ''),
            title: (string) ($post->post_title ?? ''),
            slug: (string) ($post->post_name ?? ''),
        );
    }
}
