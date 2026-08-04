<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Page Builder stored-HTML cleanup gate.
 *
 * Admin/unfiltered authors need byte-friendly persistence for generated markup,
 * inline design spans, runtime attributes, and shortcode output. If Page Builder
 * access is ever widened to a role without unfiltered_html, this gate strips the
 * executable HTML surface before source reaches durable storage.
 */
final class HtmlSanitizationGate
{
    private const EDITOR_RESERVED_ATTRIBUTES_PATTERN =
        '/\s+data-upb-editor-(?:chrome|empty-state|empty-state-actions)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?/i';
    private const SCRIPT_ELEMENT_BLOCK_PATTERN =
        '/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is';
    private const SCRIPT_ELEMENT_TAG_PATTERN =
        '/<\s*\/?\s*script\b[^>]*>/i';
    private const EXECUTABLE_ELEMENT_BLOCK_PATTERN =
        '/<\s*(iframe|object|embed)\b[^>]*>.*?<\s*\/\s*\1\s*>/is';
    private const EXECUTABLE_ELEMENT_TAG_PATTERN =
        '/<\s*\/?\s*(?:iframe|object|embed)\b[^>]*>/i';
    private const EXECUTABLE_ATTRIBUTES_PATTERN =
        '/\s+(?:on[a-z][\w:-]*|x-[\w:-]+|@[^\s=\/>]+|:[^\s=\/>]+)\s*(?:=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?/i';
    private const URL_ATTRIBUTES_PATTERN =
        '/\s+(href|src|action|formaction|xlink:href)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i';

    public static function filter(string $html): string
    {
        $html = self::stripEditorReservedAttributes($html);
        $html = self::sanitizeScriptElements($html);
        $html = self::sanitizeJavascriptUrlAttributes($html);

        if (self::canUseUnfilteredHtml()) {
            return $html;
        }

        return self::stripExecutableHtml($html);
    }

    private static function stripEditorReservedAttributes(string $html): string
    {
        // Tag-scoped so prose mentioning the attribute name is untouched.
        return self::rewriteTags(
            $html,
            static function (string $tag): string {
                $stripped = preg_replace(self::EDITOR_RESERVED_ATTRIBUTES_PATTERN, '', $tag);

                return is_string($stripped) ? $stripped : $tag;
            },
        );
    }

    private static function canUseUnfilteredHtml(): bool
    {
        if (!function_exists(__NAMESPACE__ . '\\current_user_can') && !function_exists('current_user_can')) {
            return false;
        }

        return current_user_can('unfiltered_html');
    }

    private static function stripExecutableHtml(string $html): string
    {
        $html = self::stripExecutableElements($html);
        $html = self::stripExecutableAttributes($html);

        return self::stripUnsafeUrlAttributes($html);
    }

    private static function sanitizeScriptElements(string $html): string
    {
        $html = preg_replace_callback(
            self::SCRIPT_ELEMENT_BLOCK_PATTERN,
            static fn (array $match): string => self::escapeHtmlFragment((string) ($match[0] ?? '')),
            $html,
        ) ?? $html;

        return preg_replace_callback(
            self::SCRIPT_ELEMENT_TAG_PATTERN,
            static fn (array $match): string => self::escapeHtmlFragment((string) ($match[0] ?? '')),
            $html,
        ) ?? $html;
    }

    private static function stripExecutableElements(string $html): string
    {
        $html = preg_replace(self::EXECUTABLE_ELEMENT_BLOCK_PATTERN, '', $html) ?? $html;

        return preg_replace(self::EXECUTABLE_ELEMENT_TAG_PATTERN, '', $html) ?? $html;
    }

    private static function stripExecutableAttributes(string $html): string
    {
        return self::rewriteTags(
            $html,
            static function (string $tag): string {
                $filtered = preg_replace(self::EXECUTABLE_ATTRIBUTES_PATTERN, '', $tag);

                return is_string($filtered) ? $filtered : $tag;
            },
        );
    }

    private static function stripUnsafeUrlAttributes(string $html): string
    {
        return self::sanitizeUrlAttributesByScheme($html, ['javascript', 'vbscript', 'data']);
    }

    private static function sanitizeJavascriptUrlAttributes(string $html): string
    {
        return self::sanitizeUrlAttributesByScheme($html, ['javascript']);
    }

    /**
     * @param list<string> $blockedSchemes
     */
    private static function sanitizeUrlAttributesByScheme(string $html, array $blockedSchemes): string
    {
        $schemePattern = implode('|', array_map(
            static fn (string $scheme): string => preg_quote($scheme, '/'),
            $blockedSchemes,
        ));

        return self::rewriteTags(
            $html,
            static function (string $tag) use ($schemePattern): string {
                $filtered = preg_replace_callback(
                    self::URL_ATTRIBUTES_PATTERN,
                    static function (array $attrMatch) use ($schemePattern): string {
                        $rawValue = trim((string) ($attrMatch[2] ?? ''), "\"' \t\n\r\0\x0B");
                        $decoded = html_entity_decode($rawValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $normalized = preg_replace('/[\x00-\x20\x7f]+/', '', $decoded) ?? $decoded;

                        return preg_match('/^(?:' . $schemePattern . '):/i', $normalized) === 1
                            ? self::sanitizeBlockedUrlAttribute((string) ($attrMatch[1] ?? ''), $normalized)
                            : (string) ($attrMatch[0] ?? '');
                    },
                    $tag,
                );

                return is_string($filtered) ? $filtered : $tag;
            },
        );
    }

    /**
     * Rewrite complete HTML tags without treating a quoted `>` as the end of
     * the tag. This preserves authored markup byte-for-byte outside the small
     * attributes each caller deliberately changes.
     *
     * @param callable(string): string $rewrite
     */
    private static function rewriteTags(string $html, callable $rewrite): string
    {
        $length = strlen($html);
        $offset = 0;
        $result = '';

        while ($offset < $length) {
            $start = strpos($html, '<', $offset);
            if ($start === false) {
                $result .= substr($html, $offset);
                break;
            }

            $result .= substr($html, $offset, $start - $offset);
            $quote = null;
            $end = null;

            for ($index = $start + 1; $index < $length; $index++) {
                $character = $html[$index];
                if ($quote !== null) {
                    if ($character === $quote) {
                        $quote = null;
                    }
                    continue;
                }

                if ($character === '"' || $character === "'") {
                    $quote = $character;
                    continue;
                }

                if ($character === '>') {
                    $end = $index;
                    break;
                }
            }

            if ($end === null) {
                $result .= substr($html, $start);
                break;
            }

            $result .= $rewrite(substr($html, $start, $end - $start + 1));
            $offset = $end + 1;
        }

        return $result;
    }

    private static function escapeHtmlFragment(string $html): string
    {
        return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function sanitizeBlockedUrlAttribute(string $attributeName, string $normalizedValue): string
    {
        $safeValue = 'about:blank';
        if ($normalizedValue !== '') {
            $safeValue .= '#' . rawurlencode($normalizedValue);
        }

        return sprintf(
            ' %s="%s"',
            $attributeName,
            self::escapeHtmlFragment($safeValue),
        );
    }
}
