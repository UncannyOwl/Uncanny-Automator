<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Domain\Section\DynamicContentConfig;

/**
 * Render data-ai-dynamic="wp_query" loops.
 *
 * Runs a WP_Query and clones the card template for each post,
 * replacing data-ai-bind placeholders with real post data.
 *
 * Supported bind keys:
 *   title          — post title
 *   excerpt        — post excerpt (trimmed to 20 words)
 *   content        — full post content (filtered)
 *   thumbnail      — featured image URL
 *   permalink      — post permalink
 *   date           — published date (site format)
 *   modified_date  — last modified date
 *   author         — author display name
 *   author_url     — author archive URL
 *   author_avatar  — author avatar image URL
 *   categories     — comma-separated category names
 *   tags           — comma-separated tag names
 *   comment_count  — number of comments
 *   post_id        — post ID
 *   terms.<tax>    — comma-separated term names for any taxonomy
 *   meta.<key>     — governed post meta (allowlist only)
 */
final class WpQueryCardRenderer implements SectionRendererInterface
{
    private const ALLOWED_STATUSES = ['publish', 'future', 'pending'];

    private const ALLOWED_ORDERBY = [
        'date', 'modified', 'title', 'name', 'rand', 'comment_count',
        'menu_order', 'ID', 'author', 'relevance', 'none',
        'meta_value',
    ];

    private const FEATURED_IMAGE_PLACEHOLDER_PATH =
        'assets/images/bindings/feat-image-placeholders/placeholder-1.png';

    public function render(string $cardTemplate, array $args): string
    {
        $this->markPageAsNonCacheableWhenStatusIsViewerSpecific($args);

        $plan = $this->buildQueryPlan($args);
        if ($plan === null) {
            $bindingId = (string) ($args['_binding_id'] ?? 'wp_query');
            return $this->emptyResultComment($bindingId);
        }

        $query = new \WP_Query($plan['query_args']);

        if (!$query->have_posts()) {
            return '<!-- No posts found -->';
        }

        // Prime meta + term caches to avoid N+1 queries in the loop.
        $postIds = wp_list_pluck($query->posts, 'ID');
        update_postmeta_cache($postIds);
        update_object_term_cache($postIds, $plan['query_args']['post_type']);

        $metaBindKeys = $this->extractMetaBindKeys($cardTemplate);
        $termsBindKeys = $this->extractTermsBindKeys($cardTemplate);
        $output = '';

        while ($query->have_posts()) {
            $query->the_post();
            $card = $cardTemplate;

            // ── Prepare values ──
            $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'large') ?: $this->featuredImagePlaceholderUrl();
            $avatarUrl = get_avatar_url(get_the_author_meta('ID'), ['size' => 96]) ?: '';
            $cats      = get_the_category();
            $tags      = get_the_tags();

            $postContent = $this->preparePostContent(get_the_content());

            // ── Text bindings ──
            $textBindings = [
                'title'         => esc_html(get_the_title()),
                'excerpt'       => esc_html(wp_trim_words(get_the_excerpt(), 20)),
                'date'          => esc_html(get_the_date()),
                'modified_date' => esc_html(get_the_modified_date()),
                'author'        => esc_html(get_the_author()),
                'categories'    => esc_html($cats ? implode(', ', wp_list_pluck($cats, 'name')) : ''),
                'tags'          => esc_html($tags ? implode(', ', wp_list_pluck($tags, 'name')) : ''),
                'comment_count' => esc_html((string) get_comments_number()),
                'content'       => wp_kses_post($postContent),
                'post_id'       => esc_html((string) get_the_ID()),
            ];

            foreach ($textBindings as $key => $safeValue) {
                $card = CardBindingEngine::text($card, $key, $safeValue);
            }

            // ── Image bindings ──
            $card = CardBindingEngine::image($card, 'thumbnail', esc_url($thumbnail));
            $card = CardBindingEngine::image($card, 'author_avatar', esc_url($avatarUrl));

            // ── Href bindings ──
            $card = CardBindingEngine::href($card, 'permalink', esc_url(get_permalink()));
            $card = CardBindingEngine::href($card, 'author_url', esc_url(get_author_posts_url(get_the_author_meta('ID'))));

            // ── Taxonomy terms bindings (terms.<taxonomy>) ──
            foreach ($termsBindKeys as $taxonomy) {
                $taxonomy = sanitize_key($taxonomy);
                $terms = get_the_terms(get_the_ID(), $taxonomy);
                $termNames = ($terms && !is_wp_error($terms))
                    ? implode(', ', wp_list_pluck($terms, 'name'))
                    : '';
                $bindAttr = 'terms.' . $taxonomy;
                $card = CardBindingEngine::text($card, $bindAttr, esc_html($termNames));
            }

            // ── Post meta bindings (blocklist-governed) ──
            foreach ($metaBindKeys as $metaKey) {
                if (!DynamicContentConfig::isMetaKeyAllowed($metaKey)) {
                    continue;
                }
                $bindAttr = 'meta.' . $metaKey;
                $metaValue = get_post_meta(get_the_ID(), $metaKey, true);
                $card = MetaBindingHelper::applyMetaBinding($card, $bindAttr, $metaValue, $metaKey);
            }

            $output .= $card . "\n";
        }

        wp_reset_postdata();

        if ($plan['paginate']) {
            $totalPages = $this->paginationTotalPages(
                $query,
                $plan['query_args']['posts_per_page'],
                $plan['base_offset']
            );

            if ($totalPages > 1) {
                $output .= $this->renderPagination($totalPages, $plan['current_page']);
            }
        }

        return $output;
    }

    /**
     * Build the query execution plan from author-supplied attributes.
     *
     * The returned plan keeps pagination bookkeeping alongside the final
     * WP_Query args so attribute interactions stay explicit and testable.
     *
     * @param array<string, mixed> $args
     * @return array{query_args: array<string, mixed>, paginate: bool, current_page: int, base_offset: int}|null
     */
    private function buildQueryPlan(array $args): ?array
    {
        $paginate = (bool) ($args['paginate'] ?? false);
        $currentPage = $paginate ? max(1, (int) get_query_var('paged'), (int) get_query_var('page')) : 1;
        $baseOffset = max(0, (int) ($args['offset'] ?? 0));
        $metaKey = $this->sanitizeMetaKey($args['meta_key'] ?? '');
        $postsPerPage = DynamicCardCount::resolve($args['count'] ?? null, 10);

        $queryArgs = [
            'post_type'      => sanitize_key($args['post_type'] ?? 'post'),
            'posts_per_page' => $postsPerPage,
            'orderby'        => $this->sanitizeOrderby($args['orderby'] ?? 'date', $metaKey !== ''),
            'order'          => in_array(strtoupper($args['order'] ?? 'DESC'), ['ASC', 'DESC'], true)
                                    ? strtoupper($args['order'] ?? 'DESC')
                                    : 'DESC',
            'post_status'    => $this->sanitizeStatus($args['status'] ?? 'publish'),
            'paged'          => $currentPage,
        ];

        // Section: direct attribute-to-query mapping.
        if (!empty($args['category'])) {
            $queryArgs['category_name'] = sanitize_text_field($args['category']);
        }

        if (!empty($args['tag'])) {
            $queryArgs['tag'] = sanitize_text_field($args['tag']);
        }

        if (!empty($args['author'])) {
            $queryArgs['author'] = (int) $args['author'];
        }

        if (!empty($args['exclude'])) {
            $queryArgs['post__not_in'] = array_map('intval', explode(',', $args['exclude']));
        }

        if (!empty($args['include'])) {
            $queryArgs['post__in'] = array_map('intval', explode(',', $args['include']));
        }

        if (!empty($args['parent'])) {
            $queryArgs['post_parent'] = (int) $args['parent'];
        }

        if (!empty($args['search'])) {
            $queryArgs['s'] = sanitize_text_field($args['search']);
        }

        if (!empty($args['taxonomy']) && !empty($args['term'])) {
            $queryArgs['tax_query'] = [[
                'taxonomy' => sanitize_key($args['taxonomy']),
                'field'    => 'slug',
                'terms'    => sanitize_text_field($args['term']),
            ]];
        }

        if ($metaKey !== '') {
            $queryArgs['meta_key'] = $metaKey;
            if (!empty($args['meta_value'])) {
                $queryArgs['meta_value'] = sanitize_text_field($args['meta_value']);
            }
        }

        // Section: pagination-safe offset handling.
        //
        // WP_Query's raw offset is not page-aware. Preserve author intent by
        // shifting each page window forward by the base offset, then compute
        // pagination totals from found_posts minus that base offset.
        if ($baseOffset > 0) {
            $queryArgs['offset'] = $paginate
                ? $baseOffset + (($currentPage - 1) * $postsPerPage)
                : $baseOffset;
        }

        // Apply per-binding query semantics (sticky/popular/related). The shared
        // renderer is otherwise binding-agnostic, so these would silently degrade
        // to a generic recent-posts loop without this step.
        $bindingId = (string) ($args['_binding_id'] ?? 'wp_query');
        $queryArgs = $this->specializeForBinding($bindingId, $queryArgs, $args);
        if ($queryArgs === null) {
            return null;
        }

        return [
            'query_args'    => $queryArgs,
            'paginate'      => $paginate,
            'current_page'  => $currentPage,
            'base_offset'   => $baseOffset,
        ];
    }

    /**
     * Apply query semantics specific to a query-family binding.
     *
     * Returns the modified WP_Query args, or null when the binding's required
     * context is missing (no sticky posts exist, no current post for related) —
     * the caller then fails closed with an explicit empty result rather than
     * silently rendering an unrelated recent-posts loop.
     *
     * @param array<string, mixed> $queryArgs
     * @param array<string, mixed> $args
     * @return array<string, mixed>|null
     */
    private function specializeForBinding(string $bindingId, array $queryArgs, array $args): ?array
    {
        switch ($bindingId) {
            case 'wp_sticky_posts':
                $sticky = get_option('sticky_posts');
                if (!is_array($sticky) || $sticky === []) {
                    return null;
                }
                $queryArgs['post__in'] = array_map('intval', $sticky);
                $queryArgs['ignore_sticky_posts'] = true;
                // Preserve the sticky order configured in Settings → Writing.
                $queryArgs['orderby'] = 'post__in';
                return $queryArgs;

            case 'wp_popular_posts':
                // "Popular" is approximated by comment volume (no view tracking).
                $queryArgs['orderby'] = 'comment_count';
                $queryArgs['order'] = 'DESC';
                return $queryArgs;

            case 'wp_related':
                $currentId = (int) get_the_ID();
                if ($currentId <= 0) {
                    return null;
                }
                $taxonomy = sanitize_key($args['taxonomy'] ?? 'category');
                $terms = get_the_terms($currentId, $taxonomy);
                if (is_wp_error($terms) || !is_array($terms) || $terms === []) {
                    return null;
                }
                $termIds = array_map('intval', wp_list_pluck($terms, 'term_id'));
                $queryArgs['tax_query'] = [[
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $termIds,
                ]];
                // Related posts default to the current post's type unless the
                // author queried a specific one.
                if (empty($args['post_type'])) {
                    $currentType = get_post_type($currentId);
                    if (is_string($currentType) && $currentType !== '') {
                        $queryArgs['post_type'] = $currentType;
                    }
                }
                // Always exclude the current post; merge with any author exclude
                // rather than discarding it.
                $existingExclude = isset($queryArgs['post__not_in']) && is_array($queryArgs['post__not_in'])
                    ? $queryArgs['post__not_in']
                    : [];
                $queryArgs['post__not_in'] = array_values(array_unique(array_merge($existingExclude, [$currentId])));
                return $queryArgs;

            default:
                // wp_query and wp_recent_posts use the generic args as-is.
                return $queryArgs;
        }
    }

    private function emptyResultComment(string $bindingId): string
    {
        return match ($bindingId) {
            'wp_sticky_posts' => '<!-- No sticky posts -->',
            'wp_related'      => '<!-- No related posts -->',
            default           => '<!-- No posts found -->',
        };
    }

    private function renderPagination(int $totalPages, int $currentPage): string
    {
        $links = paginate_links([
            'total'   => $totalPages,
            'current' => $currentPage,
            'format'  => 'page/%#%/',
            'base'    => trailingslashit(get_pagenum_link(1)) . 'page/%#%/',
            'type'    => 'list',
        ]);

        if (!is_string($links) || trim($links) === '') {
            return '';
        }

        return '<nav class="upb-pagination" aria-label="Page navigation">' . $links . '</nav>';
    }

    private function paginationTotalPages(object $query, int $postsPerPage, int $baseOffset): int
    {
        if ($postsPerPage <= 0) {
            return 0;
        }

        if ($baseOffset <= 0) {
            return (int) $query->max_num_pages;
        }

        $visiblePosts = max(0, (int) $query->found_posts - $baseOffset);

        return (int) ceil($visiblePosts / $postsPerPage);
    }

    private function sanitizeStatus(string $status): string
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return 'publish';
        }

        if ($status === 'publish') {
            return $status;
        }

        return $this->canReadNonPublicPosts() ? $status : 'publish';
    }

    private function canReadNonPublicPosts(): bool
    {
        return \function_exists('current_user_can') && \current_user_can('edit_posts');
    }

    /**
     * Section: non-public status queries are viewer-specific.
     *
     * Editors may legitimately see future or pending posts while visitors are
     * downgraded back to publish. Mark those responses non-cacheable so an
     * authenticated variant is never frozen and reused as public output.
     *
     * @param array<string, mixed> $args
     */
    private function markPageAsNonCacheableWhenStatusIsViewerSpecific(array $args): void
    {
        $requestedStatus = (string) ($args['status'] ?? 'publish');
        if (!in_array($requestedStatus, ['future', 'pending'], true)) {
            return;
        }

        if (!\defined('DONOTCACHEPAGE')) {
            \define('DONOTCACHEPAGE', true);
        }
    }

    private function preparePostContent(string $postContent): string
    {
        // Keep core formatting behavior without executing shortcodes in the
        // visitor context for arbitrary queried posts.
        $postContent = do_blocks($postContent);
        $postContent = wptexturize($postContent);
        $postContent = wpautop($postContent);
        $postContent = shortcode_unautop($postContent);

        return wp_filter_content_tags($postContent);
    }

    private function sanitizeOrderby(string $orderby, bool $hasMetaKey = false): string
    {
        if ($orderby === 'meta_value' && !$hasMetaKey) {
            return 'date';
        }

        return in_array($orderby, self::ALLOWED_ORDERBY, true) ? $orderby : 'date';
    }

    private function sanitizeMetaKey(mixed $metaKey): string
    {
        if (!is_string($metaKey) || $metaKey === '') {
            return '';
        }

        if (!DynamicContentConfig::isMetaKeyAllowed($metaKey)) {
            return '';
        }

        return sanitize_key($metaKey);
    }

    private function featuredImagePlaceholderUrl(): string
    {
        if (!\defined('UNCANNY_PB_URL')) {
            return '';
        }

        return (string) \constant('UNCANNY_PB_URL') . self::FEATURED_IMAGE_PLACEHOLDER_PATH;
    }

    /**
     * Extract taxonomy names from data-ai-bind="terms.<taxonomy>" attributes.
     *
     * @return string[]
     */
    private function extractTermsBindKeys(string $cardTemplate): array
    {
        if (preg_match_all('/data-ai-bind="terms\.([^"]+)"/', $cardTemplate, $matches)) {
            return array_unique($matches[1]);
        }
        return [];
    }

    /**
     * Extract meta keys from data-ai-bind="meta.<key>" attributes in the card template.
     *
     * @return string[]
     */
    private function extractMetaBindKeys(string $cardTemplate): array
    {
        if (preg_match_all('/data-ai-bind="meta\.([^"]+)"/', $cardTemplate, $matches)) {
            return array_unique($matches[1]);
        }
        return [];
    }
}
