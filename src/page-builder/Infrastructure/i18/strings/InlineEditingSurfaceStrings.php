<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class InlineEditingSurfaceStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'background_image_picker_title' => _x('Select background image', 'Page Builder', 'uncanny-automator'),
            'image_picker_title' => _x('Select image', 'Page Builder', 'uncanny-automator'),
            'link_text_label' => _x('Text', 'Page Builder', 'uncanny-automator'),
            'link_url_label' => _x('URL', 'Page Builder', 'uncanny-automator'),
            'link_url_placeholder' => _x('https://...', 'Page Builder', 'uncanny-automator'),
            'media_unavailable_message' => _x('WordPress media library is not available.', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
