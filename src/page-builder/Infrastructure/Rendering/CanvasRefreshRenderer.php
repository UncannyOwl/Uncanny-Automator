<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Application\Canvas\CanvasRefreshRendererInterface;

/**
 * Keeps authenticated editor refresh reads outside the public renderer.
 */
final class CanvasRefreshRenderer implements CanvasRefreshRendererInterface
{
    /** @var list<string> */
    private const QUERY_GLOBALS = [
        'wp_query',
        'wp_the_query',
        'post',
        'id',
        'authordata',
        'currentday',
        'currentmonth',
        'page',
        'pages',
        'multipage',
        'more',
        'numpages',
    ];

    public function __construct(
        private readonly CanvasRenderer $sections,
        private readonly PageJavaScriptRuntimeRenderer $javaScript,
    ) {}

    public function withOwnerRenderContext(int $ownerId, callable $projection): mixed
    {
        if ($ownerId <= 0) {
            throw new \InvalidArgumentException('Canvas refresh owner ID must be positive.');
        }

        $post = get_post($ownerId);
        if (!$post instanceof \WP_Post) {
            throw new \RuntimeException('Canvas refresh owner post was not found.');
        }

        $snapshot = $this->snapshotQueryGlobals();
        $query = $this->ownerQuery($post);

        try {
            $GLOBALS['wp_query'] = $query;
            $GLOBALS['wp_the_query'] = $query;
            $GLOBALS['post'] = $post;
            setup_postdata($post);

            return $projection();
        } finally {
            $this->restoreQueryGlobals($snapshot);
        }
    }

    public function renderSections(array $sections, int $ownerId): array
    {
        $rendered = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionId = (int) ($section['id'] ?? 0);
            $content = is_array($section['content'] ?? null) ? $section['content'] : [];
            $this->sections->notifyBeforeSectionRender($section, $ownerId);
            $rendered[] = [
                'id' => $sectionId,
                'html' => $this->sections->renderSectionHtml((string) ($content['html'] ?? ''), $sectionId ?: null),
            ];
        }

        return $rendered;
    }

    public function hasCurrentJavaScript(int $ownerId, ?array $header = null, ?array $footer = null): bool
    {
        return $this->javaScript->renderStandaloneCanvasScripts($ownerId, $header, $footer) !== '';
    }

    public function hasPageSourceJavaScript(
        int $pageId,
        string $javaScript,
        ?array $header = null,
        ?array $footer = null,
    ): bool {
        return $this->javaScript->renderStandaloneCanvasScriptsFromPageSource(
            $pageId,
            $javaScript,
            $header,
            $footer,
        ) !== '';
    }

    private function ownerQuery(\WP_Post $post): \WP_Query
    {
        $query = new \WP_Query();
        $postType = get_post_type($post);
        $isGlobalPart = $postType === 'upb_global_part';

        $query->posts = [$post];
        $query->post = $post;
        $query->post_count = 1;
        $query->found_posts = 1;
        $query->queried_object = $post;
        $query->queried_object_id = (int) $post->ID;
        $query->is_singular = true;
        $query->is_single = !$isGlobalPart;
        $query->is_page = !$isGlobalPart;
        $query->set($postType === 'page' ? 'page_id' : 'p', (int) $post->ID);
        $query->set('post_type', is_string($postType) ? $postType : '');

        return $query;
    }

    /**
     * @return array<string, array{present: bool, value: mixed}>
     */
    private function snapshotQueryGlobals(): array
    {
        $snapshot = [];
        foreach (self::QUERY_GLOBALS as $key) {
            $snapshot[$key] = [
                'present' => array_key_exists($key, $GLOBALS),
                'value' => $GLOBALS[$key] ?? null,
            ];
        }

        return $snapshot;
    }

    /**
     * @param array<string, array{present: bool, value: mixed}> $snapshot
     */
    private function restoreQueryGlobals(array $snapshot): void
    {
        foreach ($snapshot as $key => $state) {
            if ($state['present']) {
                $GLOBALS[$key] = $state['value'];
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }
}
