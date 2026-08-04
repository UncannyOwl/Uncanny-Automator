<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStyles;

/**
 * Minimal, property-family-based safety checks for design style values.
 *
 * This is deliberately NOT a CSS parser (see the plan's stop conditions). It
 * rejects values that would break out of a declaration or inject script, and
 * validates that a token name is a `--custom-property`.
 */
final class DesignStyleValue
{
    /** Custom property: --name, alphanumerics/dash/underscore. */
    public const TOKEN_NAME_PATTERN = '/^--[A-Za-z0-9_-]+$/';

    public static function isValidTokenName(string $name): bool
    {
        return preg_match(self::TOKEN_NAME_PATTERN, $name) === 1;
    }

    /**
     * A safe single-declaration value: non-empty, no declaration/rule breakout,
     * no comment or javascript: url, no markup. var()/colors/gradients pass.
     */
    public static function isSafeValue(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        // No characters that could terminate the declaration or open a new rule.
        if (preg_match('/[;{}]/', $trimmed) === 1) {
            return false;
        }

        // No CSS comments.
        if (str_contains($trimmed, '/*') || str_contains($trimmed, '*/')) {
            return false;
        }

        // No markup breakout.
        if (str_contains($trimmed, '<')) {
            return false;
        }

        // No expression() payloads.
        if (preg_match('/expression\s*\(/i', $trimmed) === 1) {
            return false;
        }

        // url() references may only point at http(s) or relative/fragment targets.
        // This rejects javascript:, data:, file:, etc. without trying to be a full
        // media policy — the host's media/security policy still governs uploads.
        if (!self::hasOnlySafeUrlSchemes($trimmed)) {
            return false;
        }

        return true;
    }

    /**
     * True when every url(...) reference uses a safe scheme. References without a
     * scheme (relative paths, protocol-relative //host, #fragment) are allowed;
     * any explicit scheme other than http/https is rejected.
     */
    private static function hasOnlySafeUrlSchemes(string $value): bool
    {
        if (preg_match_all('/url\(\s*([\'"]?)(.*?)\1\s*\)/is', $value, $matches) === false) {
            return false;
        }

        foreach ($matches[2] as $reference) {
            $reference = trim($reference);

            if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $reference, $scheme) !== 1) {
                continue; // No scheme — relative/fragment/protocol-relative is fine.
            }

            $name = strtolower($scheme[1]);
            if ($name !== 'http' && $name !== 'https') {
                return false;
            }
        }

        return true;
    }
}
