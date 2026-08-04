<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\Access\PageBuilderAllowedCapabilityPort;

final class WordPressPageBuilderAllowedCapabilityPort implements PageBuilderAllowedCapabilityPort
{
    public function getAllowedCapabilities(): array
    {
        return ['manage_options'];
    }

    public function userHasCapability(string $capability): bool
    {
        return current_user_can($capability);
    }
}
