<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

/**
 * Shared regex operations for data-ai-bind attribute replacement.
 * All card renderers delegate here instead of inlining the same 3 patterns.
 */
final class CardBindingEngine
{
    /**
     * Match an element bound to $key up to its OWN closing tag.
     *
     * The close is `</\2>` (a backreference to the captured tag name), not any
     * `</…>`. This prevents the lazy inner group from stopping at a nested
     * child's closing tag (corrupting `<span …>a <em>b</em></span>`) or running
     * past a void element that has no closing tag at all (swallowing following
     * markup). Residual limitation: an element nested inside another of the SAME
     * tag still stops at the inner close — regex cannot balance arbitrary nesting.
     *
     * Capture groups: 1=opening tag, 2=tag name, 3=inner content, 4=closing tag.
     */
    private static function bindKeyPattern(string $key): string
    {
        return '/(<([a-zA-Z][a-zA-Z0-9]*)\b[^>]*data-ai-bind="'
            . preg_quote($key, '/') . '"[^>]*>)(.*?)(<\/\2\s*>)/is';
    }

    /** Replace inner text content of elements with data-ai-bind="$key". */
    public static function text(string $card, string $key, string $safeValue): string
    {
        return preg_replace_callback(
            self::bindKeyPattern($key),
            static fn (array $matches): string => $matches[1] . $safeValue . $matches[4],
            $card
        ) ?? $card;
    }

    /**
     * Replace inner text content for non-anchor elements bound to $key.
     *
     * Anchors are left untouched so their author-provided label survives —
     * href() owns the <a> case by writing the attribute. Without this guard a
     * URL-typed binding would overwrite a link's visible text with the raw URL.
     */
    public static function textExceptAnchors(string $card, string $key, string $safeValue): string
    {
        return preg_replace_callback(
            self::bindKeyPattern($key),
            static function (array $matches) use ($safeValue): string {
                if (strtolower($matches[2]) === 'a') {
                    return $matches[0];
                }
                return $matches[1] . $safeValue . $matches[4];
            },
            $card
        ) ?? $card;
    }

    /** Replace src attribute on <img> elements with data-ai-bind="$key". */
    public static function image(string $card, string $key, string $url): string
    {
        $escaped = preg_quote($key, '/');

        // data-ai-bind before src
        $card = preg_replace_callback(
            '/<img([^>]*?)data-ai-bind="' . $escaped . '"([^>]*?)src="[^"]*"([^>]*?)\/?>/i',
            static fn (array $matches): string =>
                '<img' . $matches[1] . 'data-ai-bind="' . $key . '"' . $matches[2] . 'src="' . $url . '"' . $matches[3] . '/>',
            $card
        ) ?? $card;

        // src before data-ai-bind
        $card = preg_replace_callback(
            '/<img([^>]*?)src="[^"]*"([^>]*?)data-ai-bind="' . $escaped . '"([^>]*?)\/?>/i',
            static fn (array $matches): string =>
                '<img' . $matches[1] . 'src="' . $url . '"' . $matches[2] . 'data-ai-bind="' . $key . '"' . $matches[3] . '/>',
            $card
        ) ?? $card;

        // Add src when missing
        $card = preg_replace_callback(
            '/<img([^>]*?)data-ai-bind="' . $escaped . '"(?![^>]*src=)([^>]*?)\/?>/i',
            static fn (array $matches): string =>
                '<img' . $matches[1] . 'data-ai-bind="' . $key . '" src="' . $url . '"' . $matches[2] . '/>',
            $card
        ) ?? $card;

        return $card;
    }

    /** Replace href attribute on <a> elements with data-ai-bind="$key". */
    public static function href(string $card, string $key, string $url): string
    {
        $escaped = preg_quote($key, '/');

        // Replace existing href
        $card = preg_replace_callback(
            '/(<a[^>]*data-ai-bind="' . $escaped . '"[^>]*)href="[^"]*"([^>]*>)/i',
            static fn (array $matches): string => $matches[1] . 'href="' . $url . '"' . $matches[2],
            $card
        ) ?? $card;

        // Add href if missing
        $card = preg_replace_callback(
            '/(<a\s)([^>]*data-ai-bind="' . $escaped . '")(?![^>]*href=)([^>]*>)/i',
            static fn (array $matches): string => $matches[1] . 'href="' . $url . '" ' . $matches[2] . $matches[3],
            $card
        ) ?? $card;

        return $card;
    }
}
