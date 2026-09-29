<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Slashes a value for a WordPress write API that unslashes its input.
 */
final class WordPressSlashing
{
    /**
     * A failing replacement of wp_slash() must not stop the write or change
     * its value, so every failure falls back to WordPress' own slashing rule.
     */
    public static function slash(string $value): string
    {
        if (!function_exists(__NAMESPACE__ . '\\wp_slash') && !function_exists('wp_slash')) {
            return addslashes($value);
        }

        try {
            $slashed = wp_slash($value);
        } catch (\Throwable) {
            return addslashes($value);
        }

        return is_string($slashed) ? $slashed : addslashes($value);
    }
}
