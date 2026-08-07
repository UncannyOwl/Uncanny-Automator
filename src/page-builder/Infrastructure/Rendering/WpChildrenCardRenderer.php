<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Domain\Export\StaticExportPageIdentity;

/**
 * Render data-ai-dynamic="wp_children" regions.
 *
 * Queries child pages/posts of a given parent and renders card templates.
 */
final class WpChildrenCardRenderer implements SectionRendererInterface
{
    private const ALLOWED_ORDERBY = [
        'menu_order',
        'title',
        'date',
        'modified',
        'ID',
        'name',
        'rand',
    ];

    public function __construct(
        private readonly ?StaticExportPageIdentity $pageIdentity = null,
    ) {}

    public function render(string $cardTemplate, array $args): string
    {
        $bindingId = (string) ($args['_binding_id'] ?? 'wp_children');
        $count     = DynamicCardCount::resolve($args['count'] ?? null, 10);
        $orderby   = $this->sanitizeOrderby($args['orderby'] ?? 'menu_order');

        if ($bindingId === 'wp_pages') {
            // Top-level pages, independent of the current post context.
            $parentId = 0;
            $postType = 'page';
        } else {
            // wp_children: parent defaults to the current page; a parent of 0
            // (the declaration default) also means "current page" per the guide.
            $rawParent = (int) ($args['parent'] ?? 0);
            $parentId  = $rawParent > 0 ? $rawParent : $this->currentPostId();
            $postType  = sanitize_key($args['post_type'] ?? 'page');

            // No explicit parent and no current post context: fail closed rather
            // than querying post_parent=0 (which would return ALL top-level pages).
            if ($parentId <= 0) {
                return '<!-- No child pages found -->';
            }
        }

        $children = get_posts([
            'post_parent'    => $parentId,
            'post_type'      => $postType,
            'posts_per_page' => $count,
            'orderby'        => $orderby,
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ]);

        if (empty($children)) {
            return $bindingId === 'wp_pages' ? '<!-- No pages found -->' : '<!-- No child pages found -->';
        }

        $output = '';
        foreach ($children as $child) {
            $card = $cardTemplate;

            $thumbUrl = get_the_post_thumbnail_url($child->ID, 'medium') ?: '';

            $replacements = [
                'title'     => esc_html($child->post_title),
                'excerpt'   => esc_html(wp_trim_words(get_the_excerpt($child), 20)),
                'permalink' => esc_url(get_permalink($child)),
                'thumbnail' => esc_url($thumbUrl),
                'date'      => esc_html(get_the_date('', $child)),
                'order'     => esc_html((string) $child->menu_order),
            ];

            // permalink → href only, thumbnail → image only; applying text() to
            // either would overwrite an anchor's label / be dropped on void <img>.
            foreach ($replacements as $key => $value) {
                if ($key === 'thumbnail') {
                    $card = CardBindingEngine::image($card, 'thumbnail', $value);
                } elseif ($key === 'permalink') {
                    $card = CardBindingEngine::href($card, 'permalink', $value);
                } else {
                    $card = CardBindingEngine::text($card, $key, $value);
                }
            }

            $output .= $card . "\n";
        }

        return $output;
    }

    private function currentPostId(): int
    {
        return $this->pageIdentity?->pageId() ?? (int) get_the_ID();
    }

    private function sanitizeOrderby(mixed $orderby): string
    {
        return is_string($orderby) && in_array($orderby, self::ALLOWED_ORDERBY, true)
            ? $orderby
            : 'menu_order';
    }
}
