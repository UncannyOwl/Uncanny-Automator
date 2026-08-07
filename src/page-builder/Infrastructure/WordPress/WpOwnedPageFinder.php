<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Publishing\OwnedPageFinderInterface;

final class WpOwnedPageFinder implements OwnedPageFinderInterface
{
    public function __construct(
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    public function ownedPageIds(int $limit = 500, int $afterPageId = 0): array
    {
        if (!function_exists('get_posts')) {
            return [];
        }

        $supportedPostTypes = $this->supportedPostTypes();
        if ($supportedPostTypes === []) {
            return [];
        }

        $afterPageId = max(0, $afterPageId);
        $whereFilter = null;

        if ($afterPageId > 0 && function_exists('add_filter') && function_exists('remove_filter')) {
            $whereFilter = static function ($where = null) use ($afterPageId): string {
                global $wpdb;
                $where = is_string($where) ? $where : '';

                return $where . $wpdb->prepare(" AND {$wpdb->posts}.ID > %d", $afterPageId);
            };
            add_filter('posts_where', $whereFilter);
        }

        try {
            $posts = get_posts([
                'post_type' => $supportedPostTypes,
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => max(1, min(1000, $limit)),
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_key' => '_uncanny_page_builder_owned',
                'meta_value' => '1',
                'no_found_rows' => true,
            ]);
        } finally {
            if ($whereFilter !== null) {
                remove_filter('posts_where', $whereFilter);
            }
        }

        if (!is_array($posts)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', $posts),
            function (int $pageId): bool {
                $postType = $pageId > 0 ? get_post_type($pageId) : null;

                return is_string($postType)
                    && $this->supportsPostType->isSupported($postType);
            },
        ));
    }

    /**
     * @return list<string>
     */
    private function supportedPostTypes(): array
    {
        $function = __NAMESPACE__ . '\\get_post_types';
        if (function_exists('get_post_types')) {
            $registered = \get_post_types([], 'names');
        } elseif (function_exists($function)) {
            $registered = $function([], 'names');
        } else {
            return [];
        }

        if (!is_array($registered)) {
            return [];
        }

        $supported = [];
        foreach ($registered as $postType) {
            if (is_string($postType) && $this->supportsPostType->isSupported($postType)) {
                $supported[] = $postType;
            }
        }

        return array_values(array_unique($supported));
    }
}
