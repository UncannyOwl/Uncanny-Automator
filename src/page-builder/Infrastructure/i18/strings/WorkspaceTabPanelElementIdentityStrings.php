<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

/**
 * Spoken names for a selected canvas element.
 *
 * Design Lens reports a selector-shaped symbol. These names give the panel a
 * word a site owner can act on while the selector stays as supporting detail.
 */
final class WorkspaceTabPanelElementIdentityStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'heading' => _x('Heading', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Heading level, 1 through 6. */
            'heading_level' => _x('Heading %s', 'Page Builder', 'uncanny-automator'),
            'paragraph' => _x('Paragraph', 'Page Builder', 'uncanny-automator'),
            'image' => _x('Image', 'Page Builder', 'uncanny-automator'),
            'link' => _x('Link', 'Page Builder', 'uncanny-automator'),
            'button' => _x('Button', 'Page Builder', 'uncanny-automator'),
            'list' => _x('List', 'Page Builder', 'uncanny-automator'),
            'list_item' => _x('List item', 'Page Builder', 'uncanny-automator'),
            'quote' => _x('Quote', 'Page Builder', 'uncanny-automator'),
            'table' => _x('Table', 'Page Builder', 'uncanny-automator'),
            'video' => _x('Video', 'Page Builder', 'uncanny-automator'),
            'audio' => _x('Audio', 'Page Builder', 'uncanny-automator'),
            'embed' => _x('Embed', 'Page Builder', 'uncanny-automator'),
            'icon' => _x('Icon', 'Page Builder', 'uncanny-automator'),
            'figure' => _x('Figure', 'Page Builder', 'uncanny-automator'),
            'caption' => _x('Caption', 'Page Builder', 'uncanny-automator'),
            'separator' => _x('Separator', 'Page Builder', 'uncanny-automator'),
            'form' => _x('Form', 'Page Builder', 'uncanny-automator'),
            'input' => _x('Field', 'Page Builder', 'uncanny-automator'),
            'header' => _x('Header', 'Page Builder', 'uncanny-automator'),
            'footer' => _x('Footer', 'Page Builder', 'uncanny-automator'),
            'navigation' => _x('Navigation', 'Page Builder', 'uncanny-automator'),
            'container' => _x('Container', 'Page Builder', 'uncanny-automator'),
            'text' => _x('Text', 'Page Builder', 'uncanny-automator'),
            'element' => _x('Element', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
