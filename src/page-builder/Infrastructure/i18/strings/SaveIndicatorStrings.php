<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class SaveIndicatorStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'default_saved' => _x('Saved', 'Page Builder', 'uncanny-automator'),
            'save_failed' => _x('We couldn\'t save your change. Try again.', 'Page Builder', 'uncanny-automator'),
            'saving' => _x('Saving…', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
