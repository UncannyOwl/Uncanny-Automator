<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class WorkspaceTabPanelDesignColorControlStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'brand_wash' => _x('Brand wash', 'Page Builder', 'uncanny-automator'),
            'deep_brand' => _x('Deep brand', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Color control label (such as Color). */
            'picker_label' => _x('%s picker', 'Page Builder', 'uncanny-automator'),
            'primary_blend' => _x('Primary blend', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Color value input label (such as Color). */
            'value_label' => _x('%s value', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
