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
        $extraBlockedKeys = apply_filters('uncanny_page_builder_blocked_meta_keys', []);
        if (!is_array($extraBlockedKeys)) {
            $extraBlockedKeys = [];
        }

        $metaValueTypes = apply_filters('uncanny_page_builder_meta_value_types', []);
        if (!is_array($metaValueTypes)) {
            $metaValueTypes = [];
        }

        DynamicContentConfig::configure($extraBlockedKeys, $metaValueTypes);
    }
}
