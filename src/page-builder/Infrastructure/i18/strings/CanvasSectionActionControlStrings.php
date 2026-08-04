<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class CanvasSectionActionControlStrings
{
    /**
     * @return array{label: string, description: string}
     */
    public function saveAsReusable(): array
    {
        return [
            'label' => _x('Save containing section as reusable', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Save the entire section containing the selected element as a reusable part.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string, confirm: string}
     */
    public function deleteSection(): array
    {
        return [
            'label' => _x('Delete', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Delete this section from the page.', 'Page Builder', 'uncanny-automator'),
            'confirm' => _x('Are you sure you want to delete this section? This action can be undone.', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
