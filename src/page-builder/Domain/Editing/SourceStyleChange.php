<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Editing;

use UncannyPageBuilder\Domain\DesignStyles\DesignStyleProperty;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;
use UncannyPageBuilder\Domain\DesignStyles\ElementStyleRule;

/** Validated declarations use the same policy as existing element-style writes. */
final class SourceStyleChange
{
    public static function validate(array $change): array
    {
        // Request values must be strings. Coercion can hide a malformed payload
        // or turn a confirmed rejection into an uncertain server failure.
        $property = $change['property'] ?? null;
        if (!is_string($property)) {
            throw new \InvalidArgumentException('The element style property is invalid.');
        }

        $property = strtolower(trim($property));
        $value = $change['value'] ?? null;

        if (!DesignStyleProperty::isAllowed($property)) {
            throw new \InvalidArgumentException('The element style property is not allowed.');
        }

        if (!is_string($value) || !DesignStyleValue::isSafeValue($value)) {
            throw new \InvalidArgumentException('The element style value is unsafe.');
        }

        $kind = $change['target']['kind'] ?? 'block';
        $viewport = $change['viewport'] ?? 'desktop';
        $state = $change['state'] ?? 'normal';

        self::assertChoice($kind, ElementStyleRule::VALID_KINDS, 'kind');
        self::assertChoice($viewport, ElementStyleRule::VALID_VIEWPORTS, 'viewport');
        self::assertChoice($state, ElementStyleRule::VALID_STATES, 'state');

        return compact('property', 'value', 'kind', 'viewport', 'state');
    }

    private static function assertChoice(mixed $value, array $choices, string $name): void
    {
        if (!in_array($value, $choices, true)) {
            throw new \InvalidArgumentException('The element style ' . $name . ' is invalid.');
        }
    }
}
