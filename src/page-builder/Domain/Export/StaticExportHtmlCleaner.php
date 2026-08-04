<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

/**
 * Removes transient Page Builder editor markers from portable HTML.
 *
 * Durable selector handles stay in the artifact because generated CSS can
 * legitimately target them: element overrides use data-upb-lens-id, editable
 * source CSS can use data-ai-* markers, and section CSS can use data-section-id.
 */
final class StaticExportHtmlCleaner
{
    public static function clean(string $html): string
    {
        return preg_replace(
            '/\s+data-upb-(?!lens-id\b)[a-zA-Z0-9_-]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?/i',
            '',
            $html,
        ) ?? $html;
    }
}
