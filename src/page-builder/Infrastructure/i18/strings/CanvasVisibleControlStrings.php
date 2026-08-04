<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class CanvasVisibleControlStrings
{
    /**
     * @return array{label: string, description: string}
     */
    public function historyUndo(): array
    {
        return [
            'label' => _x('Undo', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Revert the previous editor operation.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function pageTitle(): array
    {
        return [
            'label' => _x('Page title', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Update the draft title. It stays private until you publish.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function pageDetails(): array
    {
        return [
            'label' => _x('Page details', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Update the draft title and URL slug. They stay private until you publish.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function pageNewCanvas(): array
    {
        return [
            'label' => _x('New page', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Create a new Uncanny Page Builder page.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageFullScreenMode(): array
    {
        return [
            'label' => _x('Full screen mode', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Open this page in the full-screen manual editor.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Full screen mode', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Open this page in the full-screen manual editor.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('View', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageExitFullScreenMode(): array
    {
        return [
            'label' => _x('Exit full screen mode', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Return to the embedded manual editor.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Exit full screen mode', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Return to the embedded manual editor.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('View', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, string>}
     */
    public function viewportMode(): array
    {
        return [
            'label' => _x('Viewport', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Change the editor preview viewport.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'preview_label' => _x('Preview in new tab', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function pageCanvasType(): array
    {
        return [
            'label' => _x('Page type', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Current editor page type.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function historyRedo(): array
    {
        return [
            'label' => _x('Redo', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Reapply the next editor operation.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function pageStatus(): array
    {
        return [
            'label' => _x('Page status', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Whether the durable working draft matches the visitor-facing page.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageSaveDraft(): array
    {
        return [
            'label' => _x('Save draft', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Save this page as a draft.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'busy_label' => _x('Saving draft…', 'Page Builder', 'uncanny-automator'),
                'saved_label' => _x('Draft saved', 'Page Builder', 'uncanny-automator'),
                'error_label' => _x('Retry save', 'Page Builder', 'uncanny-automator'),
                'compact_label' => _x('Save draft', 'Page Builder', 'uncanny-automator'),
                'menu_label' => _x('Save draft', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Save changes without publishing.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Actions', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageSavePublished(): array
    {
        return [
            'label' => _x('Publish changes', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Publish the saved working draft as the current page version.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'busy_label' => _x('Publishing changes…', 'Page Builder', 'uncanny-automator'),
                'saved_label' => _x('Changes published', 'Page Builder', 'uncanny-automator'),
                'error_label' => _x('Retry publish', 'Page Builder', 'uncanny-automator'),
                'compact_label' => _x('Publish changes', 'Page Builder', 'uncanny-automator'),
                'menu_label' => _x('Publish changes', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Publish the saved working draft without changing page visibility.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Actions', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageSwitchToDraft(): array
    {
        return [
            'label' => _x('Draft', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Take the current published page offline without saving other changes.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'busy_label' => _x('Moving to draft…', 'Page Builder', 'uncanny-automator'),
                'saved_label' => _x('Draft', 'Page Builder', 'uncanny-automator'),
                'error_label' => _x('Retry', 'Page Builder', 'uncanny-automator'),
                'lifecycle_label' => _x('Draft', 'Page Builder', 'uncanny-automator'),
                'menu_label' => _x('Draft', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Take the current published page offline without saving other changes.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Actions', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageMakeLive(): array
    {
        return [
            'label' => _x('Published', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Restore the last published page without saving other changes.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'busy_label' => _x('Publishing…', 'Page Builder', 'uncanny-automator'),
                'saved_label' => _x('Published', 'Page Builder', 'uncanny-automator'),
                'error_label' => _x('Retry', 'Page Builder', 'uncanny-automator'),
                'lifecycle_label' => _x('Published', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pagePublish(): array
    {
        return [
            'label' => _x('Publish', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Publish this page for the first time.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'busy_label' => _x('Publishing…', 'Page Builder', 'uncanny-automator'),
                'saved_label' => _x('Published', 'Page Builder', 'uncanny-automator'),
                'error_label' => _x('Retry publish', 'Page Builder', 'uncanny-automator'),
                'lifecycle_label' => _x('Published', 'Page Builder', 'uncanny-automator'),
                'menu_label' => _x('Publish', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Save and publish this page.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Actions', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function shellModeOpen(): array
    {
        return [
            'label' => _x('Layout', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Choose how this page should work.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function pagePreview(): array
    {
        return [
            'label' => _x('Preview', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Preview the page in a new tab.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageStaticExport(): array
    {
        return [
            'label' => _x('Download as HTML', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Download an HTML version of this page.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Download as HTML', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Download a portable HTML version of this page.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Actions', 'Page Builder', 'uncanny-automator'),
                'button_label' => _x('Download HTML', 'Page Builder', 'uncanny-automator'),
                'busy_label' => _x('Exporting...', 'Page Builder', 'uncanny-automator'),
                'success_label' => _x('Downloaded', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageTrash(): array
    {
        return [
            'label' => _x('Move to Trash', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Move this page to the Trash.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Move to Trash', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Move this page to the Trash.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Actions', 'Page Builder', 'uncanny-automator'),
                'destructive' => true,
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageSourceExport(): array
    {
        return [
            'label' => _x('Export page', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Download an importable Page Builder source package for this page.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Export page', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Download an importable Page Builder source package for this page.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Import/Export', 'Page Builder', 'uncanny-automator'),
                'button_label' => _x('Export page', 'Page Builder', 'uncanny-automator'),
                'busy_label' => _x('Exporting...', 'Page Builder', 'uncanny-automator'),
                'success_label' => _x('Exported', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageSourceImport(): array
    {
        return [
            'label' => _x('Import Uncanny Page Builder page', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Create a new Uncanny Page Builder draft from an exported page package.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Import Uncanny Page Builder page', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Create a new Uncanny Page Builder draft from an exported page package.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Import/Export', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageSettings(): array
    {
        return [
            'label' => _x('Settings', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Open WordPress settings for this Page Builder page.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Settings', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Open WordPress settings for this Page Builder page.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Current page', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageCodeEditor(): array
    {
        return [
            'label' => _x('Code editor', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Open this page in the WordPress editor to access code view.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Code editor', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Open this page in the WordPress editor to access code view.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Current page', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageSwitchToWordPress(): array
    {
        return [
            'label' => _x('Switch to WordPress editor', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Stop rendering Page Builder sections and restore any saved WordPress content.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Switch to WordPress editor', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Restore WordPress editing while keeping your Page Builder work saved.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Current page', 'Page Builder', 'uncanny-automator'),
                'confirm_message' => _x('Uncanny Page Builder sections will stop rendering, and the saved WordPress content will be restored. Your Page Builder work remains saved and can be resumed later. Page editing will return to the standard WordPress editor with its normal role-based permissions.', 'Page Builder', 'uncanny-automator'),
                'confirm_primary_message' => _x('Uncanny Page Builder sections will stop rendering, and the saved WordPress content will be restored.', 'Page Builder', 'uncanny-automator'),
                'confirm_secondary_message' => _x('Your Page Builder work remains saved and can be resumed later.', 'Page Builder', 'uncanny-automator'),
                'confirm_permissions_message' => _x('Page editing will return to the standard WordPress editor with its normal role-based permissions.', 'Page Builder', 'uncanny-automator'),
                'confirm_stop_emphasis' => _x('stop rendering', 'Page Builder', 'uncanny-automator'),
                'confirm_restore_emphasis' => _x('restored', 'Page Builder', 'uncanny-automator'),
                'confirm_resume_emphasis' => _x('remains saved', 'Page Builder', 'uncanny-automator'),
                'confirm_permissions_emphasis' => _x('role-based permissions', 'Page Builder', 'uncanny-automator'),
                'confirm_label' => _x('Switch to WordPress', 'Page Builder', 'uncanny-automator'),
                'cancel_label' => _x('Cancel', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageAdminReusables(): array
    {
        return [
            'label' => _x('Reusable parts', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Manage reusable parts used across your site.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Reusable parts', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Manage reusable parts used across your site.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Global settings', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageAdminSettings(): array
    {
        return [
            'label' => _x('Settings', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Open shared Page Builder settings.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Settings', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Manage shared Page Builder settings.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Global settings', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageDashboard(): array
    {
        return [
            'label' => _x('Go back to WordPress dashboard', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Return to the main WordPress dashboard.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Go back to WordPress dashboard', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Return to the main WordPress dashboard.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Quick links', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageAutomatorDashboard(): array
    {
        return [
            'label' => _x('Automator dashboard', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Open the Uncanny Automator dashboard.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('Automator dashboard', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Open the Uncanny Automator dashboard.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Quick links', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, presentation: array<string, mixed>}
     */
    public function pageAutomatorNewRecipe(): array
    {
        return [
            'label' => _x('New Automator recipe', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Create a new Uncanny Automator recipe.', 'Page Builder', 'uncanny-automator'),
            'presentation' => [
                'menu_label' => _x('New Automator recipe', 'Page Builder', 'uncanny-automator'),
                'menu_helper' => _x('Create a new Uncanny Automator recipe.', 'Page Builder', 'uncanny-automator'),
                'menu_group_label' => _x('Quick links', 'Page Builder', 'uncanny-automator'),
            ],
        ];
    }
}
