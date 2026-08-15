<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Automator;

use Uncanny_Automator\App\Application\Page_Builder\Can_Load_Page_Builder;
use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;

use function Uncanny_Automator\App\Infrastructure\automator_feature_state_query;

/**
 * Combines PAGE_BUILDER_MENU policy with the Automator-owned saved setting.
 *
 * This boundary controls new-page affordances only. Existing owned pages and
 * published rendering remain available through the always-booted runtime.
 */
final class AutomatorPageBuilderAvailability implements PageBuilderAvailabilityInterface
{
    public function allowsNewPages(): bool
    {
        if (
            !class_exists(Can_Load_Page_Builder::class)
            || !class_exists(Page_Builder_Settings::class)
            || !function_exists('Uncanny_Automator\App\Infrastructure\automator_feature_state_query')
        ) {
            return false;
        }

        try {
            // The Axis decision and the customer's saved preference are separate
            // denials. Every new-page path shares this conjunctive boundary.
            return (new Can_Load_Page_Builder(automator_feature_state_query()))->execute()
                && (new Page_Builder_Settings())->is_enabled(true);
        } catch (\Throwable) {
            return false;
        }
    }
}
