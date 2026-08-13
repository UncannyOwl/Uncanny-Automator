<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Binding\BindingStaticSafety;

/**
 * Decides whether dynamic binding regions may be frozen into a saved artifact.
 */
final class StaticRenderingPolicy
{
    public const VERSION = '2';

    /** @var array<string, string> */
    private const STATIC_SAFE = [
        'atom_url' => 'static_safe',
        'author_box' => 'public_request_safe',
        'copyright_year' => 'static_safe',
        'home_link' => 'static_safe',
        'if_comments_open' => 'public_request_safe',
        'if_has_excerpt' => 'public_request_safe',
        'if_has_menu' => 'public_request_safe',
        'if_has_thumbnail' => 'public_request_safe',
        'login_url' => 'static_safe',
        'lost_password_url' => 'static_safe',
        'page_author' => 'public_request_safe',
        'page_author_avatar' => 'public_request_safe',
        'page_date' => 'static_safe',
        'page_excerpt' => 'public_request_safe',
        'page_featured_image' => 'public_request_safe',
        'page_modified_date' => 'static_safe',
        'page_permalink' => 'static_safe',
        'page_title' => 'static_safe',
        'post_author_url' => 'static_safe',
        'post_category' => 'public_request_safe',
        'post_comment_count' => 'public_request_safe',
        'post_next_link' => 'static_safe',
        'post_prev_link' => 'static_safe',
        'post_prev_next' => 'static_safe',
        'post_reading_time' => 'public_request_safe',
        'post_tags' => 'public_request_safe',
        'post_type_archive_link' => 'static_safe',
        'post_type_label' => 'static_safe',
        'privacy_policy_url' => 'static_safe',
        'register_url' => 'static_safe',
        'rss_url' => 'static_safe',
        'search_form' => 'public_request_safe',
        'share_email' => 'static_safe',
        'share_facebook' => 'static_safe',
        'share_linkedin' => 'static_safe',
        'share_twitter' => 'static_safe',
        'site_language' => 'static_safe',
        'site_logo' => 'static_safe',
        'site_tagline' => 'static_safe',
        'site_title' => 'static_safe',
        'site_url' => 'static_safe',
        'total_comments_count' => 'static_safe',
        'total_posts_count' => 'static_safe',
        'total_users_count' => 'public_request_safe',
        'wp_archives_list' => 'public_request_safe',
        'wp_breadcrumbs' => 'public_request_safe',
        'wp_categories_list' => 'public_request_safe',
        'wp_children' => 'public_request_safe',
        'wp_comments' => 'public_request_safe',
        'wp_gallery' => 'public_request_safe',
        'wp_menu' => 'public_request_safe',
        'wp_pages' => 'public_request_safe',
        'wp_pages_list' => 'public_request_safe',
        'wp_pagination' => 'public_request_safe',
        'wp_popular_posts' => 'public_request_safe',
        'wp_recent_posts' => 'public_request_safe',
        'wp_related' => 'public_request_safe',
        'wp_sticky_posts' => 'public_request_safe',
        'wp_tag_cloud' => 'public_request_safe',
        'wp_taxonomy' => 'public_request_safe',
        'wp_users' => 'public_request_safe',
        'wp_option' => 'public_request_safe',
    ];

    /**
     * Fallback classification used only when no BindingRegistry is injected.
     * Must mirror the canonical `static_safety` field in each binding's
     * declaration.json. admin_email is privileged (manage_options-gated at
     * runtime) so it is request_sensitive, never static_safe — freezing it
     * would bake the admin address into an artifact served to every visitor.
     *
     * @var array<string, string>
     */
    private const REQUEST_SENSITIVE = [
        'admin_email' => 'request_sensitive',
        'current_user_avatar' => 'request_sensitive',
        'current_user_bio' => 'request_sensitive',
        'current_user_email' => 'request_sensitive',
        'current_user_name' => 'request_sensitive',
        'current_user_role' => 'request_sensitive',
        'current_user_url' => 'request_sensitive',
        'if_can' => 'request_sensitive',
        'if_logged_in' => 'request_sensitive',
        'if_logged_out' => 'request_sensitive',
        'if_role' => 'request_sensitive',
        'login_form' => 'request_sensitive',
        'logout_url' => 'request_sensitive',
        'registration_form' => 'request_sensitive',
        'user_meta' => 'request_sensitive',
        'wp_query' => 'request_sensitive',
    ];

    public function __construct(private readonly ?BindingRegistry $bindings = null)
    {
    }

    public function prepareHtml(
        string $html,
        string $source,
        StaticExportPurpose $purpose = StaticExportPurpose::Portable,
    ): StaticRenderingResult {
        $records = [];
        $prepared = $html;

        foreach ($this->dynamicBindingIds($html) as $bindingId) {
            $classification = $this->classification($bindingId);
            if ($classification->canFreeze()) {
                $records[] = [
                    'source' => $source,
                    'binding' => $bindingId,
                    'classification' => $classification->value,
                    'status' => 'passed',
                    'message' => 'Binding can be included in published output.',
                ];
                continue;
            }

            if ($purpose === StaticExportPurpose::Publication) {
                $records[] = [
                    'source' => $source,
                    'binding' => $bindingId,
                    'classification' => $classification->value,
                    'status' => 'runtime',
                    'message' => 'Binding will resolve for each public request.',
                ];
                continue;
            }

            $prepared = $this->removeDynamicRegion($prepared, $bindingId);
            $records[] = [
                'source' => $source,
                'binding' => $bindingId,
                'classification' => $classification->value,
                'status' => 'failed',
                'message' => sprintf('Binding "%s" cannot be included in published output.', $bindingId),
            ];
        }

        return new StaticRenderingResult($prepared, new StaticRenderingReport($records));
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function fingerprint(): string
    {
        $payload = [
            'version' => self::VERSION,
            'bindings' => $this->bindings instanceof BindingRegistry
                ? $this->bindings->staticSafetyMap()
                : [
                    'static_safe' => self::STATIC_SAFE,
                    'request_sensitive' => self::REQUEST_SENSITIVE,
                ],
        ];

        $this->sortRecursively($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return list<string>
     */
    private function dynamicBindingIds(string $html): array
    {
        $count = preg_match_all(
            '/\bdata-ai-dynamic\s*=\s*(?:"([^"]+)"|\'([^\']+)\'|([^\s>]+))/i',
            $html,
            $matches,
        );

        if ($count === false || $count === 0) {
            return [];
        }

        $ids = [];
        foreach ($matches[1] as $index => $doubleQuoted) {
            $id = (string) ($doubleQuoted ?: ($matches[2][$index] ?? '') ?: ($matches[3][$index] ?? ''));
            $id = trim($id);
            if ($id !== '') {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function classification(string $bindingId): BindingStaticSafety
    {
        if ($this->bindings instanceof BindingRegistry) {
            return $this->bindings->staticSafetyForSource($bindingId);
        }

        $classification = self::STATIC_SAFE[$bindingId]
            ?? self::REQUEST_SENSITIVE[$bindingId]
            ?? BindingStaticSafety::NotStatic->value;

        return BindingStaticSafety::tryFrom($classification) ?? BindingStaticSafety::NotStatic;
    }

    private function removeDynamicRegion(string $html, string $bindingId): string
    {
        $replacement = '<!-- uncanny-page-builder-static-unsafe:' . htmlspecialchars($bindingId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' -->';

        if (!class_exists(\DOMDocument::class)) {
            return $this->removeDynamicRegionWithRegex($html, $bindingId, $replacement);
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $html . '</body>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $this->removeDynamicRegionWithRegex($html, $bindingId, $replacement);
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//*[@data-ai-dynamic]');
        if (!$nodes instanceof \DOMNodeList) {
            return $html;
        }

        $toRemove = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement || trim($node->getAttribute('data-ai-dynamic')) !== $bindingId) {
                continue;
            }
            $toRemove[] = $node;
        }

        foreach ($toRemove as $node) {
            $comment = $document->createComment(' uncanny-page-builder-static-unsafe:' . $bindingId . ' ');
            $node->parentNode?->replaceChild($comment, $node);
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return $html;
        }

        $prepared = '';
        foreach ($body->childNodes as $child) {
            $prepared .= $document->saveHTML($child) ?: '';
        }

        return $prepared;
    }

    private function removeDynamicRegionWithRegex(string $html, string $bindingId, string $replacement): string
    {
        $id = preg_quote($bindingId, '/');
        $attribute = 'data-ai-dynamic\s*=\s*(?:"' . $id . '"|\'' . $id . '\'|' . $id . '(?=\s|>|\/))';

        $withClosedTagsRemoved = preg_replace(
            '/<([a-z][a-z0-9:-]*)(?=[^>]*\b' . $attribute . ')[^>]*>.*<\/\1>/is',
            $replacement,
            $html,
        ) ?? $html;

        return preg_replace(
            '/<([a-z][a-z0-9:-]*)(?=[^>]*\b' . $attribute . ')[^>]*\/?>/i',
            $replacement,
            $withClosedTagsRemoved,
        ) ?? $withClosedTagsRemoved;
    }

    /**
     * @param mixed $value
     */
    private function sortRecursively(&$value): void
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as &$child) {
            $this->sortRecursively($child);
        }
        unset($child);

        if (!array_is_list($value)) {
            ksort($value);
        }
    }
}
