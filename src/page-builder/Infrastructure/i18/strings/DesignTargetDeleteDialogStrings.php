<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class DesignTargetDeleteDialogStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'cancel' => _x('Cancel', 'Page Builder', 'uncanny-automator'),
            'delete' => _x('Delete', 'Page Builder', 'uncanny-automator'),
            'message' => _x('Delete this element? You can undo this change before saving.', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
