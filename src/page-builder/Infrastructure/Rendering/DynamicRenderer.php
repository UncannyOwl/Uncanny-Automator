<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Export\StaticExportPageIdentity;

/**
 * Parse data-ai-dynamic wrappers and replace with real content.
 *
 * Renderer map is built from the BindingRegistry (loaded from
 * bindings directory declarations). External plugins can still add
 * renderers via the 'uncanny_page_builder_dynamic_renderers' filter.
 */
final class DynamicRenderer
{
    /**
     * Capabilities that authors may use for conditional rendering.
     *
     * Content authors control the raw data-capability attribute, so unknown
     * values must fail closed instead of being passed directly to WordPress.
     *
     * @var string[]
     */
    private const DEFAULT_ALLOWED_CAPABILITIES = [
        'read',
        'edit_posts',
        'publish_posts',
        'edit_pages',
        'publish_pages',
        'upload_files',
        'moderate_comments',
        'manage_options',
    ];

    /**
     * Conditionals whose result depends on the current visitor's auth state.
     * When any of these is present the rendered page differs per request, so it
     * must not be reused from a full-page cache. (Page-type/taxonomy conditionals
     * like if_single are URL-derived and stay cacheable per URL.)
     *
     * @var string[]
     */
    private const AUTH_SENSITIVE_CONDITIONALS = [
        'if_logged_in',
        'if_logged_out',
        'if_role',
        'if_can',
    ];

    private BindingRegistry $registry;

    public function __construct(BindingRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Parse data-ai-dynamic wrappers and replace with real content loops.
     * Uses DOMDocument (not regex) because card templates contain nested tags.
     */
    public function render(string $html, ?StaticExportPageIdentity $pageIdentity = null): string
    {
        $wrapped = '<div id="__upb_root">' . $html . '</div>';

        $doc = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $xpath = new \DOMXPath($doc);

        // Phase 1: Resolve conditional wrappers (show/hide based on auth state).
        // Must run before content bindings to prevent nesting destruction.
        $this->resolveConditionals($xpath, $pageIdentity);

        // Phase 2: Process content bindings on the (possibly pruned) DOM.
        $nodes = $xpath->query('//*[@data-ai-dynamic]');

        if ($nodes->length === 0) {
            $root = $doc->getElementById('__upb_root');
            if (!$root) {
                return $html;
            }
            $output = '';
            foreach ($root->childNodes as $child) {
                $output .= $doc->saveHTML($child);
            }
            return $output;
        }

        $defaultRenderers = $this->buildRendererCallables($pageIdentity);
        $filteredRenderers = apply_filters(
            'uncanny_page_builder_dynamic_renderers',
            $defaultRenderers
        );
        $renderers = is_array($filteredRenderers) ? $filteredRenderers : $defaultRenderers;

        // Collect into array — DOM mutations during iteration invalidate DOMNodeList.
        $nodeList = [];
        foreach ($nodes as $n) {
            $nodeList[] = $n;
        }

        foreach ($nodeList as $node) {
            // Node may have been detached by a previous iteration's DOM mutations
            // or by resolveConditionals removing a parent.
            if ($node->parentNode === null) {
                continue;
            }
            $source = trim($node->getAttribute('data-ai-dynamic'));
            if (!isset($renderers[$source]) || !is_callable($renderers[$source])) {
                continue;
            }

            $args = $this->extractQueryArgs($node, $source);
            $declaration = $this->registry->get($source);
            $isSelfRendering = $declaration && $declaration->isSelfRendering();
            $outputShape = $declaration?->outputShape ?? 'html';

            try {
                if ($isSelfRendering) {
                    $args['_binding_id'] = $source;

                    if ($source === 'wp_menu') {
                        $templateUl = $this->extractFirstChildElement($node);
                        if ($templateUl) {
                            $args['menu_class'] = $templateUl->getAttribute('class') ?: '';
                            $args['items_wrap_id'] = $templateUl->getAttribute('id') ?: '';
                        }
                    }

                    $renderedCards = call_user_func($renderers[$source], '', $args);
                    if (!is_string($renderedCards)) {
                        throw new \UnexpectedValueException('Renderer output must be a string.');
                    }

                    $trimmed = trim($renderedCards);
                    $tagName = strtolower($node->nodeName);
                    if ($outputShape === 'url' && $trimmed === '') {
                        if ($tagName === 'a') {
                            $node->setAttribute('href', '');
                            continue;
                        }
                        if ($tagName === 'img') {
                            $node->setAttribute('src', '');
                            continue;
                        }
                    }

                    if ($outputShape === 'url' && $trimmed !== '' && !str_contains($trimmed, '<')) {
                        $attributeUrl = $this->normalizeAttributeUrl($trimmed);
                        if ($tagName === 'a' && $this->looksLikeAttributeUrl($attributeUrl)) {
                            $node->setAttribute('href', $attributeUrl);
                            continue;
                        }
                        if ($tagName === 'img') {
                            $node->setAttribute('src', $attributeUrl);
                            continue;
                        }
                    }
                } else {
                    $cardTemplate = $this->extractCardTemplate($node, $doc);
                    if (empty($cardTemplate)) {
                        continue;
                    }
                    // Card renderers shared across several bindings (e.g. the
                    // wp_query family) need the binding id to apply per-binding
                    // query semantics; without it they cannot tell wp_sticky_posts
                    // from wp_recent_posts and would silently render the same loop.
                    $args['_binding_id'] = $source;
                    $renderedCards = call_user_func($renderers[$source], $cardTemplate, $args);
                    if (!is_string($renderedCards)) {
                        throw new \UnexpectedValueException('Renderer output must be a string.');
                    }
                }
            } catch (\Throwable $e) {
                error_log(sprintf('[DynamicRenderer] Renderer "%s" threw %s: %s', $source, get_class($e), $e->getMessage()));
                continue;
            }

            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }

            $tempDoc = new \DOMDocument();
            $prevErrors = libxml_use_internal_errors(true);
            $tempDoc->loadHTML(
                '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="__cards">' . $renderedCards . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
            libxml_clear_errors();
            libxml_use_internal_errors($prevErrors);

            $cardsRoot = $tempDoc->getElementById('__cards');
            if ($cardsRoot) {
                foreach ($cardsRoot->childNodes as $child) {
                    $imported = $doc->importNode($child, true);
                    $node->appendChild($imported);
                }
            }
        }

        $root = $doc->getElementById('__upb_root');
        if (!$root) {
            return $html;
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return $output;
    }

    /**
     * Extract query arguments from data attributes using the declaration config.
     *
     * @return array<string, mixed>
     */
    private function extractQueryArgs(\DOMElement $node, string $source): array
    {
        $declaration = $this->registry->get($source);
        if (!$declaration) {
            return [];
        }

        $args = [];
        foreach ($declaration->queryAttributes as $attr => $config) {
            $raw = $node->getAttribute($attr);
            // Convert data-post-type → post_type
            $key = str_replace('-', '_', preg_replace('/^data-/', '', $attr) ?? $attr);
            $value = $raw !== '' ? $raw : ($config['default'] ?? '');

            $args[$key] = match ($config['cast'] ?? 'string') {
                'int'  => (int) $value,
                'bool' => in_array(strtolower(trim((string) $value)), ['true', '1', 'yes'], true),
                default => (string) $value,
            };
        }

        return $args;
    }

    /**
     * Resolve conditional wrappers before content bindings.
     * Conditionals are structural (show/hide), not content (replace).
     * If condition is true: strip data-ai-dynamic, leave children for phase 2.
     * If condition is false: remove the entire node from DOM.
     */
    private function resolveConditionals(
        \DOMXPath $xpath,
        ?StaticExportPageIdentity $pageIdentity = null,
    ): void {
        $nodes = $xpath->query('//*[starts-with(@data-ai-dynamic, "if_")]');

        if ($nodes->length === 0) {
            return;
        }

        $filteredEvaluators = apply_filters('uncanny_page_builder_conditional_evaluators', []);
        $customEvaluators = is_array($filteredEvaluators) ? $filteredEvaluators : [];

        // Collect — removal during iteration invalidates DOMNodeList.
        $nodeList = [];
        foreach ($nodes as $n) {
            $nodeList[] = $n;
        }

        foreach ($nodeList as $node) {
            // Parent conditional may have already removed this node.
            if ($node->parentNode === null) {
                continue;
            }
            $type = $node->getAttribute('data-ai-dynamic');

            // Presence of an auth conditional makes the page request-sensitive,
            // regardless of whether this visitor keeps or drops the node. Custom
            // evaluators are opaque, so treat them as request-sensitive too
            // (fail-safe): a membership/role evaluator must never have its
            // personalized variant reused from a full-page cache.
            $hasCustomEvaluator = isset($customEvaluators[$type]) && is_callable($customEvaluators[$type]);
            if (in_array($type, self::AUTH_SENSITIVE_CONDITIONALS, true) || $hasCustomEvaluator) {
                $this->markPageAsNonCacheable();
            }

            $keep = match ($type) {
                // Auth
                'if_logged_in'      => is_user_logged_in(),
                'if_logged_out'     => !is_user_logged_in(),
                'if_role'           => $this->hasRole($node),
                'if_can'            => $this->hasCapability($node),

                // Page type
                'if_front_page'     => is_front_page(),
                'if_home'           => is_home(),
                'if_single'         => is_single(),
                'if_page'           => is_page(),
                'if_archive'        => is_archive(),
                'if_search'         => is_search(),
                'if_404'            => is_404(),

                // Taxonomy
                'if_category'       => is_category(),
                'if_tag'            => is_tag(),
                'if_author'         => is_author(),

                // Content
                'if_has_thumbnail'  => has_post_thumbnail($pageIdentity?->pageId()),
                'if_has_excerpt'    => has_excerpt($pageIdentity?->pageId()),
                'if_comments_open'  => comments_open($pageIdentity?->pageId()),
                'if_has_menu'       => $this->hasMenu($node),

                // Custom evaluators registered via filter, then fail-closed for unknown.
                default => isset($customEvaluators[$type]) && is_callable($customEvaluators[$type])
                    ? $this->evaluateCustomConditional($customEvaluators[$type], $node, $type)
                    : $this->rejectUnknownConditional($type),
            };

            if ($keep) {
                $node->removeAttribute('data-ai-dynamic');
            } elseif ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private function evaluateCustomConditional(callable $evaluator, \DOMElement $node, string $type): bool
    {
        try {
            return $evaluator($node) === true;
        } catch (\Throwable $exception) {
            error_log(sprintf(
                '[DynamicRenderer] Conditional "%s" threw %s and failed closed',
                $type,
                get_class($exception),
            ));

            return false;
        }
    }

    /**
     * Unknown conditional IDs fail closed — content is hidden, not shown.
     */
    private function rejectUnknownConditional(string $type): bool
    {
        error_log(sprintf('[DynamicRenderer] Unknown conditional "%s" — failing closed (content hidden)', $type));
        return false;
    }

    private function hasRole(\DOMElement $node): bool
    {
        $role = trim($node->getAttribute('data-role'));
        if ($role === '') {
            return false;
        }
        $user = wp_get_current_user();
        return in_array($role, $user->roles, true);
    }

    private function hasCapability(\DOMElement $node): bool
    {
        $capability = trim($node->getAttribute('data-capability'));
        if ($capability === '') {
            return false;
        }
        if (!$this->isAllowedCapability($capability)) {
            return false;
        }
        return current_user_can($capability);
    }

    private function isAllowedCapability(string $capability): bool
    {
        $allowedCapabilities = self::DEFAULT_ALLOWED_CAPABILITIES;

        if (function_exists(__NAMESPACE__ . '\\apply_filters') || function_exists('apply_filters')) {
            $filtered = apply_filters(
                'uncanny_page_builder_dynamic_allowed_capabilities',
                $allowedCapabilities
            );

            if (is_array($filtered)) {
                $allowedCapabilities = array_values(array_filter(
                    $filtered,
                    static fn(mixed $value): bool => is_string($value) && $value !== ''
                ));
            }
        }

        return in_array($capability, $allowedCapabilities, true);
    }

    /**
     * Section: attribute URL normalization.
     *
     * Self-rendering bindings often return URL strings that have already passed
     * through WordPress display escaping. Decode those entities back to their
     * raw URL form before writing DOM attributes, then let DOMDocument perform
     * the final HTML serialization escape exactly once.
     */
    private function normalizeAttributeUrl(string $value): string
    {
        $decoded = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (function_exists(__NAMESPACE__ . '\\esc_url_raw') || function_exists('esc_url_raw')) {
            return (string) esc_url_raw($decoded);
        }

        if (function_exists(__NAMESPACE__ . '\\esc_url') || function_exists('esc_url')) {
            return html_entity_decode((string) esc_url($decoded), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $decoded;
    }

    /**
     * Decide whether a self-rendering plain-string output should be written as
     * an <a href>. Accepts absolute URLs as well as the root-relative, protocol-
     * relative, fragment, query-only, and mailto/tel shapes that
     * FILTER_VALIDATE_URL alone rejects — otherwise such a URL would silently
     * become link text and leave a broken, attribute-leaking anchor. Dangerous
     * schemes (javascript:, data:) are already neutralized upstream by
     * normalizeAttributeUrl()'s esc_url_raw() before reaching here.
     */
    private function looksLikeAttributeUrl(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return true;
        }
        return (bool) preg_match('#^(/|//|\#|\?|mailto:|tel:)#i', $value);
    }

    private function markPageAsNonCacheable(): void
    {
        if (!\defined('DONOTCACHEPAGE')) {
            \define('DONOTCACHEPAGE', true);
        }
    }

    private function hasMenu(\DOMElement $node): bool
    {
        $location = trim($node->getAttribute('data-menu-location'));
        if ($location === '') {
            return false;
        }
        return has_nav_menu($location);
    }

    private function extractCardTemplate(\DOMElement $node, \DOMDocument $doc): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return trim($doc->saveHTML($child));
            }
        }

        $innerHtml = '';
        foreach ($node->childNodes as $child) {
            $innerHtml .= $doc->saveHTML($child);
        }

        return trim($innerHtml);
    }

    private function extractFirstChildElement(\DOMElement $node): ?\DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Instantiate renderer callables from the registry's class map.
     *
     * Instantiation lives here (infrastructure) rather than in the domain
     * BindingRegistry, so renderers can be extended to accept DI in the future.
     *
     * @return array<string, callable>
     */
    private function buildRendererCallables(?StaticExportPageIdentity $pageIdentity): array
    {
        $map = [];
        foreach ($this->registry->rendererClassMap() as $id => $class) {
            if (!is_subclass_of($class, SectionRendererInterface::class)) {
                continue;
            }

            $instance = match ($class) {
                WpSingleValueRenderer::class,
                WpChildrenCardRenderer::class,
                WpCommentsCardRenderer::class,
                WpQueryCardRenderer::class => new $class($pageIdentity),
                default => new $class(),
            };
            $map[$id] = [$instance, 'render'];
        }
        return $map;
    }
}
