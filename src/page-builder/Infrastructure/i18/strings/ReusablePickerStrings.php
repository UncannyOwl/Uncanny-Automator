<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class ReusablePickerStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'add_saved_section' => _x('Add a saved section', 'Page Builder', 'uncanny-automator'),
            'add_to_page' => _x('Add to page', 'Page Builder', 'uncanny-automator'),
            'adding' => _x('Adding…', 'Page Builder', 'uncanny-automator'),
            'browse_sections' => _x('Browse reusable sections and add one to this page.', 'Page Builder', 'uncanny-automator'),
            'close' => _x('Close', 'Page Builder', 'uncanny-automator'),
            'empty_body' => _x('Use "Save containing section as reusable" in the Section panel to save one here.', 'Page Builder', 'uncanny-automator'),
            'empty_title' => _x('No saved sections yet.', 'Page Builder', 'uncanny-automator'),
            'loading' => _x('Loading saved sections...', 'Page Builder', 'uncanny-automator'),
            'modal_title' => _x('Saved sections', 'Page Builder', 'uncanny-automator'),
            'modal_description' => _x('When you add a saved section to this page, it becomes a separate copy. Any edits you make to it here will stay on this page only.', 'Page Builder', 'uncanny-automator'),
            'modal_description_more_help' => _x('Reusable headers and footers work differently. Changes to them will update every Uncanny Page Builder page that uses them.', 'Page Builder', 'uncanny-automator'),
            'open_library' => _x('Open library', 'Page Builder', 'uncanny-automator'),
            'open_library_aria' => _x('Open section library', 'Page Builder', 'uncanny-automator'),
            'section_library' => _x('Section library', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
