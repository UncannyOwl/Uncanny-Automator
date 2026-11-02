<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain;

/**
 * Whether an array is a list, as PHP's array_is_list() decides it: its keys
 * are 0, 1, 2 … in order.
 *
 * Page Builder needs PHP 8.1, where array_is_list() is built in. WordPress
 * itself only polyfills it from 6.5, and the host plugin supports WordPress
 * 6.2, so every call goes through this one guarded check.
 */
final class ArrayShape
{
    /**
     * @param array<mixed> $value
     */
    public static function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        $expected = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                return false;
            }
            ++$expected;
        }

        return true;
    }
}
