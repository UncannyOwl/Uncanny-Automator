<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\Section\DynamicContentConfig;

/**
 * Resolves WordPress filter values and injects them into DynamicContentConfig.
 *
 * This keeps apply_filters out of the domain layer.
 */
final class WpDynamicContentConfigProvider
{
    public function register(): void
    {
        $extraBlockedKeys = $this->applyArrayFilter('uncanny_page_builder_blocked_meta_keys', []);
        $metaValueTypes = $this->applyArrayFilter('uncanny_page_builder_meta_value_types', []);

        DynamicContentConfig::configure($extraBlockedKeys, $metaValueTypes);
    }

    /**
     * WordPress filters are external callbacks. A callback failure must not
     * stop plugin boot. The empty value keeps the domain defaults in use.
     *
     * @param array<mixed> $default
     * @return array<mixed>
     */
    private function applyArrayFilter(string $hook, array $default): array
    {
        try {
            $filtered = apply_filters($hook, $default);
        } catch (\Throwable $failure) {
            $this->reportFilterFailure($hook, $failure);

            return $default;
        }

        return is_array($filtered) ? $filtered : $default;
    }

    private function reportFilterFailure(string $hook, \Throwable $failure): void
    {
        try {
            error_log(sprintf(
                '[Uncanny Page Builder] WordPress filter "%s" failed (%s).',
                $hook,
                $failure::class,
            ));
        } catch (\Throwable) {
            // A log failure cannot stop plugin boot.
        }
    }
}
