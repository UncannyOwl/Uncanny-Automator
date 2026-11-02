<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;

/**
 * Validates design-token buckets before they can be persisted or rendered.
 */
final class DesignTokenValidator
{
    /**
     * @param array<string, mixed> $tokens
     * @return array<string, string>
     */
    public static function normalizeBucket(array $tokens, string $label): array
    {
        $normalized = [];

        foreach ($tokens as $key => $value) {
            $name = (string) $key;
            if (!is_string($key) || !DesignStyleValue::isValidTokenName($name)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception text; Domain has no WordPress dependency, and Page Builder returns these messages as JSON text, never as HTML.
                throw new \InvalidArgumentException("{$label} '{$name}' must be a valid CSS custom property.");
            }

            if (!is_string($value) && !is_numeric($value)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception text; Domain has no WordPress dependency, and Page Builder returns these messages as JSON text, never as HTML.
                throw new \InvalidArgumentException("{$label} '{$name}' must be a string value.");
            }

            $text = (string) $value;
            if (trim($text) !== '' && !DesignStyleValue::isSafeValue($text)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception text; Domain has no WordPress dependency, and Page Builder returns these messages as JSON text, never as HTML.
                throw new \InvalidArgumentException("{$label} '{$name}' contains an unsafe CSS value.");
            }

            $normalized[$name] = $text;
        }

        return $normalized;
    }
}
