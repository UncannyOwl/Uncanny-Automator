<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain;

enum ErrorMessage
{
    // Section
    case HtmlRequired;
    case PageNotFound;
    case SectionNotFound;
    case SectionNotFoundOnPage;

    // Restore
    case SectionsRequired;
    case StaleHistorySnapshot;
    case StaleSourceGeneration;

    // Navigation
    case NavigationMenuNotFound;

    // Global Part
    case GpTitleRequired;
    case GpSectionRequired;
    case GpCreationFailed;
    case GpAiTypeNotAllowed;
    case GpInvalidAiMarkup;

    // Ownership & Auth
    case PageNotOwned;
    case PageEditForbidden;
    case GlobalPartEditForbidden;
    case BindingTargetForbidden;

    // Proposal
    case ProposalMalformed;
    case EditableKeyNotFound;
    case EditableTypeMismatch;
    case EditableDuplicateKey;
    case EditableHasNestedMarkup;
    case BindingContractUpdateInvalid;
    case BindingTargetIdRequired;
    case BindingIdRequired;
    case BindingContractHashRequired;
    case BindingTemplateRequired;
    case BindingTargetInvalid;
    case BindingTargetNotFound;

    // Validation
    case ValidationMultipleRoots;
    case ValidationForbiddenTag;
    case ValidationEditableDuplicate;
    case ValidationEditableInvalidType;
    case ValidationDynamicInvalid;
    case ValidationBindKeyInvalid;
    case ValidationPreservedKeyMissing;
    case ValidationManifestInvalid;

    // Layout
    case LayoutParamsRequired;

    // Design Standards
    case NotEnginePage;
    case InvalidBody;
    case InvalidOverridesBody;
    case IncompleteProfile;
    case MalformedProfile;
    case MalformedOverrides;
    case MissingPageId;
    case LockedDesignToken;

    // Shell Mode
    case InvalidMode;
    case ShellMissingHtml;
    case ShellHtmlTooLarge;
    case ShellMissingAnalysis;
    case WorkingCanvasRefreshFailed;
    case PageLayoutUpdateFailed;

    // Page Meta
    case PageNotFoundGeneric;
    case InvalidStatus;
    case NothingToUpdate;
    case PageMetaUpdateFailed;

    // Control Plane
    case ControlUnknown;
    case ControlUnsupportedContext;
    case ControlNotInvokable;
    case ControlInvokeForbidden;
    case ControlInvalidRequest;
    case ControlInvokeFailed;

    // Publication
    case PublicationStaticSafetyFailed;
    case PublicationNothingToPublish;
    case PublicationSlugConflict;
    case PublicationArtifactPersistFailed;
    case PublicationCommitFailed;

    // Agent Tool Responses
    case AgentToolContractInvalid;
    case InvalidRouteId;

    // Media Upload
    case MediaImageDataRequired;
    case MediaBase64DecodeFailed;
    case MediaUploadFailed;
    case MediaAttachmentFailed;
    case MediaUploadForbidden;
    case MediaUnsupportedType;
    case MediaTooLarge;

    // Static Export
    case StaticExportFailed;

    // Component Manifest
    case ManifestExtractionFailed;

    // Agent
    case AgentMissingHtml;
    case AgentMissingUpdates;
    case AgentNoPatches;
    case AgentPatchFailed;
    case AgentEditableNotFound;
    case AgentInvalidEditableKey;
    case AgentInvalidUpdate;
    case AgentMissingBindingId;
    case AgentInvalidChangeType;
    case AgentBindingNotFound;
    case AgentMissingQueryArgs;
    case AgentInvalidQueryKey;
    case AgentMissingTemplateHtml;
    case AgentNoActiveGlobalPart;
    case AgentWrongTool;
    case AgentMissingSectionIds;
    case AgentBindingElementNotFound;

    private const META = [
        'HtmlRequired'               => ['Please provide the section content before saving.', 400],
        'PageNotFound'               => ['This page could not be found. It may have been moved or deleted.', 404],
        'SectionNotFound'            => ['This section no longer exists. Try refreshing the page.', 404],
        'SectionNotFoundOnPage'      => ['The requested section was not found on this page.', 404],
        'SectionsRequired'           => ["We couldn't restore this page because its saved section data is missing. Refresh the editor and try again.", 400],
        'StaleHistorySnapshot'       => ['The page changed since this history entry was captured. Refresh and try again.', 409],
        'StaleSourceGeneration'      => ['This page changed while it was being saved. Refresh the editor, review the latest version, then try again.', 409],
        'NavigationMenuNotFound'     => ['The requested navigation menu could not be found.', 404],
        'GpTitleRequired'            => ['Enter a name before saving this reusable.', 400],
        'GpSectionRequired'          => ["This section doesn't have any content to save yet. Add some content and try again.", 400],
        'GpCreationFailed'           => ["We couldn't save this reusable. Your section is unchanged; try again.", 500],
        'GpAiTypeNotAllowed'         => ['Uncanny Agent can create reusable headers and footers here. Choose one of those types and try again.', 400],
        'GpInvalidAiMarkup'          => ["We couldn't save this reusable because some of its content isn't supported. Ask Uncanny Agent to rebuild it and try again.", 422],
        'PageNotOwned'               => ['This page is not managed by Uncanny Page Builder.', 403],
        'PageEditForbidden'          => ['You do not have permission to edit this page.', 403],
        'GlobalPartEditForbidden'    => ['You do not have permission to edit this reusable.', 403],
        'BindingTargetForbidden'     => ['You do not have permission to edit this binding target.', 403],
        'ProposalMalformed'          => ["We couldn't apply that edit because some information was missing. Refresh the editor and try again.", 400],
        'EditableKeyNotFound'        => ['This content changed before your edit could be applied. Refresh the editor and try again.', 422],
        'EditableTypeMismatch'       => ["This content can no longer accept that kind of edit. Refresh the editor, reselect it, and try again.", 422],
        'EditableDuplicateKey'       => ["We couldn't identify the exact content to change. Ask Uncanny Agent to rebuild the section, then try again.", 422],
        'EditableHasNestedMarkup'    => ["This content is too complex for a direct text edit. Ask Uncanny Agent to update the section instead.", 422],
        'BindingContractUpdateInvalid' => ["We couldn't update this dynamic content. Refresh the editor and try again.", 422],
        'BindingTargetIdRequired'    => ['target_id is required.', 400],
        'BindingIdRequired'          => ['binding_id is required.', 400],
        'BindingContractHashRequired' => ['expected_contract_hash is required.', 400],
        'BindingTemplateRequired'    => ['replacement_template_html is required.', 400],
        'BindingTargetInvalid'       => ["We couldn't identify the dynamic content to update. Refresh the editor and try again.", 400],
        'BindingTargetNotFound'      => ['That dynamic content no longer exists. Refresh the editor to see the latest version.', 404],
        'ValidationMultipleRoots'    => ["We couldn't save this section because its structure isn't supported. Undo the latest change or ask Uncanny Agent to rebuild it.", 422],
        'ValidationForbiddenTag'     => ["We couldn't save this section because it contains code that isn't allowed. Remove the latest custom code and try again.", 422],
        'ValidationEditableDuplicate' => ["We couldn't save this section because two editable areas have the same identity. Ask Uncanny Agent to rebuild the section.", 422],
        'ValidationEditableInvalidType' => ["We couldn't save this section because one editable area uses an unsupported content type. Ask Uncanny Agent to rebuild it.", 422],
        'ValidationDynamicInvalid'   => ["We couldn't save this section because its dynamic content isn't supported. Review the latest change and try again.", 422],
        'ValidationBindKeyInvalid'   => ["We couldn't save this section because one dynamic field isn't supported. Review the latest change and try again.", 422],
        'ValidationPreservedKeyMissing' => ['This section changed in an unexpected way. Refresh the editor and try your edit again.', 422],
        'ValidationManifestInvalid'  => ["We couldn't prepare this section for editing. Ask Uncanny Agent to rebuild it and try again.", 422],
        'LayoutParamsRequired'       => ["We couldn't build this page because some required content is missing. Refresh the editor and try again.", 400],
        'NotEnginePage'              => ["This page isn't managed by Uncanny Page Builder. Return to Pages and choose a Page Builder page.", 404],
        'InvalidBody'                => ["We couldn't save the site design settings. Refresh the editor and try again.", 400],
        'InvalidOverridesBody'       => ["We couldn't save this page's design settings. Refresh the editor and try again.", 400],
        'IncompleteProfile'          => ["Some site design settings are missing. Refresh the editor before making more changes.", 400],
        'MalformedProfile'           => ["We couldn't read the site design settings. Refresh the editor and try again.", 400],
        'MalformedOverrides'         => ["We couldn't read this page's design settings. Refresh the editor and try again.", 400],
        'MissingPageId'              => ["We couldn't identify this page. Return to Pages and open it again.", 400],
        'LockedDesignToken'          => ['That design setting is controlled by your site settings and cannot be changed on this page.', 409],
        'InvalidMode'                => ['That page setup option is no longer available. Reopen the page setup and choose another option.', 400],
        'ShellMissingHtml'           => ["We couldn't find a theme header or footer to import. Choose another page setup option.", 400],
        'ShellHtmlTooLarge'          => ["This theme's header or footer is too complex to import automatically. Choose another page setup option.", 413],
        'ShellMissingAnalysis'       => ["We couldn't finish reviewing the theme header and footer. Start the import again.", 400],
        'WorkingCanvasRefreshFailed' => ["We couldn't refresh the editor preview, but your page setup was not changed. Refresh the editor and try again.", 500],
        'PageLayoutUpdateFailed'     => ["We couldn't save that page setup, and no partial change was kept. Try again.", 500],
        'PageNotFoundGeneric'        => ['This page could not be found. It may have been moved or deleted.', 404],
        'InvalidStatus'              => ["We couldn't change this page's status. Refresh the editor and try again.", 422],
        'NothingToUpdate'            => ['Make a change to the page title or status before saving.', 400],
        'PageMetaUpdateFailed'       => ["We couldn't save the page details. Your previous title and URL are unchanged; try again.", 500],
        'ControlUnknown'             => ['That editor action is no longer available. Refresh the editor and try again.', 404],
        'ControlUnsupportedContext'  => ["That action isn't available here.", 400],
        'ControlNotInvokable'        => ["That action isn't available right now. Refresh the editor and try again.", 400],
        'ControlInvokeForbidden'     => ["You don't have permission to make this change. Ask a site administrator for access.", 403],
        'ControlInvalidRequest'      => ["We couldn't complete that action because some information was missing or invalid. Refresh the editor and try again.", 400],
        'ControlInvokeFailed'        => ["We couldn't complete that action. Try again. If it keeps happening, ask your site administrator for help.", 500],
        'PublicationStaticSafetyFailed' => ["One of this page's sections contains something we cannot publish safely. Review your latest changes, then try again.", 422],
        'PublicationNothingToPublish' => ['This page needs at least one section before it can be published. Add a section, then try again.', 422],
        'PublicationSlugConflict'    => ['That page URL is already in use. Choose a different URL in the page settings, then try again.', 409],
        'PublicationArtifactPersistFailed' => ["We couldn't prepare the live page, but your draft is safe. Try again in a moment.", 500],
        'PublicationCommitFailed'    => ["We couldn't update the live page, but your draft is safe. Try again in a moment.", 500],
        'AgentToolContractInvalid'   => ['The server produced an invalid tool response. Please try again.', 500],
        'InvalidRouteId'             => ['The URL contains an invalid or conflicting ID.', 400],
        'MediaImageDataRequired'     => ["We couldn't read that image. Choose it again and retry.", 400],
        'MediaBase64DecodeFailed'    => ["We couldn't read that image file. Choose another image and try again.", 400],
        'MediaUploadFailed'          => ["We couldn't save that image to the media library. Try again or choose another file.", 500],
        'MediaAttachmentFailed'      => ["The image was uploaded, but we couldn't add it to the media library. Try again.", 500],
        'MediaUploadForbidden'       => ['You do not have permission to upload media files.', 403],
        'MediaUnsupportedType'       => ['Choose a PNG, JPEG, GIF, or WebP image.', 422],
        'MediaTooLarge'              => ["That image is larger than this site's upload limit. Choose a smaller file and try again.", 413],
        'StaticExportFailed'         => ["We couldn't download this page as HTML. Try again in a moment.", 500],
        'ManifestExtractionFailed'   => ['Could not extract component manifest.', 500],

        // Agent
        'AgentMissingHtml'           => ['html must be a non-empty string.', 400],
        'AgentMissingUpdates'        => ['updates must be a non-empty array.', 400],
        'AgentNoPatches'             => ['At least one patch or css_rule is required.', 400],
        'AgentPatchFailed'           => ['A string patch could not be applied.', 422],
        'AgentEditableNotFound'      => ['The specified editable was not found in the section.', 422],
        'AgentInvalidEditableKey'    => ['An editable key contains invalid characters.', 422],
        'AgentInvalidUpdate'         => ['An editable update is invalid.', 422],
        'AgentMissingBindingId'      => ['binding_id is required.', 400],
        'AgentInvalidChangeType'     => ["change_type must be 'query' or 'template'.", 400],
        'AgentBindingNotFound'       => ['Specified binding not found.', 404],
        'AgentMissingQueryArgs'      => ['query_args required for change_type=query.', 400],
        'AgentInvalidQueryKey'       => ['query_args keys must be alphanumeric with hyphens/underscores.', 400],
        'AgentMissingTemplateHtml'   => ['template_html required for change_type=template.', 400],
        'AgentNoActiveGlobalPart'    => ['No active global part found.', 404],
        'AgentWrongTool'             => ['The given ID is a global part, not a section. Use update_global_part or patch_global_part instead.', 422],
        'AgentMissingSectionIds'     => ['section_ids must be a non-empty array.', 400],
        'AgentBindingElementNotFound' => ['The binding element was not found in the section HTML.', 422],
    ];

    public function message(): string
    {
        return self::META[$this->name][0];
    }

    public function httpStatus(): int
    {
        return self::META[$this->name][1];
    }
}
