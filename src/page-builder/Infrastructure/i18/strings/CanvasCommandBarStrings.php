<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class CanvasCommandBarStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'actions_group' => _x('Actions', 'Page Builder', 'uncanny-automator'),
            'agent_update_manual_edits' => _x('Turn on Manual edits to fine-tune it directly.', 'Page Builder', 'uncanny-automator'),
            'agent_update_ready' => _x('Your page update is ready.', 'Page Builder', 'uncanny-automator'),
            'cancel' => _x('Cancel', 'Page Builder', 'uncanny-automator'),
            'canvas_editor' => _x('Manual edits', 'Page Builder', 'uncanny-automator'),
            'current_page_group' => _x('Current page', 'Page Builder', 'uncanny-automator'),
            'copy_draft_permalink' => _x('Copy draft URL preview', 'Page Builder', 'uncanny-automator'),
            'copy_permalink' => _x('Copy permalink', 'Page Builder', 'uncanny-automator'),
            'draft_permalink' => _x('Draft URL preview (not live):', 'Page Builder', 'uncanny-automator'),
            'draft_status' => _x('Draft', 'Page Builder', 'uncanny-automator'),
            'dismiss_tip' => _x('Dismiss', 'Page Builder', 'uncanny-automator'),
            'dont_show_again' => _x("Don't show again", 'Page Builder', 'uncanny-automator'),
            'edit_title' => _x('Edit title', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Current page title. */
            'edit_title_for' => _x('Edit title for %s', 'Page Builder', 'uncanny-automator'),
            'edit_page_details' => _x('Edit page details', 'Page Builder', 'uncanny-automator'),
            'editor_short' => _x('Manual', 'Page Builder', 'uncanny-automator'),
            'enable_manual_edits' => _x('Turn on Manual Editor', 'Page Builder', 'uncanny-automator'),
            'enable_manual_edits_label' => _x('Turn on Manual Editor', 'Page Builder', 'uncanny-automator'),
            'error' => _x('Retry', 'Page Builder', 'uncanny-automator'),
            'global_settings_group' => _x('Global settings', 'Page Builder', 'uncanny-automator'),
            'hide_canvas_editor' => _x('Hide manual editor', 'Page Builder', 'uncanny-automator'),
            'import_export_group' => _x('Import/Export', 'Page Builder', 'uncanny-automator'),
            'live_status' => _x('Live', 'Page Builder', 'uncanny-automator'),
            'make_page_live' => _x('Make page live', 'Page Builder', 'uncanny-automator'),
            'manual_editing_tip_label' => _x('Manual editing tip', 'Page Builder', 'uncanny-automator'),
            'more_actions' => _x('More actions', 'Page Builder', 'uncanny-automator'),
            'more_page_actions' => _x('More page actions', 'Page Builder', 'uncanny-automator'),
            'move_to_draft' => _x('Move to draft', 'Page Builder', 'uncanny-automator'),
            'page_details_error' => _x('We couldn\'t save the page details. Your previous title and URL are unchanged; try again.', 'Page Builder', 'uncanny-automator'),
            'page_label' => _x('Page', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Human-readable page lifecycle status label. */
            'page_status_label' => _x('Page status: %s', 'Page Builder', 'uncanny-automator'),
            'page_title_placeholder' => _x('Page title', 'Page Builder', 'uncanny-automator'),
            'permalink' => _x('Permalink:', 'Page Builder', 'uncanny-automator'),
            'preview_in_new_tab' => _x('Preview in new tab', 'Page Builder', 'uncanny-automator'),
            'quick_links_group' => _x('Quick links', 'Page Builder', 'uncanny-automator'),
            'save' => _x('Save', 'Page Builder', 'uncanny-automator'),
            'saved' => _x('Saved', 'Page Builder', 'uncanny-automator'),
            'saving' => _x('Saving…', 'Page Builder', 'uncanny-automator'),
            'selected' => _x('Selected', 'Page Builder', 'uncanny-automator'),
            'slug' => _x('Slug', 'Page Builder', 'uncanny-automator'),
            'title' => _x('Title', 'Page Builder', 'uncanny-automator'),
            'untitled' => _x('Untitled', 'Page Builder', 'uncanny-automator'),
            'undo_pending_change' => _x('Undo pending change', 'Page Builder', 'uncanny-automator'),
            'undo_pending_change_description' => _x('Undo the latest browser-local change.', 'Page Builder', 'uncanny-automator'),
            'view_group' => _x('View', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
