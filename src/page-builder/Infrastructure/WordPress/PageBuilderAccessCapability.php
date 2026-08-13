<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;

/**
 * Bridges Page Builder's authoring policy into WordPress capability checks.
 *
 * WordPress menus, CPT screens, and hidden admin pages need one capability
 * string. Page Builder's actual authoring policy stays in the application use
 * case, so this adapter grants a synthetic capability when that use case allows
 * the current user to author Page Builder content.
 */
final class PageBuilderAccessCapability
{
    public const NAME = 'uncanny_page_builder_access';

    public function __construct(
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
    ) {}

    public function register(): void
    {
        $callbacks = new WordPressCallbackBoundary();
        add_filter('user_has_cap', $callbacks->filter('capability.grant', [$this, 'grantCapability']), 10, 4);
    }

    /**
     * @param array<string, bool> $allCaps
     * @param array<int, string> $requestedCaps
     * @param array<int, mixed> $args
     * @return array<string, bool>
     */
    public function grantCapability($allCaps = null, $requestedCaps = null, $args = null, $user = null): array
    {
        unset($user);

        $allCaps = is_array($allCaps) ? $allCaps : [];
        $requestedCaps = is_array($requestedCaps) ? $requestedCaps : [];
        $args = is_array($args) ? $args : [];

        $requestedCapability = is_string($args[0] ?? null)
            ? $args[0]
            : '';

        if ($requestedCapability !== self::NAME && !in_array(self::NAME, $requestedCaps, true)) {
            return $allCaps;
        }

        if ($this->allowedCapabilities->currentUserHasAllowedCapability()) {
            $allCaps[self::NAME] = true;
        }

        return $allCaps;
    }
}
