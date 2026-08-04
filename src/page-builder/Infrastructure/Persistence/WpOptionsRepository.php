<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Application\Options\OptionsPortInterface;
use UncannyPageBuilder\Domain\Options\Enum;

/**
 * Persists plugin-owned site options in wp_options.
 *
 * Each option key maps to one wp_options row. Arrays rely on WordPress'
 * built-in serialization, while scalars are normalized on read to the caller's
 * requested default shape when one is provided.
 */
final class WpOptionsRepository implements OptionsPortInterface
{
    // ── Option read ─────────────────────────────────────────────────────────

    public function load(
        Enum $option,
        array|string|int|float|bool|null $default = null,
    ): array|string|int|float|bool|null {
        $stored = get_option($option->value, $default);

        return $this->normalizeLoadedValue($stored, $default);
    }

    // ── Option write ────────────────────────────────────────────────────────

    public function save(Enum $option, array|string|int|float|bool $value): void
    {
        update_option($option->value, $value, false);
        $this->disableAutoload($option);
    }

    public function delete(Enum $option): void
    {
        delete_option($option->value);
    }

    // ── Value normalization ────────────────────────────────────────────────

    private function normalizeLoadedValue(
        mixed $stored,
        array|string|int|float|bool|null $default,
    ): array|string|int|float|bool|null {
        if ($default === null) {
            return $this->isSupportedValue($stored) ? $stored : null;
        }

        if (is_array($default)) {
            return is_array($stored) ? $stored : $default;
        }

        if (is_array($stored)) {
            return $default;
        }

        if (!is_scalar($stored) && $stored !== null) {
            return $default;
        }

        if (is_bool($default)) {
            return $this->normalizeBool($stored, $default);
        }

        if (is_int($default)) {
            return $this->normalizeInt($stored, $default);
        }

        if (is_float($default)) {
            return $this->normalizeFloat($stored, $default);
        }

        return $stored === null ? $default : (string) $stored;
    }

    private function isSupportedValue(mixed $value): bool
    {
        return is_array($value) || is_scalar($value) || $value === null;
    }

    private function normalizeBool(mixed $stored, bool $default): bool
    {
        if (is_bool($stored)) {
            return $stored;
        }

        if (is_int($stored) || is_float($stored)) {
            return $stored !== 0;
        }

        if (!is_string($stored)) {
            return $default;
        }

        $normalized = strtolower(trim($stored));

        if ($normalized === '' || in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }

        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        return $default;
    }

    private function normalizeInt(mixed $stored, int $default): int
    {
        if (is_int($stored)) {
            return $stored;
        }

        if (is_bool($stored)) {
            return $stored ? 1 : 0;
        }

        if (is_float($stored)) {
            return (int) $stored;
        }

        if (is_string($stored) && is_numeric($stored)) {
            return (int) $stored;
        }

        return $default;
    }

    private function normalizeFloat(mixed $stored, float $default): float
    {
        if (is_float($stored)) {
            return $stored;
        }

        if (is_int($stored)) {
            return (float) $stored;
        }

        if (is_bool($stored)) {
            return $stored ? 1.0 : 0.0;
        }

        if (is_string($stored) && is_numeric($stored)) {
            return (float) $stored;
        }

        return $default;
    }

    // ── Autoload policy ─────────────────────────────────────────────────────

    private function disableAutoload(Enum $option): void
    {
        if (\function_exists(__NAMESPACE__ . '\\wp_set_option_autoload')) {
            wp_set_option_autoload($option->value, false);
            return;
        }

        if (\function_exists('wp_set_option_autoload')) {
            \wp_set_option_autoload($option->value, false);
        }
    }
}
