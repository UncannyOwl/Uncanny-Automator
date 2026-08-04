<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class WorkspaceTabPanelTextStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'content' => _x('Content', 'Page Builder', 'uncanny-automator'),
            'inline_link_media_helpers' => _x('Inline link/media helpers', 'Page Builder', 'uncanny-automator'),
            'raw_content_details' => _x('Raw content details', 'Page Builder', 'uncanny-automator'),
            'rich_edit' => _x('Rich Edit', 'Page Builder', 'uncanny-automator'),
            'text' => _x('Text', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
