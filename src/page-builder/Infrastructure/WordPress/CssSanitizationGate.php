<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Capability-based CSS sanitization gate for persisted section source.
 *
 * Public rendering still escapes CSS for a <style> text node, but persistence
 * must not rely on every render path remembering to apply the same defense.
 */
final class CssSanitizationGate
{
    public static function filter(string $css): string
    {
        if (self::canUseUnfilteredHtml()) {
            return $css;
        }

        return self::filterDangerousSyntax($css);
    }

    /**
     * Remove CSS syntax that can break the persisted style boundary. Source
     * editors still allow broad CSS, but they must not store style breakouts,
     * remote imports, legacy expressions, or unsafe URL protocols.
     */
    public static function filterDangerousSyntax(string $css): string
    {
        $css = self::stripMarkup($css);
        $css = self::stripImportRules($css);
        $css = self::stripExpressionDeclarations($css);
        $css = self::neutralizeUnsafeUrls($css);

        return trim($css);
    }

    private static function canUseUnfilteredHtml(): bool
    {
        if (!function_exists(__NAMESPACE__ . '\\current_user_can') && !function_exists('current_user_can')) {
            return false;
        }

        return current_user_can('unfiltered_html');
    }

    private static function stripMarkup(string $css): string
    {
        $out = '';
        $quote = null;
        $length = strlen($css);

        for ($i = 0; $i < $length; ++$i) {
            $char = $css[$i];

            if ($quote !== null) {
                $out .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    ++$i;
                    $out .= $css[$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $out .= $char;
                continue;
            }

            if ($char === '<') {
                $end = strpos($css, '>', $i + 1);
                if ($end !== false) {
                    $i = $end;
                    continue;
                }
            }

            $out .= $char;
        }

        return $out;
    }

    private static function stripImportRules(string $css): string
    {
        return preg_replace('/@import\s+(?:url\([^)]*\)|[^;{}])+;?/i', '', $css) ?? $css;
    }

    private static function stripExpressionDeclarations(string $css): string
    {
        $css = preg_replace('/[^{};]*:\s*[^{};]*expression\s*\([^{};]*;?/i', '', $css) ?? $css;

        return preg_replace('/expression\s*\(/i', 'blocked-expression(', $css) ?? $css;
    }

    private static function neutralizeUnsafeUrls(string $css): string
    {
        return preg_replace_callback(
            '/url\(\s*([\'"]?)(.*?)\1\s*\)/is',
            static function (array $matches): string {
                $reference = trim((string) ($matches[2] ?? ''));

                if (preg_match('/^(?:javascript|data|file|vbscript)\s*:/i', $reference) === 1) {
                    return 'url("")';
                }

                return (string) ($matches[0] ?? '');
            },
            $css
        ) ?? $css;
    }
}
