<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class ErrorToastStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'dismiss' => _x('Dismiss', 'Page Builder', 'uncanny-automator'),
            'generic_error' => _x('Something went wrong. Try again. If it keeps happening, ask your site administrator for help.', 'Page Builder', 'uncanny-automator'),
            'section_conflict' => _x('This section was modified elsewhere. Refresh to get the latest version.', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
