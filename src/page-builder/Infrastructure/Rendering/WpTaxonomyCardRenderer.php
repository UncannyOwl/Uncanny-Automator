<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

/**
 * Render data-ai-dynamic="wp_taxonomy" regions.
 *
 * Queries terms from any taxonomy (categories, tags, custom) and
 * renders them using the card template with data-ai-bind keys.
 */
final class WpTaxonomyCardRenderer implements SectionRendererInterface
{
    private const ALLOWED_ORDERBY = [
        'name',
        'slug',
        'term_group',
        'term_id',
        'id',
        'description',
        'count',
        'include',
    ];

    public function render(string $cardTemplate, array $args): string
    {
        $taxonomy  = sanitize_key($args['taxonomy'] ?? 'category');
        $count     = DynamicCardCount::resolve($args['count'] ?? null, 6);
        $orderby   = $this->sanitizeOrderby($args['orderby'] ?? 'name');
        $hideEmpty = (bool) ($args['hide_empty'] ?? true);
        $parent    = (int) ($args['parent'] ?? -1);

        $termArgs = [
            'taxonomy'   => $taxonomy,
            'number'     => $count,
            'orderby'    => $orderby,
            'hide_empty' => $hideEmpty,
        ];

        if ($parent >= 0) {
            $termArgs['parent'] = $parent;
        }

        $terms = get_terms($termArgs);

        if (is_wp_error($terms) || empty($terms)) {
            return '<!-- No terms found -->';
        }

        $output = '';
        foreach ($terms as $term) {
            $card = $cardTemplate;

            $thumbnail = '';
            $thumbId = get_term_meta($term->term_id, 'thumbnail_id', true);
            if ($thumbId) {
                $thumbnail = wp_get_attachment_image_url((int) $thumbId, 'medium') ?: '';
            }

            $termLink = get_term_link($term);
            $permalink = is_wp_error($termLink) ? '' : esc_url($termLink);

            $replacements = [
                'name'        => esc_html($term->name),
                'description' => wp_kses_post($term->description),
                'count'       => esc_html((string) $term->count),
                'permalink'   => $permalink,
                'thumbnail'   => esc_url($thumbnail),
                'slug'        => esc_html($term->slug),
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

    private function sanitizeOrderby(mixed $orderby): string
    {
        return is_string($orderby) && in_array($orderby, self::ALLOWED_ORDERBY, true)
            ? $orderby
            : 'name';
    }
}
