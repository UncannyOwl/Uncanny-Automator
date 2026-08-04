<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class DeleteConfirmationModalStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'cancel_label' => _x('Cancel', 'Page Builder', 'uncanny-automator'),
            'close_label' => _x('Close dialog', 'Page Builder', 'uncanny-automator'),
            'message' => _x('Are you sure you want to delete this section? This action can be undone.', 'Page Builder', 'uncanny-automator'),
            'modal_title' => _x('Delete section', 'Page Builder', 'uncanny-automator'),
            /* translators: 1: The section name. 2: The 1-based section index. */
            'section_format' => _x('%1$s (#%2$d)', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
