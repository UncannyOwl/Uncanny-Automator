<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Automator;

use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;

/**
 * Reads the Automator-owned Page Builder availability setting.
 */
final class AutomatorPageBuilderAvailability implements PageBuilderAvailabilityInterface
{
    public function allowsNewPages(): bool
    {
        if (!class_exists(Page_Builder_Settings::class)) {
            return true;
        }

        return (new Page_Builder_Settings())->is_enabled(true);
    }
}
