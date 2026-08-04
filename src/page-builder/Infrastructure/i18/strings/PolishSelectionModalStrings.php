<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class PolishSelectionModalStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'body_intro' => _x('Describe the result you want. The selected element is already included.', 'Page Builder', 'uncanny-automator'),
            'clear_selection' => _x('Clear selection', 'Page Builder', 'uncanny-automator'),
            'modal_title' => _x('What should the agent change?', 'Page Builder', 'uncanny-automator'),
            'prompt_help' => _x('Press Enter to submit. Press Shift+Enter for a new line.', 'Page Builder', 'uncanny-automator'),
            'prompt_label' => _x('Instructions', 'Page Builder', 'uncanny-automator'),
            'prompt_placeholder' => _x('Example: Make this section feel more premium. Tighten the spacing, improve readability, and keep the existing structure.', 'Page Builder', 'uncanny-automator'),
            'selection_button_label' => _x('Edit with Uncanny Agent', 'Page Builder', 'uncanny-automator'),
            'submit_instructions' => _x('Submit instructions', 'Page Builder', 'uncanny-automator'),
            'submitting_text' => _x('Reading your message...', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
