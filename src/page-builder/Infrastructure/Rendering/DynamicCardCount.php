<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

/**
 * Normalizes public dynamic-card query limits.
 *
 * Dynamic renderers can be reached by anonymous visitors, so count-like
 * attributes must be clamped before reaching WordPress query APIs.
 */
final class DynamicCardCount
{
    private const DEFAULT_MAX = 100;

    public static function resolve(mixed $value, int $default): int
    {
        $count = $value === null ? $default : (int) $value;

        return min(self::max(), max(1, $count));
    }

    private static function max(): int
    {
        $max = self::DEFAULT_MAX;
        if (\function_exists('apply_filters')) {
            $max = (int) \apply_filters('uncanny_page_builder_dynamic_card_max_count', $max);
        }

        return max(1, $max);
    }
}
