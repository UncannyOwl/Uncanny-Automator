<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class GlobalPartModalStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'cancel_label' => _x('Cancel', 'Page Builder', 'uncanny-automator'),
            'close_label' => _x('Close dialog', 'Page Builder', 'uncanny-automator'),
            'modal_title' => _x('Save containing section as reusable', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: The section name. */
            'scope_help' => _x('This saves the entire %s section, not only the selected element.', 'Page Builder', 'uncanny-automator'),
            /* translators: 1: The section name. 2: The 1-based section index. */
            'section_format' => _x('%1$s (#%2$d)', 'Page Builder', 'uncanny-automator'),
            'title_placeholder' => _x('Reusable name…', 'Page Builder', 'uncanny-automator'),
            'type_footer' => _x('Footer', 'Page Builder', 'uncanny-automator'),
            'type_header' => _x('Header', 'Page Builder', 'uncanny-automator'),
            'type_section' => _x('Section', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
