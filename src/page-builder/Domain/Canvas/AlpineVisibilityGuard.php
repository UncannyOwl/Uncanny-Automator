<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Canvas;

/**
 * Adds the minimal no-flash contract for Alpine-controlled visibility.
 */
final class AlpineVisibilityGuard
{
    public static function cloakCss(string $scopeSelector = '#uncanny-pb-canvas'): string
    {
        return $scopeSelector . '[x-cloak],'
            . $scopeSelector . ' [x-cloak]{display:none!important}';
    }

    public static function addCloakToXShow(string $html): string
    {
        if (!str_contains($html, 'x-show')) {
            return $html;
        }

        return preg_replace_callback(
            '/<([a-z][a-z0-9:-]*)(\s[^<>]*\bx-show\b[^<>]*)(\/?)>/i',
            static function (array $matches): string {
                $tag = $matches[0];
                if (preg_match('/\sx-cloak(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)|(?=\s|\/?>))/', $tag)) {
                    return $tag;
                }

                return '<' . $matches[1] . $matches[2] . ' x-cloak' . $matches[3] . '>';
            },
            $html
        ) ?? $html;
    }
}
