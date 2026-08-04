<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Domain\Section\DynamicContentConfig;

/**
 * Shared typed meta value binding for card renderers.
 *
 * Applies a meta value to a data-ai-bind placeholder based on the
 * configured value type (text, url, image, number). Invalid or empty
 * values degrade safely to empty content — never crash rendering.
 */
final class MetaBindingHelper
{
    /**
     * Clear a bound placeholder when runtime decides the value must not render.
     *
     * This keeps card markup truthful: blocked or missing governed values should
     * not leave author placeholder text behind as if it were real data.
     */
    public static function clearBinding(string $card, string $bindAttr): string
    {
        $card = CardBindingEngine::image($card, $bindAttr, '');
        $card = CardBindingEngine::href($card, $bindAttr, '');

        return CardBindingEngine::text($card, $bindAttr, '');
    }

    /**
     * Apply a single meta binding to a card template.
     *
     * @param string $card         Current card HTML
     * @param string $bindAttr     The data-ai-bind value (e.g., "meta.price")
     * @param mixed  $rawValue     Raw meta value from get_post_meta / get_user_meta
     * @param string $metaKey      The bare meta key (e.g., "price") for type lookup
     */
    public static function applyMetaBinding(string $card, string $bindAttr, mixed $rawValue, string $metaKey): string
    {
        if (!is_scalar($rawValue)) {
            return $card;
        }

        $value = (string) $rawValue;
        if ($value === '') {
            return $card;
        }

        $type = DynamicContentConfig::metaValueType($metaKey);

        return match ($type) {
            DynamicContentConfig::META_TYPE_URL   => self::bindAsUrl($card, $bindAttr, $value),
            DynamicContentConfig::META_TYPE_IMAGE => self::bindAsImage($card, $bindAttr, $value),
            DynamicContentConfig::META_TYPE_NUMBER => self::bindAsText($card, $bindAttr, self::formatNumber($value)),
            default                                => self::bindAsText($card, $bindAttr, esc_html($value)),
        };
    }

    /** Replace inner text content. */
    private static function bindAsText(string $card, string $bindAttr, string $safeValue): string
    {
        return CardBindingEngine::text($card, $bindAttr, $safeValue);
    }

    /** Replace href attribute on <a> elements, or text content on other elements. */
    private static function bindAsUrl(string $card, string $bindAttr, string $rawUrl): string
    {
        // <a> elements: write the href. esc_url is the correct attribute escape.
        $card = CardBindingEngine::href($card, $bindAttr, esc_url($rawUrl));

        // Non-<a> elements: show the URL as visible text. Anchors keep their
        // author-provided label (href() already handled them). esc_url_raw keeps
        // "&" literal and strips unsafe schemes; esc_html then escapes exactly
        // once — esc_html(esc_url(...)) would double-encode "&" to "&amp;#038;".
        $card = CardBindingEngine::textExceptAnchors($card, $bindAttr, esc_html(esc_url_raw($rawUrl)));

        return $card;
    }

    /** Replace src attribute on <img> elements. */
    private static function bindAsImage(string $card, string $bindAttr, string $rawUrl): string
    {
        // Check if it resolves to a real URL (attachment ID or direct URL).
        $imageUrl = is_numeric($rawUrl) ? wp_get_attachment_url((int) $rawUrl) : $rawUrl;
        if (!$imageUrl || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return $card;
        }

        $safeUrl = esc_url($imageUrl);

        return CardBindingEngine::image($card, $bindAttr, $safeUrl);
    }

    private static function formatNumber(string $value): string
    {
        if (str_contains($value, '.')) {
            return esc_html(number_format((float) $value, 2));
        }
        return esc_html((string) (int) $value);
    }
}
