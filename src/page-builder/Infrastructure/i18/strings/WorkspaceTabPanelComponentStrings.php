<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class WorkspaceTabPanelComponentStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'active' => _x('Active', 'Page Builder', 'uncanny-automator'),
            'bottom' => _x('Bottom', 'Page Builder', 'uncanny-automator'),
            'canvas_scope_page' => _x('Styles for the selected element on this page.', 'Page Builder', 'uncanny-automator'),
            'canvas_scope_global' => _x('Brand styles apply to every page built with Uncanny Page Builder.', 'Page Builder', 'uncanny-automator'),
            'canvas_scope_reusable' => _x('Styles for the selected element in this reusable.', 'Page Builder', 'uncanny-automator'),
            'click_highlight' => _x('Click a highlight to select an element.', 'Page Builder', 'uncanny-automator'),
            'clear_selection' => _x('Clear selection', 'Page Builder', 'uncanny-automator'),
            'contains_selected_element' => _x('The selected element is inside this section. Saving includes the entire section.', 'Page Builder', 'uncanny-automator'),
            'css_origin' => _x('CSS', 'Page Builder', 'uncanny-automator'),
            'default_origin' => _x('Default', 'Page Builder', 'uncanny-automator'),
            'delete_element' => _x('Delete element', 'Page Builder', 'uncanny-automator'),
            'delete_section' => _x('Delete section', 'Page Builder', 'uncanny-automator'),
            'delete_selected_element' => _x('Delete selected element', 'Page Builder', 'uncanny-automator'),
            'element_origin' => _x('Element', 'Page Builder', 'uncanny-automator'),
            'element_state' => _x('Element state', 'Page Builder', 'uncanny-automator'),
            'elements_tab' => _x('Elements', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Current element style state (normal, hover, focus, active). */
            'editing_styles' => _x('Editing %s styles', 'Page Builder', 'uncanny-automator'),
            'fallback_origin' => _x('Fallback', 'Page Builder', 'uncanny-automator'),
            'focus' => _x('Focus', 'Page Builder', 'uncanny-automator'),
            'focused_layer' => _x('Focused layer', 'Page Builder', 'uncanny-automator'),
            'global_origin' => _x('Brand styles', 'Page Builder', 'uncanny-automator'),
            'global_tab' => _x('Brand styles', 'Page Builder', 'uncanny-automator'),
            'generated_css_origin' => _x('Generated CSS', 'Page Builder', 'uncanny-automator'),
            'hover' => _x('Hover', 'Page Builder', 'uncanny-automator'),
            'hover_preview' => _x('Hover over the page to preview editable areas.', 'Page Builder', 'uncanny-automator'),
            'hover_states' => _x('Hover & states', 'Page Builder', 'uncanny-automator'),
            'inherited_origin' => _x('Inherited', 'Page Builder', 'uncanny-automator'),
            'inline_origin' => _x('Inline', 'Page Builder', 'uncanny-automator'),
            'inside_space' => _x('Inside space', 'Page Builder', 'uncanny-automator'),
            'left' => _x('Left', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Number of pending style changes. */
            'many_pending_changes' => _x('%s pending changes', 'Page Builder', 'uncanny-automator'),
            'manage_brand_styles' => _x('Manage typography', 'Page Builder', 'uncanny-automator'),
            'normal' => _x('Normal', 'Page Builder', 'uncanny-automator'),
            'navigator' => _x('Navigator', 'Page Builder', 'uncanny-automator'),
            'no_authored_style_sources' => _x('No authored styles. This element uses browser defaults.', 'Page Builder', 'uncanny-automator'),
            'one_pending_change' => _x('1 pending change', 'Page Builder', 'uncanny-automator'),
            'outside_space' => _x('Outside space', 'Page Builder', 'uncanny-automator'),
            'page_origin' => _x('Page', 'Page Builder', 'uncanny-automator'),
            'page_scope' => _x('Page-specific styles for this page.', 'Page Builder', 'uncanny-automator'),
            'page_tab' => _x('Page', 'Page Builder', 'uncanny-automator'),
            'pending_style_overrides' => _x('Pending style overrides', 'Page Builder', 'uncanny-automator'),
            'reset' => _x('Reset', 'Page Builder', 'uncanny-automator'),
            'reset_all_pending_changes' => _x('Reset all pending changes for selected element', 'Page Builder', 'uncanny-automator'),
            /* translators: 1: Box control label (for example, margin or padding). 2: Box side label. */
            'reset_box_side_label' => _x('%1$s %2$s', 'Page Builder', 'uncanny-automator'),
            'reset_pending_changes' => _x('Reset pending changes for selected element', 'Page Builder', 'uncanny-automator'),
            'reset_pending_changes_confirm' => _x('Reset pending changes for the selected element?', 'Page Builder', 'uncanny-automator'),
            'reset_selected_element' => _x('Reset selected element', 'Page Builder', 'uncanny-automator'),
            'right' => _x('Right', 'Page Builder', 'uncanny-automator'),
            'save_as_reusable' => _x('Save as reusable', 'Page Builder', 'uncanny-automator'),
            'save_section_as_reusable' => _x('Save containing section as reusable', 'Page Builder', 'uncanny-automator'),
            'section' => _x('Section', 'Page Builder', 'uncanny-automator'),
            'section_actions' => _x('Section actions', 'Page Builder', 'uncanny-automator'),
            'site_design_tab' => _x('Brand styles', 'Page Builder', 'uncanny-automator'),
            'spacing' => _x('Spacing', 'Page Builder', 'uncanny-automator'),
            'selected_element' => _x('Selected element', 'Page Builder', 'uncanny-automator'),
            'selected_suffix' => _x('(selected)', 'Page Builder', 'uncanny-automator'),
            'text_editing_help' => _x('Single-click an element to select it. Double-click text or single-click an inline image to edit it.', 'Page Builder', 'uncanny-automator'),
            'token_origin' => _x('Token', 'Page Builder', 'uncanny-automator'),
            'theme_css_origin' => _x('Theme CSS', 'Page Builder', 'uncanny-automator'),
            'top' => _x('Top', 'Page Builder', 'uncanny-automator'),
            'where_styles_come_from' => _x('Where styles come from', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
