<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Domain\Export\StaticExportPageIdentity;

/**
 * Render data-ai-dynamic="wp_comments" regions.
 *
 * Queries approved comments and renders them using the card template.
 */
final class WpCommentsCardRenderer implements SectionRendererInterface
{
    private const ALLOWED_ORDERBY = [
        'comment_date_gmt', 'comment_date', 'comment_author',
        'comment_type', 'comment_ID',
    ];

    public function __construct(
        private readonly ?StaticExportPageIdentity $pageIdentity = null,
    ) {}

    public function render(string $cardTemplate, array $args): string
    {
        // Section: declaration defaults pass post_id=0 through the renderer, so
        // the "current post" contract must be re-applied here instead of only
        // relying on the null-coalescing fallback.
        $postId  = (int) ($args['post_id'] ?? 0);
        if ($postId <= 0) {
            $postId = $this->pageIdentity?->pageId() ?? (int) get_the_ID();
        }
        if ($postId <= 0) {
            return '<!-- No comments found -->';
        }
        $count   = DynamicCardCount::resolve($args['count'] ?? null, 5);
        $orderby = in_array($args['orderby'] ?? 'comment_date_gmt', self::ALLOWED_ORDERBY, true)
            ? ($args['orderby'] ?? 'comment_date_gmt')
            : 'comment_date_gmt';

        $comments = get_comments([
            'post_id' => $postId,
            'status'  => 'approve',
            'number'  => $count,
            'orderby' => $orderby,
            'order'   => 'DESC',
        ]);

        if (empty($comments)) {
            return '<!-- No comments found -->';
        }

        $output = '';
        foreach ($comments as $comment) {
            $card = $cardTemplate;

            $replacements = [
                'author'  => esc_html($comment->comment_author),
                'avatar'  => esc_url(get_avatar_url($comment->comment_author_email, ['size' => 96]) ?: ''),
                'content' => wp_kses_post($comment->comment_content),
                'date'    => esc_html(get_comment_date('', $comment)),
                'url'     => esc_url(get_comment_link($comment)),
            ];

            foreach ($replacements as $key => $value) {
                if ($key === 'avatar') {
                    $card = CardBindingEngine::image($card, 'avatar', $value);
                } elseif ($key === 'url') {
                    $card = CardBindingEngine::href($card, 'url', $value);
                } else {
                    $card = CardBindingEngine::text($card, $key, $value);
                }
            }

            $output .= $card . "\n";
        }

        return $output;
    }
}
