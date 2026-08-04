<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18;

use UncannyPageBuilder\Infrastructure\i18\strings\CanvasCommandBarStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\DeleteConfirmationModalStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\DesignTargetDeleteDialogStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\EmptyCanvasStateStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\EditorLockStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\ErrorToastStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\EditorClientAppStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\GlobalPartModalStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\InlineEditingToolbarStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\InlineEditingSurfaceStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\PolishSelectionModalStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\RequestProgressStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\ReusablePickerStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\SaveIndicatorStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\ShellModeModalStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelAttributesStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelBackgroundStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelBorderStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelComponentStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelContentStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelDesignColorControlStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelEffectsStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelHelperStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelLayoutStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelShadowStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelTextStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelTokenStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\WorkspaceTabPanelTypographyStrings;

final class PageBuilderJsStrings
{
    public function __construct(
        private readonly ?CanvasCommandBarStrings $canvasCommandBarStrings = null,
        private readonly ?DeleteConfirmationModalStrings $deleteConfirmationModalStrings = null,
        private readonly ?DesignTargetDeleteDialogStrings $designTargetDeleteDialogStrings = null,
        private readonly ?EmptyCanvasStateStrings $emptyCanvasStateStrings = null,
        private readonly ?EditorLockStrings $editorLockStrings = null,
        private readonly ?ErrorToastStrings $errorToastStrings = null,
        private readonly ?EditorClientAppStrings $editorClientAppStrings = null,
        private readonly ?GlobalPartModalStrings $globalPartModalStrings = null,
        private readonly ?InlineEditingSurfaceStrings $inlineEditingSurfaceStrings = null,
        private readonly ?InlineEditingToolbarStrings $inlineEditingToolbarStrings = null,
        private readonly ?PolishSelectionModalStrings $polishSelectionModalStrings = null,
        private readonly ?ReusablePickerStrings $reusablePickerStrings = null,
        private readonly ?RequestProgressStrings $requestProgressStrings = null,
        private readonly ?SaveIndicatorStrings $saveIndicatorStrings = null,
        private readonly ?ShellModeModalStrings $shellModeModalStrings = null,
        private readonly ?WorkspaceTabPanelAttributesStrings $workspaceTabPanelAttributesStrings = null,
        private readonly ?WorkspaceTabPanelBackgroundStrings $workspaceTabPanelBackgroundStrings = null,
        private readonly ?WorkspaceTabPanelBorderStrings $workspaceTabPanelBorderStrings = null,
        private readonly ?WorkspaceTabPanelComponentStrings $workspaceTabPanelComponentStrings = null,
        private readonly ?WorkspaceTabPanelContentStrings $workspaceTabPanelContentStrings = null,
        private readonly ?WorkspaceTabPanelDesignColorControlStrings $workspaceTabPanelDesignColorControlStrings = null,
        private readonly ?WorkspaceTabPanelEffectsStrings $workspaceTabPanelEffectsStrings = null,
        private readonly ?WorkspaceTabPanelHelperStrings $workspaceTabPanelHelperStrings = null,
        private readonly ?WorkspaceTabPanelLayoutStrings $workspaceTabPanelLayoutStrings = null,
        private readonly ?WorkspaceTabPanelShadowStrings $workspaceTabPanelShadowStrings = null,
        private readonly ?WorkspaceTabPanelTextStrings $workspaceTabPanelTextStrings = null,
        private readonly ?WorkspaceTabPanelTokenStrings $workspaceTabPanelTokenStrings = null,
        private readonly ?WorkspaceTabPanelTypographyStrings $workspaceTabPanelTypographyStrings = null,
    ) {
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bridgePayload(): array
    {
        return [
            'canvas.command_bar' => ($this->canvasCommandBarStrings ?? new CanvasCommandBarStrings())->toArray(),
            'canvas.delete_confirmation_modal' => ($this->deleteConfirmationModalStrings ?? new DeleteConfirmationModalStrings())->toArray(),
            'canvas.design_target_delete_dialog' => ($this->designTargetDeleteDialogStrings ?? new DesignTargetDeleteDialogStrings())->toArray(),
            'canvas.empty_state' => ($this->emptyCanvasStateStrings ?? new EmptyCanvasStateStrings())->toArray(),
            'canvas.editor_lock' => ($this->editorLockStrings ?? new EditorLockStrings())->toArray(),
            'canvas.error_toast' => ($this->errorToastStrings ?? new ErrorToastStrings())->toArray(),
            'canvas.editor_client_app' => ($this->editorClientAppStrings ?? new EditorClientAppStrings())->toArray(),
            'canvas.global_part_modal' => ($this->globalPartModalStrings ?? new GlobalPartModalStrings())->toArray(),
            'canvas.inline_editing.surface' => ($this->inlineEditingSurfaceStrings ?? new InlineEditingSurfaceStrings())->toArray(),
            'canvas.inline_editing.toolbar' => ($this->inlineEditingToolbarStrings ?? new InlineEditingToolbarStrings())->toArray(),
            'canvas.polish_selection_modal' => ($this->polishSelectionModalStrings ?? new PolishSelectionModalStrings())->toArray(),
            'canvas.reusable_picker' => ($this->reusablePickerStrings ?? new ReusablePickerStrings())->toArray(),
            'canvas.request_progress' => ($this->requestProgressStrings ?? new RequestProgressStrings())->toArray(),
            'canvas.save_indicator' => ($this->saveIndicatorStrings ?? new SaveIndicatorStrings())->toArray(),
            'canvas.shell_mode_modal' => ($this->shellModeModalStrings ?? new ShellModeModalStrings())->toArray(),
            'canvas.workspace_tab_panel.attributes_panel' => ($this->workspaceTabPanelAttributesStrings ?? new WorkspaceTabPanelAttributesStrings())->toArray(),
            'canvas.workspace_tab_panel.background_panel' => ($this->workspaceTabPanelBackgroundStrings ?? new WorkspaceTabPanelBackgroundStrings())->toArray(),
            'canvas.workspace_tab_panel.border_panel' => ($this->workspaceTabPanelBorderStrings ?? new WorkspaceTabPanelBorderStrings())->toArray(),
            'canvas.workspace_tab_panel.component' => ($this->workspaceTabPanelComponentStrings ?? new WorkspaceTabPanelComponentStrings())->toArray(),
            'canvas.workspace_tab_panel.content_panel' => ($this->workspaceTabPanelContentStrings ?? new WorkspaceTabPanelContentStrings())->toArray(),
            'canvas.workspace_tab_panel.design_color_control' => ($this->workspaceTabPanelDesignColorControlStrings ?? new WorkspaceTabPanelDesignColorControlStrings())->toArray(),
            'canvas.workspace_tab_panel.effects_panel' => ($this->workspaceTabPanelEffectsStrings ?? new WorkspaceTabPanelEffectsStrings())->toArray(),
            'canvas.workspace_tab_panel.helpers' => ($this->workspaceTabPanelHelperStrings ?? new WorkspaceTabPanelHelperStrings())->toArray(),
            'canvas.workspace_tab_panel.layout_panel' => ($this->workspaceTabPanelLayoutStrings ?? new WorkspaceTabPanelLayoutStrings())->toArray(),
            'canvas.workspace_tab_panel.shadow_panel' => ($this->workspaceTabPanelShadowStrings ?? new WorkspaceTabPanelShadowStrings())->toArray(),
            'canvas.workspace_tab_panel.text_panel' => ($this->workspaceTabPanelTextStrings ?? new WorkspaceTabPanelTextStrings())->toArray(),
            'canvas.workspace_tab_panel.token_panel' => ($this->workspaceTabPanelTokenStrings ?? new WorkspaceTabPanelTokenStrings())->toArray(),
            'canvas.workspace_tab_panel.typography_panel' => ($this->workspaceTabPanelTypographyStrings ?? new WorkspaceTabPanelTypographyStrings())->toArray(),
        ];
    }
}
