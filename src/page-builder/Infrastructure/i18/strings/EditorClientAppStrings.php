<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class EditorClientAppStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'agent_editing_body' => _x('Wait for it to finish before making Manual editor changes.', 'Page Builder', 'uncanny-automator'),
            'agent_editing_reload' => _x('Reload editor', 'Page Builder', 'uncanny-automator'),
            'agent_editing_title' => _x('Uncanny Agent is editing this page.', 'Page Builder', 'uncanny-automator'),
            'attribute_not_saveable' => _x('This attribute cannot be saved yet. Select editable Page Builder content and try again.', 'Page Builder', 'uncanny-automator'),
            'attribute_preview_failed' => _x('We couldn\'t preview that change. Reselect the element and try again.', 'Page Builder', 'uncanny-automator'),
            'attribute_save_failed' => _x('We couldn\'t save that change. Your previous value is still in place; try again.', 'Page Builder', 'uncanny-automator'),
            'binding_owned_content' => _x('This is dynamic data and cannot be edited directly. Edit the dynamic source instead.', 'Page Builder', 'uncanny-automator'),
            'binding_owned_element' => _x('This element is dynamic data and cannot be edited directly. Style the dynamic wrapper or edit the dynamic source instead.', 'Page Builder', 'uncanny-automator'),
            'could_not_open_agent' => _x('Uncanny Agent didn\'t open. Try again, or refresh the editor if it keeps happening.', 'Page Builder', 'uncanny-automator'),
            'could_not_send_selection' => _x('We couldn\'t send that element to Uncanny Agent. Try again, or refresh the editor if it keeps happening.', 'Page Builder', 'uncanny-automator'),
            'could_not_load_sections' => _x('We couldn\'t load your saved sections. Close the library and try again.', 'Page Builder', 'uncanny-automator'),
            'draft_saved' => _x('Draft saved', 'Page Builder', 'uncanny-automator'),
            'delete' => _x('Delete', 'Page Builder', 'uncanny-automator'),
            'dismiss' => _x('Dismiss', 'Page Builder', 'uncanny-automator'),
            'deleting_section' => _x('Deleting section…', 'Page Builder', 'uncanny-automator'),
            'design_preview_rejected' => _x('Design preview could not be applied. Reselect the element and try again.', 'Page Builder', 'uncanny-automator'),
            'design_lens_unavailable' => _x('We couldn\'t open the design tools. Refresh the editor and try again.', 'Page Builder', 'uncanny-automator'),
            'design_state_unavailable' => _x('That design view is no longer available. Reselect the element and try again.', 'Page Builder', 'uncanny-automator'),
            'deleting' => _x('Deleting…', 'Page Builder', 'uncanny-automator'),
            'element_delete_not_saveable' => _x('This element cannot be deleted yet. Select a generated Page Builder element and try again.', 'Page Builder', 'uncanny-automator'),
            'element_target_unresolved' => _x('This element could not be matched to a section. Refresh the Manual editor and try again.', 'Page Builder', 'uncanny-automator'),
            'failed_to_add_section' => _x('We couldn\'t add that section. Close the library and try again.', 'Page Builder', 'uncanny-automator'),
            'failed_to_import_section' => _x('We couldn\'t import that section. Check the file and try again.', 'Page Builder', 'uncanny-automator'),
            'image_not_saveable' => _x('This image cannot be saved yet. Select an editable Page Builder image and try again.', 'Page Builder', 'uncanny-automator'),
            'image_preview_failed' => _x('We couldn\'t preview that image. Choose it again and retry.', 'Page Builder', 'uncanny-automator'),
            'image_save_failed' => _x('We couldn\'t save that image change. Your previous image is still in place; try again.', 'Page Builder', 'uncanny-automator'),
            'layer_projection_missing' => _x('This layer could not be inspected. Select the element again and retry.', 'Page Builder', 'uncanny-automator'),
            'manual_editor_refresh_failed' => _x('We couldn\'t refresh the Manual editor. Your saved draft is unchanged; refresh the page and try again.', 'Page Builder', 'uncanny-automator'),
            'media_library_unavailable' => _x('The media library didn\'t open. Refresh the editor and try again.', 'Page Builder', 'uncanny-automator'),
            'media_picker_background_title' => _x('Choose background image', 'Page Builder', 'uncanny-automator'),
            'media_picker_image_title' => _x('Replace image', 'Page Builder', 'uncanny-automator'),
            'no' => _x('No', 'Page Builder', 'uncanny-automator'),
            'parked_draft_message' => _x('There is a draft newer than the currently published page. Do you want to load it?', 'Page Builder', 'uncanny-automator'),
            'publish_waiting_for_draft' => _x('Publish after the pending Manual changes are saved to the working draft.', 'Page Builder', 'uncanny-automator'),
            'retry_save' => _x('Retry save', 'Page Builder', 'uncanny-automator'),
            'reusable_save_failed' => _x('We couldn\'t save this reusable. Your section is unchanged; try again.', 'Page Builder', 'uncanny-automator'),
            'save' => _x('Save', 'Page Builder', 'uncanny-automator'),
            'save_draft' => _x('Save draft', 'Page Builder', 'uncanny-automator'),
            'saved' => _x('Saved!', 'Page Builder', 'uncanny-automator'),
            'shortcode_owned_content' => _x('This is dynamic data rendered by a shortcode, so it cannot be edited directly. Edit the shortcode instead.', 'Page Builder', 'uncanny-automator'),
            'section_deleted' => _x('Section deleted', 'Page Builder', 'uncanny-automator'),
            'section_delete_failed' => _x('We couldn\'t delete that section. It is still on the page; try again.', 'Page Builder', 'uncanny-automator'),
            'section_delete_instead' => _x('Use the section delete action to delete a section.', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: The 1-based section number. */
            'section_fallback' => _x('Section %s', 'Page Builder', 'uncanny-automator'),
            'saving' => _x('Saving…', 'Page Builder', 'uncanny-automator'),
            'saving_draft' => _x('Saving draft…', 'Page Builder', 'uncanny-automator'),
            'agent_finished' => _x('Uncanny Agent finished. Review the current draft, then update the live page when ready.', 'Page Builder', 'uncanny-automator'),
            'text_not_saveable' => _x('This text cannot be saved yet. Select editable Page Builder text and try again.', 'Page Builder', 'uncanny-automator'),
            'text_preview_failed' => _x('We couldn\'t preview that text change. Reselect the text and try again.', 'Page Builder', 'uncanny-automator'),
            'text_save_failed' => _x('We couldn\'t save that text change. Your previous text is still in place; try again.', 'Page Builder', 'uncanny-automator'),
            'unsupported_panel_content_type' => _x('This type of content can\'t be edited here yet. Select another element or ask Uncanny Agent to change it.', 'Page Builder', 'uncanny-automator'),
            'unknown_error' => _x('Something went wrong. Try again, or refresh the editor if it keeps happening.', 'Page Builder', 'uncanny-automator'),
            'yes' => _x('Yes', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
