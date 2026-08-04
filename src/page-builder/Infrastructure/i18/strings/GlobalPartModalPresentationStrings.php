<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class GlobalPartModalPresentationStrings
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'modal_title' => _x('Save containing section as reusable', 'Page Builder', 'uncanny-automator'),
            'close_label' => _x('Close dialog', 'Page Builder', 'uncanny-automator'),
            'cancel_label' => _x('Cancel', 'Page Builder', 'uncanny-automator'),
            /* translators: 1: The section name. 2: The 1-based section index. */
            'section_format' => _x('%1$s (#%2$d)', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: The section name. */
            'scope_help' => _x('This saves the entire %s section, not only the selected element.', 'Page Builder', 'uncanny-automator'),
            'submit_label' => _x('Save', 'Page Builder', 'uncanny-automator'),
            'busy_label' => _x('Saving…', 'Page Builder', 'uncanny-automator'),
            'saving_message' => _x('Saving as reusable…', 'Page Builder', 'uncanny-automator'),
            'success_message' => _x('Saved!', 'Page Builder', 'uncanny-automator'),
            'title_placeholder' => _x('Reusable name…', 'Page Builder', 'uncanny-automator'),
            'type_options' => [
                [
                    'value' => 'section',
                    'label' => _x('Section', 'Page Builder', 'uncanny-automator'),
                ],
                [
                    'value' => 'header',
                    'label' => _x('Header', 'Page Builder', 'uncanny-automator'),
                    'disabled_shell_mode' => 'theme_composition',
                    'disabled_reason' => _x('Header is provided by your theme in Compose mode.', 'Page Builder', 'uncanny-automator'),
                ],
                [
                    'value' => 'footer',
                    'label' => _x('Footer', 'Page Builder', 'uncanny-automator'),
                    'disabled_shell_mode' => 'theme_composition',
                    'disabled_reason' => _x('Footer is provided by your theme in Compose mode.', 'Page Builder', 'uncanny-automator'),
                ],
            ],
        ];
    }
}
