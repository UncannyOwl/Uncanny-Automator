<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

/**
 * Escapes CSS for inline <style> text nodes without destroying valid CSS.
 *
 * CSS can legitimately contain SVG data URIs or string content with angle
 * brackets. The only HTML parser breakout we need to neutralize here is a
 * closing style tag.
 */
final class StyleElementCss
{
    public static function escape(string $css): string
    {
        return preg_replace('#</style#i', '<\\/style', $css) ?? $css;
    }
}
