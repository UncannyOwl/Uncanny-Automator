<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

/**
 * Extracts CSS custom property references from source strings.
 *
 * Scans for `var(--...)` patterns in CSS and HTML. Returns unique
 * token key names sorted alphabetically.
 */
final class CssTokenScanner
{
    /**
     * Extract unique CSS custom property names referenced via var().
     *
     * @param string ...$sources  One or more source strings (CSS, HTML with inline styles).
     * @return string[] Sorted unique token keys (e.g. ['--bs-border-radius', '--bs-primary']).
     */
    public static function scan(string ...$sources): array
    {
        $tokens = [];
        foreach ($sources as $source) {
            if (preg_match_all('/var\(\s*(--[a-zA-Z0-9_-]+)/', $source, $matches)) {
                foreach ($matches[1] as $token) {
                    $tokens[$token] = true;
                }
            }
        }

        $result = array_keys($tokens);
        sort($result);
        return $result;
    }
}
