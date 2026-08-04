<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class WorkspaceTabPanelHelperStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'computed_details' => _x('Computed details', 'Page Builder', 'uncanny-automator'),
            'reset' => _x('Reset', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Style property label for the field being reset. */
            'reset_field' => _x('Reset %s', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
