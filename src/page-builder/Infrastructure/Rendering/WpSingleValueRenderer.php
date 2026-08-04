<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Domain\Export\StaticExportPageIdentity;

/**
 * Generic renderer for single-value self-rendering bindings.
 *
 * Looks up the binding ID from $args['_binding_id'] and calls the
 * corresponding WordPress function. The output replaces the wrapper's
 * inner content.
 *
 * Supported binding IDs are registered in the VALUE_MAP constant.
 */
final class WpSingleValueRenderer implements SectionRendererInterface
{
    public function __construct(
        private readonly ?StaticExportPageIdentity $pageIdentity = null,
    ) {}

    /**
     * Option keys safe to expose publicly via wp_option. Anything else (auth
     * keys, API tokens, mail passwords, serialized internals) must never be
     * rendered. Extendable via the uncanny_page_builder_public_option_keys filter.
     *
     * @var string[]
     */
    private const PUBLIC_OPTION_KEYS = [
        'blogname',
        'blogdescription',
        'start_of_week',
        'timezone_string',
        'date_format',
        'time_format',
        'posts_per_page',
        'show_on_front',
    ];

    /**
     * Shortcode tags wp_shortcode must not execute by default.
     *
     * Most WordPress users expect registered shortcodes from installed plugins
     * to render. Keep the dynamic binding as the owner boundary, then block
     * only known risky tags. `[embed]` has its own wp_embed binding where
     * provider discovery stays disabled.
     *
     * @var string[]
     */
    private const DEFAULT_BLOCKED_SHORTCODES = [
        'embed',
    ];

    private const ALLOWED_IMAGE_SIZES = ['thumbnail', 'medium', 'medium_large', 'large', 'full'];

    private const ALLOWED_ARCHIVE_TYPES = ['yearly', 'monthly', 'daily', 'weekly', 'postbypost', 'alpha'];

    /**
     * @param string              $cardTemplate Ignored — self-rendering.
     * @param array<string, mixed> $args        Must include '_binding_id'.
     */
    public function render(string $cardTemplate, array $args): string
    {
        $bindingId = $args['_binding_id'] ?? '';

        return match ($bindingId) {
            // Site identity
            'site_title'          => esc_html(get_bloginfo('name')),
            'site_tagline'        => esc_html(get_bloginfo('description')),
            'site_url'            => esc_url(home_url('/')),
            'copyright_year'      => esc_html(gmdate('Y')),
            'privacy_policy_url'  => esc_url(get_privacy_policy_url()),
            'admin_email'         => $this->renderAdminEmail(),
            'site_language'       => esc_html(get_bloginfo('language')),
            'rss_url'             => esc_url(get_bloginfo('rss2_url')),
            'atom_url'            => esc_url(get_bloginfo('atom_url')),
            'share_email'         => $this->renderShareEmail(),
            'share_facebook'      => $this->renderShareFacebook(),
            'share_linkedin'      => $this->renderShareLinkedIn(),
            'share_twitter'       => $this->renderShareTwitter(),
            'pingback_url'        => esc_url(get_bloginfo('pingback_url')),
            'charset'             => esc_html(get_bloginfo('charset')),
            'wp_version'          => esc_html(get_bloginfo('version')),
            'stylesheet_url'      => esc_url(get_bloginfo('stylesheet_url')),
            'template_url'        => esc_url(get_bloginfo('template_url')),
            'home_link'           => sprintf(
                '<a href="%s">%s</a>',
                esc_url(home_url('/')),
                esc_html(get_bloginfo('name'))
            ),

            // Authentication URLs
            'login_url'           => esc_url(wp_login_url()),
            'logout_url'          => $this->renderLogoutUrl(),
            'register_url'        => esc_url(wp_registration_url()),
            'lost_password_url'   => esc_url(wp_lostpassword_url()),

            // Current page/post
            'page_title'          => $this->renderPageTitle(),
            'page_permalink'      => $this->renderPagePermalink(),
            'page_date'           => $this->renderPageDate(),
            'page_modified_date'  => $this->renderPageModifiedDate(),
            'page_author'         => $this->renderPageAuthor(),
            'page_author_avatar'  => $this->renderPageAuthorAvatar(),
            'page_excerpt'        => $this->renderPageExcerpt(),
            'page_featured_image' => $this->renderFeaturedImage(),
            'post_type_label'     => esc_html($this->getPostTypeLabel()),
            'post_author_url'     => $this->renderPostAuthorUrl(),
            'post_comment_count'  => $this->renderPostCommentCount(),
            'post_reading_time'   => $this->renderPostReadingTime(),
            'post_category'       => $this->renderPostCategory(),
            'post_tags'           => $this->renderPostTags(),
            'post_prev_link'      => $this->renderAdjacentPostUrl(true),
            'post_next_link'      => $this->renderAdjacentPostUrl(false),
            'post_prev_next'      => $this->renderAdjacentPostNav(),
            'author_box'          => $this->renderAuthorBox($args),
            'post_meta'           => $this->renderPostMeta($args),
            'user_meta'           => $this->renderUserMeta($args),

            // Current user
            'current_user_name'   => $this->renderCurrentUserName(),
            'current_user_avatar' => $this->renderCurrentUserAvatar(),
            'current_user_email'  => $this->renderCurrentUserEmail(),
            'current_user_role'   => $this->renderCurrentUserRole(),
            'current_user_bio'    => $this->renderCurrentUserBio(),
            'current_user_url'    => $this->renderCurrentUserUrl(),

            // Forms
            'search_form'         => get_search_form(false),
            'login_form'          => $this->renderLoginForm($args),
            'registration_form'   => $this->renderRegistrationForm(),

            // Navigation helpers
            'post_type_archive_link' => esc_url(
                get_post_type_archive_link($args['post_type'] ?? 'post') ?: ''
            ),

            // Site-wide counts (context-free aggregates)
            'total_posts_count'    => esc_html($this->renderTotalPostsCount()),
            'total_comments_count' => esc_html($this->renderTotalCommentsCount()),
            'total_users_count'    => esc_html($this->renderTotalUsersCount()),

            // Arbitrary option (allowlist-governed — never expose secrets)
            'wp_option'            => $this->renderOption($args),

            // WordPress-generated list markup
            'wp_pages_list'        => $this->renderPagesList($args),
            'wp_categories_list'   => $this->renderCategoriesList($args),
            'wp_archives_list'     => $this->renderArchivesList($args),
            'wp_tag_cloud'         => $this->renderTagCloud($args),

            // Navigation / structure
            'wp_breadcrumbs'       => $this->renderBreadcrumbs($args),
            'wp_pagination'        => $this->renderPaginationLinks(),

            // Media / embeds (intentionally bounded)
            'wp_gallery'           => $this->renderGallery($args),
            'wp_embed'             => $this->renderEmbed($args),
            'wp_shortcode'         => $this->renderShortcode($args),

            default => '<!-- binding "' . esc_html($bindingId) . '" not implemented -->',
        };
    }

    private function renderFeaturedImage(): string
    {
        $postId = $this->currentPostId();
        if ($postId <= 0) {
            return '';
        }

        $url = get_the_post_thumbnail_url($postId, 'large');
        return is_string($url) ? esc_url($url) : '';
    }

    // ── Slice 4: current page / post single-value bindings ────────────────

    private function renderPageTitle(): string
    {
        if ($this->pageIdentity instanceof StaticExportPageIdentity) {
            return esc_html($this->pageIdentity->title());
        }

        $postId = $this->currentPostId();
        return $postId > 0 ? esc_html(get_the_title($postId)) : '';
    }

    private function renderPagePermalink(): string
    {
        if ($this->pageIdentity instanceof StaticExportPageIdentity) {
            return esc_url($this->pageIdentity->permalink());
        }

        $postId = $this->currentPostId();
        return $postId > 0 ? esc_url(get_permalink($postId)) : '';
    }

    private function renderPageDate(): string
    {
        $postId = $this->currentPostId();
        return $postId > 0 ? esc_html(get_the_date('', $postId)) : '';
    }

    private function renderPageModifiedDate(): string
    {
        $postId = $this->currentPostId();
        return $postId > 0 ? esc_html(get_the_modified_date('', $postId)) : '';
    }

    private function renderPageAuthor(): string
    {
        $authorId = $this->currentPostAuthorId();
        return $authorId > 0 ? esc_html((string) get_the_author_meta('display_name', $authorId)) : '';
    }

    private function renderPageAuthorAvatar(): string
    {
        $authorId = $this->currentPostAuthorId();
        return $authorId > 0 ? $this->avatarUrl($authorId, 96) : '';
    }

    private function renderPageExcerpt(): string
    {
        $postId = $this->currentPostId();
        return $postId > 0 ? wp_kses_post(get_the_excerpt($postId)) : '';
    }

    private function renderPostAuthorUrl(): string
    {
        $authorId = $this->currentPostAuthorId();
        return $authorId > 0 ? esc_url(get_author_posts_url($authorId)) : '';
    }

    private function renderPostCommentCount(): string
    {
        $postId = $this->currentPostId();
        return $postId > 0 ? esc_html((string) get_comments_number($postId)) : '';
    }

    private function renderPostReadingTime(): string
    {
        $postId = $this->currentPostId();
        if ($postId <= 0) {
            return '';
        }

        $content = (string) get_post_field('post_content', $postId);
        $words = str_word_count(wp_strip_all_tags($content));
        $minutes = max(1, (int) ceil($words / 200));

        return esc_html($minutes . ' min read');
    }

    private function renderPostCategory(): string
    {
        $postId = $this->currentPostId();
        if ($postId <= 0) {
            return '';
        }

        $categories = get_the_category($postId);
        if (!is_array($categories) || $categories === []) {
            return get_post_type($postId) === 'post' ? esc_html_x('Uncategorized', 'Fallback post category name', 'uncanny-automator') : '';
        }

        return esc_html((string) ($categories[0]->name ?? ''));
    }

    private function renderPostTags(): string
    {
        $postId = $this->currentPostId();
        if ($postId <= 0) {
            return '';
        }

        $tags = get_the_tags($postId);
        if (!is_array($tags) || $tags === []) {
            return '';
        }

        $names = array_values(array_filter(array_map(
            static fn(mixed $tag): string => is_object($tag) ? (string) ($tag->name ?? '') : '',
            $tags
        )));

        return $names === [] ? '' : esc_html(implode(', ', $names));
    }

    private function renderAdjacentPostUrl(bool $previous): string
    {
        $adjacent = $this->adjacentPost($previous);
        if (!$adjacent || !isset($adjacent->ID)) {
            return '';
        }

        return esc_url(get_permalink((int) $adjacent->ID));
    }

    private function renderAdjacentPostNav(): string
    {
        $previous = $this->adjacentPost(true);
        $next = $this->adjacentPost(false);

        if (!$previous && !$next) {
            return '';
        }

        $parts = [];
        if ($previous && isset($previous->ID)) {
            $parts[] = sprintf(
                '<a class="upb-post-prev" href="%s">%s</a>',
                esc_url(get_permalink((int) $previous->ID)),
                esc_html_x('Previous Post', 'Page Builder', 'uncanny-automator')
            );
        }
        if ($next && isset($next->ID)) {
            $parts[] = sprintf(
                '<a class="upb-post-next" href="%s">%s</a>',
                esc_url(get_permalink((int) $next->ID)),
                esc_html_x('Next Post', 'Page Builder', 'uncanny-automator')
            );
        }

        return implode('', $parts);
    }

    private function renderAuthorBox(array $args): string
    {
        $userId = max(0, (int) ($args['user_id'] ?? 0));
        if ($userId <= 0) {
            $userId = $this->currentPostAuthorId();
        }
        if ($userId <= 0) {
            return '';
        }

        $name = (string) get_the_author_meta('display_name', $userId);
        $bio = (string) get_the_author_meta('description', $userId);

        return sprintf(
            '<div class="upb-author-box">%s<div class="upb-author-box__body"><h3 class="upb-author-box__name">%s</h3><p class="upb-author-box__bio">%s</p><a class="upb-author-box__link" href="%s">%s</a></div></div>',
            wp_kses_post(get_avatar($userId, 96) ?: ''),
            esc_html($name),
            esc_html($bio),
            esc_url(get_author_posts_url($userId)),
            esc_html_x('View all posts', 'Page Builder', 'uncanny-automator')
        );
    }

    private function renderPostMeta(array $args): string
    {
        $postId = max(0, (int) ($args['post_id'] ?? 0));
        if ($postId <= 0) {
            $postId = $this->currentPostId();
        }

        return $postId > 0 ? $this->renderMetaValue(
            get_post_meta($postId, (string) ($args['key'] ?? ''), true),
            (string) ($args['key'] ?? '')
        ) : '';
    }

    private function renderUserMeta(array $args): string
    {
        $this->markPageAsNonCacheable();

        $userId = max(0, (int) ($args['user_id'] ?? 0));
        if ($userId <= 0) {
            $userId = $this->isUserLoggedIn() ? get_current_user_id() : 0;
        }

        return $userId > 0 ? $this->renderMetaValue(
            get_user_meta($userId, (string) ($args['key'] ?? ''), true),
            (string) ($args['key'] ?? '')
        ) : '';
    }

    private function renderMetaValue(mixed $rawValue, string $metaKey): string
    {
        if (
            $metaKey === ''
            || !\UncannyPageBuilder\Domain\Section\DynamicContentConfig::isMetaKeyAllowed($metaKey)
            || !is_scalar($rawValue)
        ) {
            return '';
        }

        $value = (string) $rawValue;
        if ($value === '') {
            return '';
        }

        return match (\UncannyPageBuilder\Domain\Section\DynamicContentConfig::metaValueType($metaKey)) {
            \UncannyPageBuilder\Domain\Section\DynamicContentConfig::META_TYPE_URL => esc_html(esc_url_raw($value)),
            \UncannyPageBuilder\Domain\Section\DynamicContentConfig::META_TYPE_IMAGE => $this->normalizeImageMetaValue($value),
            \UncannyPageBuilder\Domain\Section\DynamicContentConfig::META_TYPE_NUMBER => esc_html($this->formatMetaNumber($value)),
            default => esc_html($value),
        };
    }

    private function normalizeImageMetaValue(string $value): string
    {
        $imageUrl = is_numeric($value) ? wp_get_attachment_url((int) $value) : $value;
        if (!is_string($imageUrl) || $imageUrl === '') {
            return '';
        }

        $safeUrl = esc_url_raw($imageUrl);
        return $safeUrl !== '' ? esc_html($safeUrl) : '';
    }

    private function avatarUrl(int $userId, int $size): string
    {
        if (function_exists('get_avatar_url')) {
            $url = get_avatar_url($userId, ['size' => $size]);
            return is_string($url) ? esc_url($url) : '';
        }

        $avatar = get_avatar($userId, $size);
        if (!is_string($avatar) || $avatar === '') {
            return '';
        }

        if (preg_match('/\ssrc=(["\'])(.*?)\1/i', $avatar, $matches) !== 1) {
            return '';
        }

        return esc_url($matches[2]);
    }

    private function formatMetaNumber(string $value): string
    {
        return str_contains($value, '.') ? number_format((float) $value, 2) : (string) (int) $value;
    }

    private function currentPostId(): int
    {
        // Section: prefer the queried page/post object over the active loop item.
        // Archive, taxonomy, and blog-index renders can have get_the_ID() set to
        // the first loop post, which would make "current page/post" bindings lie.
        $queriedPostId = $this->queriedPostId();
        if ($queriedPostId > 0) {
            return $queriedPostId;
        }

        if ($this->hasExplicitNonPostQueryContext()) {
            return 0;
        }

        $loopPostId = (int) get_the_ID();
        if ($loopPostId <= 0) {
            return 0;
        }

        return $this->isRenderablePostContext($loopPostId) ? $loopPostId : 0;
    }

    private function queriedPostId(): int
    {
        if (\function_exists('get_queried_object')) {
            $queried = get_queried_object();
            if (\is_object($queried) && isset($queried->ID)) {
                $postId = (int) $queried->ID;
                if ($postId > 0 && $this->isRenderablePostContext($postId)) {
                    return $postId;
                }
            }
        }

        if (!\function_exists('get_queried_object_id')) {
            return 0;
        }

        $postId = (int) get_queried_object_id();
        if ($postId <= 0) {
            return 0;
        }

        return $this->isRenderablePostContext($postId) ? $postId : 0;
    }

    private function hasExplicitNonPostQueryContext(): bool
    {
        if (!\function_exists('get_queried_object')) {
            return false;
        }

        $queried = get_queried_object();
        if (!\is_object($queried)) {
            return false;
        }

        if (!isset($queried->ID)) {
            return true;
        }

        return !$this->isRenderablePostContext((int) $queried->ID);
    }

    private function isRenderablePostContext(int $postId): bool
    {
        $post = get_post($postId);

        return \is_object($post) && isset($post->ID);
    }

    private function currentPostAuthorId(): int
    {
        $postId = $this->currentPostId();
        return $postId > 0 ? (int) get_post_field('post_author', $postId) : 0;
    }

    private function currentShareUrl(): string
    {
        if ($this->pageIdentity instanceof StaticExportPageIdentity) {
            return esc_url($this->pageIdentity->permalink());
        }

        $postId = $this->currentPostId();
        if ($postId > 0) {
            return esc_url(get_permalink($postId));
        }

        return esc_url(home_url('/'));
    }

    private function currentShareTitle(): string
    {
        if ($this->pageIdentity instanceof StaticExportPageIdentity) {
            return esc_html($this->pageIdentity->title());
        }

        $postId = $this->currentPostId();
        if ($postId > 0) {
            return esc_html(get_the_title($postId));
        }

        return esc_html(get_bloginfo('name'));
    }

    private function adjacentPost(bool $previous): ?object
    {
        $postId = $this->currentPostId();
        if ($postId <= 0) {
            return null;
        }

        $current = get_post($postId);
        if (!$current) {
            return null;
        }

        $originalPost = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $current;

        try {
            $adjacent = $previous ? get_previous_post() : get_next_post();
        } finally {
            $GLOBALS['post'] = $originalPost;
        }

        return is_object($adjacent) ? $adjacent : null;
    }

    private function renderShareEmail(): string
    {
        $subject = rawurlencode(html_entity_decode($this->currentShareTitle(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $body = rawurlencode(html_entity_decode($this->currentShareUrl(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return esc_url(sprintf('mailto:?subject=%s&body=%s', $subject, $body));
    }

    private function renderShareFacebook(): string
    {
        $url = rawurlencode(html_entity_decode($this->currentShareUrl(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return esc_url('https://www.facebook.com/sharer/sharer.php?u=' . $url);
    }

    private function renderShareLinkedIn(): string
    {
        $url = rawurlencode(html_entity_decode($this->currentShareUrl(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return esc_url('https://www.linkedin.com/sharing/share-offsite/?url=' . $url);
    }

    private function renderShareTwitter(): string
    {
        $url = rawurlencode(html_entity_decode($this->currentShareUrl(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = rawurlencode(html_entity_decode($this->currentShareTitle(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return esc_url(sprintf('https://twitter.com/intent/tweet?url=%s&text=%s', $url, $text));
    }

    // ── Slice 6: complex self-rendering surfaces ──────────────────────────

    private function renderTotalPostsCount(): string
    {
        $counts = wp_count_posts('post');
        return (string) (int) ($counts->publish ?? 0);
    }

    private function renderTotalCommentsCount(): string
    {
        $counts = wp_count_comments();
        return (string) (int) ($counts->approved ?? 0);
    }

    private function renderTotalUsersCount(): string
    {
        $result = count_users();
        return (string) (int) ($result['total_users'] ?? 0);
    }

    /**
     * Render a single option value — only for allowlisted, display-safe keys.
     * Fails closed (empty) for unknown keys so secrets can never be surfaced.
     */
    private function renderOption(array $args): string
    {
        $key = sanitize_key($args['key'] ?? '');
        if ($key === '' || !$this->isAllowedOptionKey($key)) {
            return '';
        }

        $value = get_option($key);
        if (!is_scalar($value)) {
            return '';
        }

        return esc_html((string) $value);
    }

    private function isAllowedOptionKey(string $key): bool
    {
        $allowed = self::PUBLIC_OPTION_KEYS;

        if (\function_exists(__NAMESPACE__ . '\\apply_filters') || \function_exists('apply_filters')) {
            $filtered = apply_filters('uncanny_page_builder_public_option_keys', $allowed);
            if (is_array($filtered)) {
                $allowed = array_values(array_filter(
                    $filtered,
                    static fn(mixed $v): bool => is_string($v) && $v !== ''
                ));
            }
        }

        return in_array($key, $allowed, true);
    }

    private function renderPagesList(array $args): string
    {
        $depth = max(0, (int) ($args['depth'] ?? 0));
        $html = wp_list_pages(['echo' => false, 'title_li' => '', 'depth' => $depth]);
        if (!is_string($html) || trim($html) === '') {
            return '';
        }
        return '<ul class="upb-pages-list">' . wp_kses_post($html) . '</ul>';
    }

    private function renderCategoriesList(array $args): string
    {
        $html = wp_list_categories([
            'echo'         => false,
            'title_li'     => '',
            'show_count'   => (bool) ($args['show_count'] ?? false),
            'hierarchical' => (bool) ($args['hierarchical'] ?? true),
        ]);
        if (!is_string($html) || trim($html) === '') {
            return '';
        }
        return '<ul class="upb-categories-list">' . wp_kses_post($html) . '</ul>';
    }

    private function renderArchivesList(array $args): string
    {
        $type = $args['type'] ?? 'monthly';
        $type = in_array($type, self::ALLOWED_ARCHIVE_TYPES, true) ? $type : 'monthly';
        $limit = max(0, (int) ($args['count'] ?? 12));

        $options = ['echo' => false, 'type' => $type, 'format' => 'html'];
        if ($limit > 0) {
            $options['limit'] = $limit;
        }

        $html = wp_get_archives($options);
        if (!is_string($html) || trim($html) === '') {
            return '';
        }
        return '<ul class="upb-archives-list">' . wp_kses_post($html) . '</ul>';
    }

    private function renderTagCloud(array $args): string
    {
        $taxonomy = sanitize_key($args['taxonomy'] ?? 'post_tag');
        $number = max(0, (int) ($args['count'] ?? 45));

        $html = wp_tag_cloud(['echo' => false, 'taxonomy' => $taxonomy, 'number' => $number]);
        if (!is_string($html) || trim($html) === '') {
            return '';
        }
        return wp_kses_post($html);
    }

    private function renderBreadcrumbs(array $args): string
    {
        $separator = trim((string) ($args['separator'] ?? '/'));
        if ($separator === '') {
            $separator = '/';
        }

        $crumbs = [
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(home_url('/')),
                esc_html_x('Home', 'Breadcrumb label', 'uncanny-automator')
            ),
        ];

        $id = (int) get_the_ID();
        if ($id > 0) {
            foreach (array_reverse(get_post_ancestors($id)) as $ancestorId) {
                $crumbs[] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(get_permalink($ancestorId)),
                    esc_html(get_the_title($ancestorId))
                );
            }
            $crumbs[] = '<span aria-current="page">' . esc_html(get_the_title($id)) . '</span>';
        }

        $sep = ' <span class="upb-breadcrumb-sep">' . esc_html($separator) . '</span> ';
        return '<nav class="upb-breadcrumbs" aria-label="Breadcrumb">' . implode($sep, $crumbs) . '</nav>';
    }

    private function renderPaginationLinks(): string
    {
        $links = paginate_links(['type' => 'list']);
        if (!is_string($links) || trim($links) === '') {
            return '';
        }
        return '<nav class="upb-pagination" aria-label="Page navigation">' . wp_kses_post($links) . '</nav>';
    }

    /**
     * Render a post's attached images as a bounded gallery. Built directly from
     * attachments (not the [gallery] shortcode) so output is predictable and the
     * item count is capped.
     */
    private function renderGallery(array $args): string
    {
        $postId = (int) ($args['post_id'] ?? get_the_ID());
        if ($postId <= 0) {
            return '';
        }
        $count = max(1, min(50, (int) ($args['count'] ?? 10)));
        $size = $args['size'] ?? 'medium';
        $size = in_array($size, self::ALLOWED_IMAGE_SIZES, true) ? $size : 'medium';

        $attachments = get_attached_media('image', $postId);
        if (empty($attachments)) {
            return '';
        }

        $items = '';
        $rendered = 0;
        foreach ($attachments as $attachment) {
            if ($rendered >= $count) {
                break;
            }
            $img = wp_get_attachment_image((int) $attachment->ID, $size);
            if (is_string($img) && $img !== '') {
                $items .= '<figure class="upb-gallery-item">' . wp_kses_post($img) . '</figure>';
                $rendered++;
            }
        }

        return $items === '' ? '' : '<div class="upb-gallery">' . $items . '</div>';
    }

    /**
     * Embed a URL via WordPress oEmbed. wp_oembed_get only resolves URLs from
     * WordPress's sanctioned provider list. Discovery must stay disabled here:
     * otherwise arbitrary URLs can trigger remote discovery fetches.
     */
    private function renderEmbed(array $args): string
    {
        $url = esc_url_raw(trim((string) ($args['url'] ?? '')));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }

        $html = wp_oembed_get($url, ['discover' => false]);
        if (!is_string($html) || $html === '') {
            return '<!-- embed unavailable -->';
        }

        return '<div class="upb-embed">' . $html . '</div>';
    }

    /**
     * Execute a shortcode string unless one of its tags is blocklisted.
     */
    private function renderShortcode(array $args): string
    {
        $shortcode = trim((string) ($args['shortcode'] ?? ''));
        if ($shortcode === '') {
            return '';
        }

        $tags = $this->shortcodeTagsIn($shortcode);
        if ($tags === []) {
            return '';
        }

        foreach ($tags as $tag) {
            if ($this->isBlockedShortcode($tag)) {
                return '<!-- shortcode "' . esc_html($tag) . '" not allowed -->';
            }
        }

        return do_shortcode($shortcode);
    }

    /**
     * @return string[] lowercased shortcode tags appearing in the string
     */
    private function shortcodeTagsIn(string $shortcode): array
    {
        if (preg_match_all('/\[\s*([a-zA-Z0-9_\-]+)/', $shortcode, $matches)) {
            return array_values(array_unique(array_map('strtolower', $matches[1])));
        }
        return [];
    }

    private function isBlockedShortcode(string $tag): bool
    {
        $blocked = self::DEFAULT_BLOCKED_SHORTCODES;

        if (\function_exists(__NAMESPACE__ . '\\apply_filters') || \function_exists('apply_filters')) {
            $filtered = apply_filters('uncanny_page_builder_blocked_shortcodes', $blocked);
            if ($filtered === true) {
                return true;
            }
            if (is_array($filtered)) {
                $blocked = array_values(array_filter(
                    $filtered,
                    static fn(mixed $v): bool => is_string($v) && $v !== ''
                ));
            }
        }

        return in_array($tag, array_map('strtolower', $blocked), true);
    }

    private function renderAdminEmail(): string
    {
        // Output depends on the viewer's capability, so the rendered page must
        // not be served from a full-page cache to other (non-admin) visitors.
        $this->markPageAsNonCacheable();

        if (!$this->currentUserCan('manage_options')) {
            return '';
        }

        return esc_html(get_bloginfo('admin_email'));
    }

    private function renderCurrentUserName(): string
    {
        $user = $this->currentUserForPersonalizedBinding();

        return $user ? esc_html($user->display_name ?? '') : '';
    }

    private function renderCurrentUserAvatar(): string
    {
        $user = $this->currentUserForPersonalizedBinding();
        if (!$user) {
            return '';
        }

        return $this->avatarUrl((int) ($user->ID ?? get_current_user_id()), 96);
    }

    private function renderCurrentUserEmail(): string
    {
        $user = $this->currentUserForPersonalizedBinding();

        return $user ? esc_html($user->user_email ?? '') : '';
    }

    private function renderCurrentUserRole(): string
    {
        $user = $this->currentUserForPersonalizedBinding();
        if (!$user) {
            return '';
        }

        $roles = $user->roles ?? [];

        return esc_html(!empty($roles) ? (string) reset($roles) : '');
    }

    private function renderCurrentUserBio(): string
    {
        $user = $this->currentUserForPersonalizedBinding();
        if (!$user) {
            return '';
        }

        return wp_kses_post(get_the_author_meta('description', (int) ($user->ID ?? get_current_user_id())));
    }

    private function renderCurrentUserUrl(): string
    {
        $user = $this->currentUserForPersonalizedBinding();
        if (!$user) {
            return '';
        }

        return esc_url(get_author_posts_url((int) ($user->ID ?? get_current_user_id())));
    }

    private function renderLogoutUrl(): string
    {
        $user = $this->currentUserForPersonalizedBinding();
        if (!$user) {
            return '';
        }

        return esc_url(wp_logout_url(home_url('/')));
    }

    private function currentUserForPersonalizedBinding(): ?object
    {
        // Current-user bindings are personalized fragments; prevent full-page
        // caches from serving one visitor's rendered value to another visitor.
        $this->markPageAsNonCacheable();

        if (!$this->isUserLoggedIn()) {
            return null;
        }

        return wp_get_current_user();
    }

    private function getPostTypeLabel(): string
    {
        $postType = get_post_type($this->currentPostId());
        if (!$postType) {
            return '';
        }
        $obj = get_post_type_object($postType);
        return $obj ? ($obj->labels->singular_name ?? $postType) : $postType;
    }

    private function currentUserCan(string $capability): bool
    {
        if (!\function_exists(__NAMESPACE__ . '\\current_user_can') && !\function_exists('current_user_can')) {
            return false;
        }

        return (bool) current_user_can($capability);
    }

    private function isUserLoggedIn(): bool
    {
        if (!\function_exists(__NAMESPACE__ . '\\is_user_logged_in') && !\function_exists('is_user_logged_in')) {
            return false;
        }

        return (bool) is_user_logged_in();
    }

    private function markPageAsNonCacheable(): void
    {
        if (!\defined('DONOTCACHEPAGE')) {
            \define('DONOTCACHEPAGE', true);
        }
    }

    private function renderRegistrationForm(): string
    {
        // Output branches on auth state (logged-in users see a personalized
        // welcome with their display name); never reuse it from a page cache.
        $this->markPageAsNonCacheable();

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return sprintf(
                '<p>%s, %s. <a href="%s">%s</a></p>',
                esc_html_x('Welcome', 'Logged-in user greeting', 'uncanny-automator'),
                esc_html($user->display_name),
                esc_url(wp_logout_url(home_url('/'))),
                esc_html_x('Log out', 'Page Builder', 'uncanny-automator')
            );
        }

        if (!get_option('users_can_register')) {
            return '<p>' . esc_html_x('Registration is currently disabled.', 'Page Builder', 'uncanny-automator') . '</p>';
        }

        return sprintf(
            '<form method="post" action="%s">'
            . '<p><label>%s<br /><input type="text" name="user_login" required /></label></p>'
            . '<p><label>%s<br /><input type="email" name="user_email" required /></label></p>'
            . '%s'
            . '<p><input type="submit" value="%s" /></p>'
            . '</form>',
            esc_url(site_url('wp-login.php?action=register', 'login')),
            esc_html_x('Username', 'Page Builder', 'uncanny-automator'),
            esc_html_x('Email', 'Page Builder', 'uncanny-automator'),
            wp_nonce_field('register', '_wpnonce', true, false),
            esc_attr_x('Register', 'Registration form submit button label', 'uncanny-automator')
        );
    }

    private function renderLoginForm(array $args): string
    {
        // Output branches on auth state (logged-in users see a personalized
        // welcome with their display name); never reuse it from a page cache.
        $this->markPageAsNonCacheable();

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return sprintf(
                '<p>%s, %s. <a href="%s">%s</a></p>',
                esc_html_x('Welcome', 'Logged-in user greeting', 'uncanny-automator'),
                esc_html($user->display_name),
                esc_url(wp_logout_url(home_url('/'))),
                esc_html_x('Log out', 'Page Builder', 'uncanny-automator')
            );
        }

        $redirect = esc_url_raw($args['redirect'] ?? home_url('/'));
        // Prevent open redirect to external domains.
        $redirect = wp_validate_redirect($redirect, home_url('/'));

        return wp_login_form([
            'echo'     => false,
            'redirect' => $redirect,
            'remember' => true,
        ]);
    }
}
