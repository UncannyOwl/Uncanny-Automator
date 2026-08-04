<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Resolvers;

use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\Controls\ControlContext;
use UncannyPageBuilder\Application\Controls\ControlDefinition;
use UncannyPageBuilder\Application\Controls\ControlState;
use UncannyPageBuilder\Application\Controls\ControlStateResolverInterface;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Controls\PageTrashUrlPortInterface;
use UncannyPageBuilder\Application\History\OperationHistoryService;
use UncannyPageBuilder\Application\Publishing\PageLiveStateReaderInterface;
use UncannyPageBuilder\Domain\Publishing\PageLiveState;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Infrastructure\WordPress\AdminCanvasEditorWindowedPage;
use UncannyPageBuilder\Infrastructure\WordPress\AdminCanvasPage;
use UncannyPageBuilder\Infrastructure\WordPress\PageOwnershipActions;

final class CommandBarControlStateResolver implements ControlStateResolverInterface
{
    public function __construct(
        private readonly ?OperationHistoryService $history = null,
        private readonly ?GlobalPartRepositoryInterface $globalPartRepository = null,
        private readonly ?PageDetailsPortInterface $pageDetails = null,
        private readonly ?PageTrashUrlPortInterface $pageTrashUrl = null,
        private readonly ?PageLiveStateReaderInterface $liveState = null,
        private readonly ?PageStateRepositoryInterface $pageStates = null,
        private readonly ?PageBuilderAvailabilityInterface $availability = null,
    ) {}

    public function resolve(ControlContext $context, ControlDefinition $definition): ControlState
    {
        $state = ControlState::defaults($definition);
        $isGlobalPart = $context->scope() === 'global_part' && $context->globalPartId() > 0;
        // Reusables carry their status on the global-part post, pages on the page post.
        $statusPostId = $isGlobalPart ? $context->globalPartId() : $context->pageId();
        $status = $statusPostId > 0 ? $this->postStatus($statusPostId) : 'draft';
        $publication = !$isGlobalPart && $context->pageId() > 0
            ? $this->liveState?->forPage($context->pageId()) ?? PageLiveState::Draft
            : null;
        $hasPublishedArtifact = !$isGlobalPart
            && $context->pageId() > 0
            && $this->pageStates?->findForPage($context->pageId())?->isPublished() === true;
        $automatorAvailable = $this->automatorAvailable();

        return match ($definition->id()) {
            'history.undo' => $state->withPatch([
                'visible' => $context->pageId() > 0,
                'enabled' => $this->history instanceof OperationHistoryService
                    && $context->canEdit()
                    && $context->pageId() > 0
                    && $this->history->canUndo(OperationHistoryService::SCOPE_PAGE, $context->pageId()),
            ]),
            'history.redo' => $state->withPatch([
                'visible' => $context->pageId() > 0,
                'enabled' => $this->history instanceof OperationHistoryService
                    && $context->canEdit()
                    && $context->pageId() > 0
                    && $this->history->canRedo(OperationHistoryService::SCOPE_PAGE, $context->pageId()),
            ]),
            'page.title' => $state->withPatch([
                'label'   => $isGlobalPart ? 'Reusable title' : $definition->label(),
                'description' => $isGlobalPart ? 'Update the title for this reusable.' : $definition->description(),
                'value'   => $context->pageId() > 0
                    ? $this->draftPageTitle($context->pageId())
                    : ($isGlobalPart ? $this->postTitle($context->globalPartId(), false) : ''),
                'enabled' => ($context->pageId() > 0 || $isGlobalPart) && $context->canEdit(),
                'visible' => $context->pageId() > 0 || $isGlobalPart,
                'reason'  => ($context->pageId() > 0 || $isGlobalPart) && ! $context->canEdit()
                    ? ($isGlobalPart
                        ? 'You do not have permission to edit this reusable.'
                        : 'You do not have permission to edit this page.')
                    : null,
            ]),
            'page.details' => $this->pageDetailsState($state, $context, $isGlobalPart),
            'page.canvas_type' => $state->withPatch([
                'value'   => $isGlobalPart ? $this->globalPartCanvasTypeLabel($context->globalPartId()) : 'Page',
                'visible' => true,
            ]),
            'page.status' => $state->withPatch([
                'value'   => $isGlobalPart ? $status : ($status === 'publish' ? 'Published' : 'Draft'),
                'enabled' => false,
                'visible' => $statusPostId > 0,
            ]),
            // Reusables are always-published internal artifacts, so the
            // draft/publish lifecycle controls only apply to pages.
            'page.save_draft' => $state->withPatch([
                // Authorization and usefulness are intentionally separate.
                // The browser knows whether Manual edits exist; the server
                // remains the authority on whether this user may save them.
                'permitted' => ! $isGlobalPart && $context->pageId() > 0 && $context->canEdit(),
                'enabled' => false,
                'visible' => ! $isGlobalPart && $context->pageId() > 0,
                'reason'  => $context->canEdit() ? null : 'You do not have permission to edit this page.',
            ]),
            // Reusables are always published, so "Save published" is their single
            // commit action — surfaced as "Update".
            'page.save_published' => $isGlobalPart
                ? $state->withPatch([
                    'label'   => 'Update',
                    'permitted' => $context->canEdit(),
                    'enabled' => $context->canEdit(),
                    'visible' => true,
                    'reason'  => $context->canEdit() ? null : 'You do not have permission to edit this reusable.',
                ])
                : $state->withPatch([
                    'permitted' => $context->canEdit() && $context->canPublish(),
                    'enabled' => $context->canEdit()
                        && $context->canPublish()
                        && $publication === PageLiveState::ChangesNotLive,
                    // Keep the content-action area stable after publication.
                    // A clean artifact shows a disabled Publish changes button
                    // instead of making the primary action and overflow vanish.
                    'visible' => $context->pageId() > 0 && $publication !== PageLiveState::Draft,
                    'label' => 'Publish changes',
                    'reason'  => ! $context->canEdit() || ! $context->canPublish()
                        ? 'You do not have permission to publish this page.'
                        : ($publication === PageLiveState::Live
                            ? 'There are no saved draft changes to publish.'
                            : null),
                ]),
            'page.make_live' => $state->withPatch([
                'permitted' => $context->canEdit() && $context->canPublish(),
                'enabled' => $context->canEdit() && $context->canPublish(),
                'visible' => !$isGlobalPart
                    && $context->pageId() > 0
                    && $status !== 'publish'
                    && $hasPublishedArtifact,
                'reason' => $context->canEdit() && $context->canPublish()
                    ? null
                    : 'You do not have permission to publish this page.',
            ]),
            'page.switch_to_draft' => $state->withPatch([
                'permitted' => $context->canEdit(),
                'enabled' => $context->canEdit(),
                'visible' => !$isGlobalPart && $context->pageId() > 0 && $status === 'publish',
                'reason'  => $context->canEdit() ? null : 'You do not have permission to edit this page.',
            ]),
            'page.publish' => $state->withPatch([
                'permitted' => $context->canEdit() && $context->canPublish(),
                'enabled' => $context->canEdit() && $context->canPublish(),
                'visible' => !$isGlobalPart && $context->pageId() > 0 && $publication === PageLiveState::Draft,
                'reason'  => $context->canEdit() && $context->canPublish()
                    ? null
                    : 'You do not have permission to publish this page.',
            ]),
            'shell.mode.open' => $isGlobalPart
                ? $this->hiddenPageOnlyState($state, 'Layout is not available when editing a reusable.')
                : $state,
            'page.new_canvas' => !$this->allowsNewPages()
                ? $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason' => 'Uncanny Page Builder is disabled in Automator settings.',
                ])
                : ($context->canEdit()
                ? ($isGlobalPart
                    ? $this->hiddenPageOnlyState($state, 'New pages are not available when editing a reusable.')
                    : $state->withPatch(['value' => $this->newCanvasUrl()]))
                : $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason'  => 'You do not have permission to create pages.',
                ])),
            'page.full_screen_mode' => $this->fullScreenModeState($state, $context, $isGlobalPart),
            'page.exit_full_screen_mode' => $this->exitFullScreenModeState($state, $context, $isGlobalPart),
            'page.settings' => $context->pageId() > 0 && $context->canEdit() && !$isGlobalPart
                ? $state->withPatch(['value' => $this->postEditUrl($context->pageId())])
                : $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason'  => $isGlobalPart
                        ? 'Page settings are not available when editing a reusable.'
                        : 'You do not have permission to edit this page.',
                ]),
            'page.code_editor' => $context->pageId() > 0 && $context->canEdit() && !$isGlobalPart
                ? $state->withPatch(['value' => $this->postEditUrl($context->pageId())])
                : $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason'  => $isGlobalPart
                        ? 'Code editor is not available when editing a reusable.'
                        : 'You do not have permission to edit this page.',
                ]),
            'page.switch_to_wordpress' => $context->pageId() > 0 && $context->canEdit() && !$isGlobalPart
                ? $state->withPatch(['value' => $this->switchToWordPressUrl($context->pageId())])
                : $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason'  => $isGlobalPart
                        ? 'Switching to WordPress is not available when editing a reusable.'
                        : 'You do not have permission to edit this page.',
                ]),
            'page.trash' => $this->pageTrashState($state, $context, $isGlobalPart),
            'page.admin_reusables' => $context->canEdit() && !$isGlobalPart
                ? $state->withPatch(['value' => $this->reusablesUrl()])
                : $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason'  => $isGlobalPart
                        ? 'Reusable navigation is hidden when editing a reusable.'
                        : 'You do not have permission to view reusables.',
                ]),
            'page.admin_settings' => $context->canEdit() && !$isGlobalPart
                ? $state->withPatch(['value' => $this->pageBuilderSettingsUrl()])
                : $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason'  => $isGlobalPart
                        ? 'Settings navigation is hidden when editing a reusable.'
                        : 'You do not have permission to access settings.',
                ]),
            'page.dashboard' => $context->canEdit() && !$isGlobalPart
                ? $state->withPatch(['value' => $this->wpDashboardUrl()])
                : $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason'  => $isGlobalPart
                        ? 'Dashboard navigation is hidden when editing a reusable.'
                        : 'Dashboard navigation is unavailable in this context.',
                ]),
            'page.automator_dashboard' => $context->canEdit() && !$isGlobalPart && $automatorAvailable
                ? $state->withPatch(['value' => $this->automatorDashboardUrl()])
                : $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason'  => $isGlobalPart
                        ? 'Automator dashboard is hidden when editing a reusable.'
                        : ($automatorAvailable
                            ? 'You do not have permission to access Automator.'
                            : 'Automator is not active on this site.'),
                ]),
            'page.automator_new_recipe' => $context->canEdit() && !$isGlobalPart && $automatorAvailable
                ? $state->withPatch(['value' => $this->newAutomatorRecipeUrl()])
                : $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason'  => $isGlobalPart
                        ? 'Automator recipe creation is hidden when editing a reusable.'
                        : ($automatorAvailable
                            ? 'You do not have permission to create Automator recipes.'
                            : 'Automator is not active on this site.'),
                ]),
            'viewport.mode' => $state->withPatch([
                'value'   => 'desktop',
                'options' => [
                    ['value' => 'desktop', 'label' => 'Desktop', 'icon' => 'desktop', 'width' => null],
                    ['value' => 'tablet', 'label' => 'Tablet', 'icon' => 'tablet', 'width' => 768],
                    ['value' => 'mobile', 'label' => 'Mobile', 'icon' => 'mobile', 'width' => 375],
                ],
            ]),
            'page.preview' => $isGlobalPart
                ? $this->hiddenPageOnlyState($state, 'Preview is not available when editing a reusable.')
                : $this->pagePreviewState($state, $context, $publication),
            'page.static_export' => $isGlobalPart
                ? $this->hiddenPageOnlyState($state, 'Static export is not available when editing a reusable.')
                : $state,
            'page.source_import' => !$this->allowsNewPages()
                ? $state->withPatch([
                    'enabled' => false,
                    'visible' => false,
                    'reason' => 'Uncanny Page Builder is disabled in Automator settings.',
                ])
                : ($isGlobalPart
                ? $this->hiddenPageOnlyState($state, 'Page import is not available when editing a reusable.')
                : $state->withPatch([
                    'enabled' => $context->canManage(),
                    'reason' => $context->canManage()
                        ? null
                        : 'You do not have permission to import Page Builder pages.',
                ])),
            'page.source_export' => $isGlobalPart
                ? $this->hiddenPageOnlyState($state, 'Page export is not available when editing a reusable.')
                : $state,
            default => $state,
        };
    }

    private function pagePreviewState(ControlState $state, ControlContext $context, ?PageLiveState $publication): ControlState
    {
        $details = $context->pageId() > 0 ? $this->pageDetails?->find($context->pageId()) : null;
        if ($details === null) {
            return $state;
        }

        $isLive = $publication !== null && $publication !== PageLiveState::Draft;
        $publicUrl = $isLive ? get_permalink($context->pageId()) : '';

        return $state->withPatch([
            'label' => $isLive ? 'View live' : 'Preview',
            'description' => $isLive ? 'View the visitor-facing page in a new tab.' : 'Preview the working draft in a new tab.',
            // Draft slug edits must not make "View live" point at a URL that
            // has not been published. WordPress public fields move only with
            // the exact artifact pointer, so its permalink is authoritative.
            'value' => $isLive && is_string($publicUrl) && $publicUrl !== ''
                ? $publicUrl
                : $details->previewUrl(),
        ]);
    }

    private function hiddenPageOnlyState(ControlState $state, string $reason): ControlState
    {
        return $state->withPatch([
            'enabled' => false,
            'visible' => false,
            'reason'  => $reason,
        ]);
    }

    private function pageTrashState(ControlState $state, ControlContext $context, bool $isGlobalPart): ControlState
    {
        if ($isGlobalPart || $context->pageId() <= 0) {
            return $this->hiddenPageOnlyState($state, 'Trash is only available for pages.');
        }

        $url = $this->pageTrashUrl?->forPage($context->pageId());
        if ($url === null) {
            return $state->withPatch([
                'enabled' => false,
                'visible' => false,
                'reason' => 'You do not have permission to move this page to the Trash.',
            ]);
        }

        return $state->withPatch(['value' => $url]);
    }

    private function pageDetailsState(ControlState $state, ControlContext $context, bool $isGlobalPart): ControlState
    {
        if ($isGlobalPart || $context->pageId() <= 0) {
            return $state->withPatch([
                'enabled' => false,
                'visible' => false,
                'reason' => 'Page details are only available for pages.',
            ]);
        }

        $details = $this->pageDetails?->find($context->pageId());
        if ($details === null) {
            return $state->withPatch([
                'enabled' => false,
                'visible' => false,
                'reason' => 'Page details are unavailable.',
            ]);
        }

        return $state->withPatch([
            'value' => $details->toArray(),
            'enabled' => $context->canEdit(),
            'visible' => true,
            'reason' => $context->canEdit()
                ? null
                : 'You do not have permission to edit this page.',
        ]);
    }

    private function postTitle(int $postId, bool $isPage): string
    {
        $title = get_the_title($postId);

        if (!is_string($title) || $title === '') {
            return $isPage
                ? sprintf(
                    /* translators: %d is the WordPress page/post ID. */
                    _x('Untitled page #%d', 'Page Builder', 'uncanny-automator'),
                    $postId
                )
                : 'Untitled';
        }

        // WordPress prepares post titles for HTML output. Control state is
        // serialized as JSON and rendered with textContent, so keep raw text
        // characters here instead of HTML entities like &#8217;.
        return html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    }

    private function draftPageTitle(int $pageId): string
    {
        $details = $this->pageDetails?->find($pageId);
        if ($details !== null) {
            return $details->title();
        }

        return $this->postTitle($pageId, true);
    }

    private function postStatus(int $postId): string
    {
        $status = get_post_status($postId);

        return is_string($status) && $status !== '' ? $status : 'draft';
    }

    private function fullScreenModeState(ControlState $state, ControlContext $context, bool $isGlobalPart): ControlState
    {
        // Reusables edit in the windowed canvas only; no full-screen toggle.
        if ($isGlobalPart) {
            return $this->hiddenPageOnlyState($state, 'Full screen mode is not available when editing a reusable.');
        }

        if ($context->pageId() <= 0 || !$context->canEdit()) {
            return $state->withPatch([
                'enabled' => false,
                'visible' => false,
                'reason'  => 'You do not have permission to edit this page.',
            ]);
        }

        return $state->withPatch([
            'value' => add_query_arg('full_screen_mode', '1', AdminCanvasPage::editorUrl($context->pageId())),
        ]);
    }

    private function exitFullScreenModeState(ControlState $state, ControlContext $context, bool $isGlobalPart): ControlState
    {
        // Reusables edit in the windowed canvas only; no full-screen toggle.
        if ($isGlobalPart) {
            return $this->hiddenPageOnlyState($state, 'Full screen mode is not available when editing a reusable.');
        }

        if ($context->pageId() <= 0 || !$context->canEdit()) {
            return $state->withPatch([
                'enabled' => false,
                'visible' => false,
                'reason'  => 'You do not have permission to edit this page.',
            ]);
        }

        return $state->withPatch([
            'value' => AdminCanvasEditorWindowedPage::editorUrl($context->pageId()),
        ]);
    }

    private function reusablesUrl(): string
    {
        return admin_url('edit.php?post_type=upb_global_part');
    }

    private function pageBuilderSettingsUrl(): string
    {
        return admin_url('admin.php?page=uncanny-page-builder-settings');
    }

    private function wpDashboardUrl(): string
    {
        return admin_url('index.php');
    }

    private function automatorDashboardUrl(): string
    {
        return admin_url('edit.php?post_type=uo-recipe&page=uncanny-automator-dashboard');
    }

    private function newAutomatorRecipeUrl(): string
    {
        return admin_url('post-new.php?post_type=uo-recipe');
    }

    private function automatorAvailable(): bool
    {
        return function_exists('post_type_exists') && post_type_exists('uo-recipe');
    }

    private function allowsNewPages(): bool
    {
        return $this->availability?->allowsNewPages() ?? true;
    }

    private function newCanvasUrl(): string
    {
        $url = admin_url('admin-post.php?action=uncanny_page_builder_create_page');

        // This value is serialized as control-plane JSON. wp_nonce_url() is
        // intended for escaped HTML output and may encode & as &amp;.
        return $url
            . (str_contains($url, '?') ? '&' : '?')
            . '_wpnonce='
            . rawurlencode((string) wp_create_nonce('uncanny_page_builder_create_page'));
    }

    private function postEditUrl(int $postId): string
    {
        return admin_url('post.php?post=' . $postId . '&action=edit');
    }

    private function switchToWordPressUrl(int $postId): string
    {
        $url = admin_url(
            'admin-post.php?action=' . PageOwnershipActions::ACTION . '&page_id=' . $postId,
        );

        // This value is serialized as control-plane JSON. Build the nonce URL
        // directly so ampersands are not HTML-encoded by wp_nonce_url().
        return $url
            . (str_contains($url, '?') ? '&' : '?')
            . '_wpnonce='
            . rawurlencode((string) wp_create_nonce(PageOwnershipActions::NONCE_ACTION));
    }

    private function globalPartCanvasTypeLabel(int $globalPartId): string
    {
        $type = $this->globalPartTypeValue($globalPartId);

        return 'Reusable ' . $type;
    }

    private function globalPartTypeValue(int $globalPartId): string
    {
        if (!$this->globalPartRepository instanceof GlobalPartRepositoryInterface || $globalPartId <= 0) {
            return GlobalPartType::Section->value;
        }

        $part = $this->globalPartRepository->findById($globalPartId);
        $rawType = is_array($part) ? (string) ($part['type'] ?? '') : '';

        return GlobalPartType::fromString($rawType)->value;
    }
}
