<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

final class WordPressPostId
{
    public static function fromCurrentQuery(mixed $queriedObjectId): ?int
    {
        return self::fromMixed($queriedObjectId);
    }

    public static function fromMixed(mixed $value): ?int
    {
        if (is_string($value)) {
            if ($value === '' || !ctype_digit($value)) {
                return null;
            }

            $value = ltrim($value, '0');
            if ($value === '') {
                return null;
            }

            $value = filter_var($value, FILTER_VALIDATE_INT);
        }

        return is_int($value) && $value > 0 ? $value : null;
    }
}
