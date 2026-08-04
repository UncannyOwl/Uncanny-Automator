<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Controls\PageTrashUrlPortInterface;

/**
 * Builds the native WordPress trash action without exposing escaped HTML URLs.
 */
final class WordPressPageTrashUrlPort implements PageTrashUrlPortInterface
{
    public function __construct(
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    public function forPage(int $pageId): ?string
    {
        $post = get_post($pageId);
        if (
            !is_object($post)
            || !$this->supportsPostType->isSupported((string) ($post->post_type ?? ''))
            || (string) ($post->post_status ?? '') === 'trash'
        ) {
            return null;
        }

        $link = get_delete_post_link($pageId);
        if (!is_string($link) || $link === '') {
            return null;
        }

        $link = html_entity_decode($link, ENT_QUOTES, 'UTF-8');
        if (!$this->isTrashAction($link)) {
            return null;
        }

        return add_query_arg(
            '_wp_http_referer',
            AdminCanvasEditorWindowedPage::pagesScreenUrl(),
            $link,
        );
    }

    private function isTrashAction(string $link): bool
    {
        $query = parse_url($link, PHP_URL_QUERY);
        if (!is_string($query)) {
            return false;
        }

        parse_str($query, $params);

        return ($params['action'] ?? null) === 'trash';
    }
}
