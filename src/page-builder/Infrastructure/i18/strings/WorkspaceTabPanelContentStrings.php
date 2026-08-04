<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class WorkspaceTabPanelContentStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'alt_text' => _x('Alt text', 'Page Builder', 'uncanny-automator'),
            'aria_label' => _x('ARIA label', 'Page Builder', 'uncanny-automator'),
            'content' => _x('Content', 'Page Builder', 'uncanny-automator'),
            'disabled' => _x('Disabled', 'Page Builder', 'uncanny-automator'),
            'field' => _x('Field', 'Page Builder', 'uncanny-automator'),
            'image_url' => _x('Image URL', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Inline text index. */
            'inline_text_numbered' => _x('Inline text %s', 'Page Builder', 'uncanny-automator'),
            'link_relationship' => _x('Link relationship', 'Page Builder', 'uncanny-automator'),
            'link_target' => _x('Link target', 'Page Builder', 'uncanny-automator'),
            'link_text' => _x('Link text', 'Page Builder', 'uncanny-automator'),
            'link_url' => _x('Link URL', 'Page Builder', 'uncanny-automator'),
            'media_url' => _x('Media URL', 'Page Builder', 'uncanny-automator'),
            'name' => _x('Name', 'Page Builder', 'uncanny-automator'),
            'not_set' => _x('Not set', 'Page Builder', 'uncanny-automator'),
            'off' => _x('Off', 'Page Builder', 'uncanny-automator'),
            'on' => _x('On', 'Page Builder', 'uncanny-automator'),
            'placeholder' => _x('Placeholder', 'Page Builder', 'uncanny-automator'),
            'readonly' => _x('Readonly', 'Page Builder', 'uncanny-automator'),
            'replace_image' => _x('Replace Image', 'Page Builder', 'uncanny-automator'),
            'required' => _x('Required', 'Page Builder', 'uncanny-automator'),
            'save_failed' => _x('This field could not be saved.', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Symbol name. */
            'symbol_text' => _x('%s text', 'Page Builder', 'uncanny-automator'),
            'text' => _x('Text', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Text index. */
            'text_numbered' => _x('Text %s', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Text run index. */
            'text_run_numbered' => _x('Text run %s', 'Page Builder', 'uncanny-automator'),
            'title' => _x('Title', 'Page Builder', 'uncanny-automator'),
            'type' => _x('Type', 'Page Builder', 'uncanny-automator'),
            'value' => _x('Value', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
