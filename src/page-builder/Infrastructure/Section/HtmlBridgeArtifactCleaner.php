<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

/**
 * Surgical string-based removal of known Magic Bridge artifacts from section HTML.
 *
 * This cleaner does NOT parse HTML.  It uses targeted string/regex operations to
 * strip only the artifacts that the Magic Bridge injected (badges, editor-only
 * attributes, editor-only CSS classes).  Every other attribute — including Alpine
 * shorthand (@click, @submit.prevent), colon bindings (:class), long-form
 * directives (x-data, x-show, x-on:click, x-transition), and any arbitrary
 * authored markup — is left exactly as stored.
 *
 * Shared by CanvasRenderer (live render) and HtmlCssProcessor (patch normalization).
 */
final class HtmlBridgeArtifactCleaner
{
    /**
     * Remove all Magic Bridge artifacts from HTML.
     *
     * @param string $html   Raw HTML with potential bridge artifacts.
     * @param string $rootId Unused — kept for backward compatibility.
     * @return string Cleaned HTML.  Never null; always returns a usable string.
     */
    public static function clean(string $html, string $rootId = ''): string
    {
        // 1. Remove <upb-section-badge> custom elements (production badge).
        $html = (string) preg_replace(
            '/<upb-section-badge\b[^>]*>[\s\S]*?<\/upb-section-badge>/i',
            '',
            $html,
        );

        // 2. Remove any element whose class list contains "upb-section-badge"
        //    (test/fallback badge markup, e.g. <span class="upb-section-badge">…</span>).
        $html = (string) preg_replace(
            '/<(\w+)\b[^>]*\bclass="[^"]*\bupb-section-badge\b[^"]*"[^>]*>[\s\S]*?<\/\1>/i',
            '',
            $html,
        );

        // 3. Remove data-section-id attributes injected at render time.
        $html = (string) preg_replace('/\s+data-section-id="[^"]*"/', '', $html);

        // 4. Remove contenteditable attributes from inline editing.
        $html = (string) preg_replace('/\s+contenteditable(?:="[^"]*")?/', '', $html);

        // 5. Strip editor-only classes (upb-active, upb-hover) from class attributes.
        //    Preserves all other classes; removes the class attribute entirely only
        //    if no classes remain after stripping.
        $html = (string) preg_replace_callback(
            '/(\s?)\bclass="([^"]*)"/',
            static function (array $m): string {
                $cleaned = preg_replace('/\b(?:upb-active|upb-hover)\b/', '', $m[2]);
                $cleaned = preg_replace('/\s{2,}/', ' ', trim((string) $cleaned));
                return $cleaned === '' ? '' : $m[1] . 'class="' . $cleaned . '"';
            },
            $html,
        );

        return trim($html);
    }
}
