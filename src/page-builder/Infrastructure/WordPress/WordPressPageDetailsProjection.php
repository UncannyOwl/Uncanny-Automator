<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Controls\PageDetails;
use UncannyPageBuilder\Application\Controls\PageDetailsProjectionInterface;

/**
 * Read-only WordPress adapter for adoption values and draft URL presentation.
 *
 * The displayed permalink is a future URL preview. WordPress public title,
 * slug, and routing fields are deliberately never changed here.
 */
final class WordPressPageDetailsProjection implements PageDetailsProjectionInterface
{
    public function __construct(
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    public function readPublicPage(int $pageId): ?PageDetails
    {
        $post = $this->page($pageId);
        if ($post === null) {
            return null;
        }

        $title = $this->resolvedTitle((string) ($post->post_title ?? ''), $pageId);
        $slug = sanitize_title((string) ($post->post_name ?? ''));
        if ($slug === '') {
            $slug = sanitize_title($title);
        }

        return $this->details($post, $title, $slug);
    }

    public function projectDraft(int $pageId, string $title, string $slug): ?PageDetails
    {
        $post = $this->page($pageId);
        if ($post === null) {
            return null;
        }

        $resolvedTitle = $this->resolvedTitle($title, $pageId);
        $resolvedSlug = sanitize_title($slug);
        if ($resolvedSlug === '') {
            $resolvedSlug = sanitize_title($resolvedTitle);
        }

        return $this->details($post, $resolvedTitle, $resolvedSlug);
    }

    private function page(int $pageId): ?object
    {
        $post = get_post($pageId);

        return is_object($post)
            && $this->supportsPostType->isSupported((string) ($post->post_type ?? ''))
            ? $post
            : null;
    }

    private function resolvedTitle(string $title, int $pageId): string
    {
        $resolved = sanitize_text_field(trim($title));

        return $resolved !== ''
            ? $resolved
            : sprintf(
                /* translators: %d: The WordPress page ID. */
                _x('Untitled page #%d', 'Page Builder', 'uncanny-automator'),
                $pageId,
            );
    }

    private function details(object $post, string $title, string $slug): PageDetails
    {
        [$permalink, $prefix, $suffix] = $this->draftPermalink($post, $title, $slug);
        /*
         * A draft preview is the authenticated working canvas, never the
         * public permalink. Once exact-pointer rendering is activated, a
         * WordPress preview URL would correctly resolve the public artifact
         * and could misleadingly hide unsaved working changes.
         */
        $previewUrl = add_query_arg(
            'upb_preview',
            '1',
            AdminCanvasPage::editorUrl((int) $post->ID),
        );

        return new PageDetails(
            pageId: (int) $post->ID,
            title: html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            slug: $slug,
            permalink: $permalink,
            permalinkPrefix: $prefix,
            permalinkSuffix: $suffix,
            previewUrl: is_string($previewUrl) && $previewUrl !== '' ? $previewUrl : $permalink,
            permalinkIsLive: false,
        );
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function draftPermalink(object $post, string $title, string $slug): array
    {
        $this->loadSamplePermalinkFunction();

        if (function_exists('get_sample_permalink')) {
            $sample = get_sample_permalink((int) $post->ID, $title, $slug);
            if (is_array($sample) && isset($sample[0])) {
                [$prefix, $suffix] = $this->permalinkParts((string) $sample[0]);
                if ($prefix !== '' || $suffix !== '') {
                    return [$prefix . $slug . $suffix, $prefix, $suffix];
                }
            }
        }

        $permalink = get_permalink((int) $post->ID);

        return [is_string($permalink) ? $permalink : '', '', ''];
    }

    private function loadSamplePermalinkFunction(): void
    {
        if (function_exists('get_sample_permalink') || !defined('ABSPATH')) {
            return;
        }

        $file = ABSPATH . 'wp-admin/includes/post.php';
        if (is_file($file)) {
            require_once $file;
        }
    }

    /** @return array{0: string, 1: string} */
    private function permalinkParts(string $template): array
    {
        foreach (['%pagename%', '%postname%'] as $placeholder) {
            if (str_contains($template, $placeholder)) {
                $parts = explode($placeholder, $template, 2);

                return [(string) ($parts[0] ?? ''), (string) ($parts[1] ?? '')];
            }
        }

        return ['', ''];
    }
}
