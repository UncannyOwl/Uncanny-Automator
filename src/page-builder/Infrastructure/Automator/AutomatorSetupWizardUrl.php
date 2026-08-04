<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Automator;

/**
 * Builds the Automator setup wizard URL.
 */
final class AutomatorSetupWizardUrl
{
    public function get(): string
    {
        $postType = defined('AUTOMATOR_POST_TYPE_RECIPE')
            ? (string) AUTOMATOR_POST_TYPE_RECIPE
            : 'uo-recipe';

        return add_query_arg(
            [
                'post_type' => $postType,
                'page' => 'uncanny-automator-setup-wizard',
            ],
            admin_url('edit.php'),
        );
    }
}
