<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class EditorLockStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'warning_title' => _x('Editing ownership could not be verified.', 'Page Builder', 'uncanny-automator'),
            'warning_body' => _x('You can keep working, but saving may require another try.', 'Page Builder', 'uncanny-automator'),
            'paused_title' => _x('Editing is paused', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Name of the user editing the item. */
            'owner_named' => _x('%s is now editing this item.', 'Page Builder', 'uncanny-automator'),
            'owner_unknown' => _x('Another user is now editing this item.', 'Page Builder', 'uncanny-automator'),
            'source_changed' => _x('The saved source changed while editing was paused. Reload before continuing. Reloading discards the unsaved changes in this tab.', 'Page Builder', 'uncanny-automator'),
            'reload_discard' => _x('Reload and discard local changes', 'Page Builder', 'uncanny-automator'),
            'unsaved_changes' => _x('Your unsaved changes remain in this tab and have not been saved.', 'Page Builder', 'uncanny-automator'),
            'checking' => _x('Checking…', 'Page Builder', 'uncanny-automator'),
            'check_again' => _x('Check again', 'Page Builder', 'uncanny-automator'),
            'exit_editor' => _x('Exit editor', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
