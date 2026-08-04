<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class WorkspaceTabPanelAttributesStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'accessibility' => _x('Accessibility', 'Page Builder', 'uncanny-automator'),
            'attributes' => _x('Attributes', 'Page Builder', 'uncanny-automator'),
            'link_behavior' => _x('Link behavior', 'Page Builder', 'uncanny-automator'),
            'primary_attributes' => _x('Primary attributes', 'Page Builder', 'uncanny-automator'),
            'raw_attributes' => _x('Raw attributes', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
