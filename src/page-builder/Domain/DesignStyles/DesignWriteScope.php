<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStyles;

/**
 * Where a design style change is written.
 *
 * The Page Builder UI decides this from the active workspace tab; the backend
 * routes a commit by this scope. Domain concept only — no REST, DOM, or React.
 */
enum DesignWriteScope: string
{
    case Element = 'element';
    case Page = 'page';
    case Global = 'global';

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }
}
