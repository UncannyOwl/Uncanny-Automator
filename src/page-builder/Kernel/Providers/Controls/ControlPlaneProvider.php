<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\Controls;

use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\Canvas\CanvasRefreshRendererInterface;
use UncannyPageBuilder\Application\Controls\ControlDefinition;
use UncannyPageBuilder\Application\Controls\ControlDispatcher;
use UncannyPageBuilder\Application\Controls\ControlRegistry;
use UncannyPageBuilder\Application\Controls\ControlStateService;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Controls\PageTrashUrlPortInterface;
use UncannyPageBuilder\Application\Controls\Handlers\DesignStyleCommitHandler;
use UncannyPageBuilder\Application\Controls\Handlers\HistoryRedoHandler;
use UncannyPageBuilder\Application\Controls\Handlers\HistoryUndoHandler;
use UncannyPageBuilder\Application\Controls\Handlers\ManualChangeSetHandler;
use UncannyPageBuilder\Application\History\HistoryOperationRestorer;
use UncannyPageBuilder\Application\Controls\Handlers\PageDetailsHandler;
use UncannyPageBuilder\Application\Controls\Handlers\PageSourceExportHandler;
use UncannyPageBuilder\Application\Controls\Handlers\PageStaticExportHandler;
use UncannyPageBuilder\Application\Controls\Handlers\PageResumeDraftHandler;
use UncannyPageBuilder\Application\Controls\Handlers\PageStatusHandler;
use UncannyPageBuilder\Application\Controls\Handlers\PageTitleHandler;
use UncannyPageBuilder\Application\Controls\Handlers\SectionDeleteHandler;
use UncannyPageBuilder\Application\Controls\Handlers\SectionEditableUpdateHandler;
use UncannyPageBuilder\Application\Controls\Handlers\SectionNodeUpdateHandler;
use UncannyPageBuilder\Application\Controls\Handlers\SectionReorderHandler;
use UncannyPageBuilder\Application\Controls\Handlers\SectionRewriteSourceHandler;
use UncannyPageBuilder\Application\Controls\Handlers\SectionSaveAsReusableHandler;
use UncannyPageBuilder\Application\Controls\Resolvers\CommandBarControlStateResolver;
use UncannyPageBuilder\Application\Controls\Resolvers\SectionActionControlStateResolver;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\DesignStyles\DesignStyleCommitService;
use UncannyPageBuilder\Application\DesignStyles\ElementStyleCommitter;
use UncannyPageBuilder\Application\DesignStyles\GlobalPartElementStyleCommitter;
use UncannyPageBuilder\Application\DesignStyles\InlineTypographyMigrator;
use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Editor\RestorePublishedSourceToWorkingDraft;
use UncannyPageBuilder\Application\Editing\EditableUpdateService;
use UncannyPageBuilder\Application\Editing\GlobalPartEditableUpdateService;
use UncannyPageBuilder\Application\Editing\GlobalPartNodeUpdateService;
use UncannyPageBuilder\Application\Editing\SectionNodeHtmlMutator;
use UncannyPageBuilder\Application\Editing\SectionNodeUpdateService;
use UncannyPageBuilder\Application\Export\StaticPageExportService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\PageGlobalPartSelectionService;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\History\OperationHistoryService;
use UncannyPageBuilder\Application\Publishing\PublishPage;
use UncannyPageBuilder\Application\Publishing\PageLiveStateReaderInterface;
use UncannyPageBuilder\Application\Publishing\SwitchPageToDraftInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\Reusable\ReusablePortInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveArtifactStoreInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveDownloadUrlInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveService;
use UncannyPageBuilder\Domain\Controls\CanvasArea;
use UncannyPageBuilder\Domain\Controls\ControlType;
use UncannyPageBuilder\Domain\Controls\ControlZone;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseGlobalPartRepository;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Infrastructure\DesignStyles\SourceInlineStylePatcher;
use UncannyPageBuilder\Infrastructure\i18\strings\CanvasHiddenControlStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\DeleteConfirmationModalPresentationStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\CanvasSectionActionControlStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\CanvasVisibleControlStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\GlobalPartModalPresentationStrings;
use UncannyPageBuilder\Infrastructure\i18\strings\InlineEditingPresentationStrings;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;

final class ControlPlaneProvider implements ServiceProviderInterface
{
    private bool $controlsRegistered = false;

    public function register(Container $container): void
    {
        $container->factory(ControlRegistry::class, static fn (): ControlRegistry => new ControlRegistry());

        $container->factory(CommandBarControlStateResolver::class, static function (Container $c): CommandBarControlStateResolver {
            return new CommandBarControlStateResolver(
                $c->typed(OperationHistoryService::class),
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(PageDetailsPortInterface::class),
                $c->typed(PageTrashUrlPortInterface::class),
                $c->typed(PageLiveStateReaderInterface::class),
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(PageBuilderAvailabilityInterface::class),
            );
        });
        $container->factory(SectionActionControlStateResolver::class, static fn (): SectionActionControlStateResolver => new SectionActionControlStateResolver());
        $container->factory(HistoryUndoHandler::class, static function (Container $c): HistoryUndoHandler {
            return new HistoryUndoHandler(
                $c->typed(OperationHistoryService::class),
                $c->typed(CanvasRefreshRendererInterface::class),
                $c->typed(HistoryOperationRestorer::class),
            );
        });
        $container->factory(HistoryRedoHandler::class, static function (Container $c): HistoryRedoHandler {
            return new HistoryRedoHandler(
                $c->typed(OperationHistoryService::class),
                $c->typed(CanvasRefreshRendererInterface::class),
                $c->typed(HistoryOperationRestorer::class),
            );
        });
        $container->factory(HistoryOperationRestorer::class, static function (Container $c): HistoryOperationRestorer {
            return new HistoryOperationRestorer(
                $c->typed(\UncannyPageBuilder\Application\History\SectionHistoryRestorerInterface::class),
                $c->typed(\UncannyPageBuilder\Application\History\PageDetailsHistoryRestorerInterface::class),
            );
        });
        $container->factory(PageTitleHandler::class, static function (Container $c): PageTitleHandler {
            return new PageTitleHandler(
                $c->typed(PageDetailsPortInterface::class),
                $c->typed(ReusablePortInterface::class),
            );
        });
        $container->factory(PageDetailsHandler::class, static function (Container $c): PageDetailsHandler {
            return new PageDetailsHandler(
                $c->typed(PageDetailsPortInterface::class),
            );
        });
        $container->factory(PageStatusHandler::class, static function (Container $c): PageStatusHandler {
            return new PageStatusHandler(
                $c->typed(PublishPage::class),
                $c->typed(SwitchPageToDraftInterface::class),
                $c->typed(\UncannyPageBuilder\Application\Publishing\PageDraftStatusPortInterface::class),
                $c->typed(\UncannyPageBuilder\Application\Publishing\PageLiveStateReaderInterface::class),
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(\UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface::class),
            );
        });
        $container->factory(PageResumeDraftHandler::class, static function (Container $c): PageResumeDraftHandler {
            return new PageResumeDraftHandler(
                $c->typed(\UncannyPageBuilder\Application\Editor\SelectEditorPageSource::class),
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(\UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface::class),
            );
        });
        $container->factory(PageStaticExportHandler::class, static function (Container $c): PageStaticExportHandler {
            return new PageStaticExportHandler(
                $c->typed(StaticPageExportService::class),
                $c->typed(PageDetailsPortInterface::class),
            );
        });
        $container->factory(PageSourceExportHandler::class, static function (Container $c): PageSourceExportHandler {
            return new PageSourceExportHandler(
                $c->typed(PageSourceArchiveDownloadUrlInterface::class),
                $c->typed(PageSourceArchiveService::class),
                $c->typed(PageSourceArchiveArtifactStoreInterface::class),
            );
        });
        $container->factory(SectionEditableUpdateHandler::class, static function (Container $c): SectionEditableUpdateHandler {
            return new SectionEditableUpdateHandler(
                $c->typed(EditableUpdateService::class),
                $c->typed(GlobalPartEditableUpdateService::class),
            );
        });
        $container->factory(SectionNodeUpdateService::class, static function (Container $c): SectionNodeUpdateService {
            return new SectionNodeUpdateService(
                $c->typed(SectionService::class),
                $c->typed(SectionNodeHtmlMutator::class),
            );
        });
        $container->factory(SectionNodeUpdateHandler::class, static function (Container $c): SectionNodeUpdateHandler {
            return new SectionNodeUpdateHandler(
                $c->typed(SectionNodeUpdateService::class),
                $c->typed(GlobalPartNodeUpdateService::class),
            );
        });
        $container->factory(SectionDeleteHandler::class, static function (Container $c): SectionDeleteHandler {
            return new SectionDeleteHandler(
                $c->typed(SectionService::class),
            );
        });
        $container->factory(SectionSaveAsReusableHandler::class, static function (Container $c): SectionSaveAsReusableHandler {
            return new SectionSaveAsReusableHandler(
                $c->typed(GlobalPartService::class),
                $c->typed(SectionService::class),
            );
        });
        $container->factory(SectionReorderHandler::class, static function (Container $c): SectionReorderHandler {
            return new SectionReorderHandler(
                $c->typed(SectionService::class),
            );
        });
        $container->factory(SectionRewriteSourceHandler::class, static function (Container $c): SectionRewriteSourceHandler {
            return new SectionRewriteSourceHandler(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
            );
        });

        $container->factory(SourceInlineStylePatcher::class, static fn (): SourceInlineStylePatcher => new SourceInlineStylePatcher());
        $container->factory(InlineTypographyMigrator::class, static function (Container $c): InlineTypographyMigrator {
            return new InlineTypographyMigrator($c->typed(SourceInlineStylePatcher::class));
        });
        $container->factory(ElementStyleCommitter::class, static function (Container $c): ElementStyleCommitter {
            return new ElementStyleCommitter(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(InlineTypographyMigrator::class),
            );
        });
        $container->factory(GlobalPartElementStyleCommitter::class, static function (Container $c): GlobalPartElementStyleCommitter {
            return new GlobalPartElementStyleCommitter(
                $c->typed(GlobalPartService::class),
                $c->typed(InlineTypographyMigrator::class),
            );
        });
        $container->factory(DesignStyleCommitService::class, static function (Container $c): DesignStyleCommitService {
            return new DesignStyleCommitService(
                $c->typed(DesignStandardsService::class),
                $c->typed(ElementStyleCommitter::class),
                $c->typed(WorkingCanvasRefresherInterface::class),
                $c->typed(GlobalPartElementStyleCommitter::class),
            );
        });
        $container->factory(DesignStyleCommitHandler::class, static function (Container $c): DesignStyleCommitHandler {
            return new DesignStyleCommitHandler(
                $c->typed(DesignStyleCommitService::class),
            );
        });
        $container->factory(ManualChangeSetHandler::class, static function (Container $c): ManualChangeSetHandler {
            return new ManualChangeSetHandler(
                $c->typed(PageSourceMutation::class),
                $c->typed(RestorePublishedSourceToWorkingDraft::class),
                $c->typed(DesignStyleCommitHandler::class),
                $c->typed(SectionEditableUpdateHandler::class),
                $c->typed(SectionNodeUpdateHandler::class),
                $c->typed(SectionRewriteSourceHandler::class),
                $c->typed(SectionService::class),
                $c->typed(PageDetailsPortInterface::class),
                $c->typed(ShellModeService::class),
                $c->typed(PageGlobalPartSelectionService::class),
                $c->typed(PageJavaScriptRuntimeService::class),
                $c->typed(ToolSettingsAccess::class),
                $c->typed(OperationHistoryService::class),
                $c->typed(HistoryOperationRestorer::class),
                $c->typed(PageStateRepositoryInterface::class),
            );
        });

        $container->factory(ControlStateService::class, static function (Container $c): ControlStateService {
            return new ControlStateService(
                $c->typed(ControlRegistry::class),
                $c,
            );
        });

        $container->factory(ControlDispatcher::class, static function (Container $c): ControlDispatcher {
            return new ControlDispatcher(
                $c->typed(ControlRegistry::class),
                $c,
                $c->typed(PermissionChecker::class),
                $c->typed(DatabaseSectionRepository::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        // Control definitions carry translated labels (__()). Register them on
        // `init` so translations are not requested before WordPress is ready for
        // them — WP 6.7+ emits a "translation loaded too early" notice otherwise.
        // The registry is only read during request handling (REST/canvas), which
        // runs well after `init`, so deferring registration is safe.
        add_action('init', function () use ($container): void {
            try {
                $this->registerControls($container);
            } catch (\JsonException | \InvalidArgumentException | \RuntimeException $error) {
                $this->reportRegistrationFailure($error);
            } catch (\Throwable $error) {
                // Control extensions run in WordPress init. Their failure must
                // not terminate requests that do not use the control plane.
                $this->reportRegistrationFailure($error);
            }
        });
    }

    private function reportRegistrationFailure(\Throwable $error): void
    {
        error_log(sprintf(
            '[Uncanny Page Builder] Control registration unavailable (%s).',
            $error::class,
        ));
    }

    public function registerControls(Container $container): void
    {
        if ($this->controlsRegistered) {
            return;
        }

        $registry = $container->typed(ControlRegistry::class);

        foreach ($this->coreControls() as $definition) {
            // A previous init callback can stop after registering only part of
            // the core set. Replay must continue past those valid entries.
            if ($registry->has($definition->id())) {
                continue;
            }
            $registry->registerCore($definition);
        }

        // WordPress owns the hook lifecycle, and third-party code can invoke
        // `init` more than once. Core registration is complete at this point,
        // so replaying this observer would only create duplicate controls.
        $this->controlsRegistered = true;

        do_action('uncanny_page_builder_register_controls', $registry, $container);
    }

    /** @return ControlDefinition[] */
    private function coreControls(): array
    {
        $resolver = CommandBarControlStateResolver::class;
        $sectionResolver = SectionActionControlStateResolver::class;
        $inlineEditingPresentationStrings = new InlineEditingPresentationStrings();
        $globalPartModalPresentationStrings = new GlobalPartModalPresentationStrings();
        $deleteConfirmationModalPresentationStrings = new DeleteConfirmationModalPresentationStrings();
        $canvasVisibleControlStrings = new CanvasVisibleControlStrings();
        $canvasSectionActionControlStrings = new CanvasSectionActionControlStrings();
        $canvasHiddenControlStrings = new CanvasHiddenControlStrings();

        // Order 2: section.create - Create a new section on a page.
        $sectionCreate = ControlDefinition::make([
            ...$this->agentTool('create_section', 'write'),
            'id'          => 'section.create',
            ...$canvasHiddenControlStrings->sectionCreate(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 2,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 2: page.new_canvas - Create a new canvas page.
        $pageNewCanvas = ControlDefinition::make([
            'id'             => 'page.new_canvas',
            ...$canvasVisibleControlStrings->pageNewCanvas(),
            'zone'           => ControlZone::Document,
            'canvas_area'    => CanvasArea::TopBarLeft,
            'order'          => 2,
            'type'           => ControlType::Trigger,
            'icon'           => 'plus',
            'client_hint'    => 'external_url',
            'presentation'   => [
                'icon_only'           => true,
                'icon_style'          => 'inverse_primary',
                'link_style'          => 'primary',
                'button_size'         => 'compact',
                'toolbar_group'       => 'post_viewport',
                'responsive_priority' => 3,
            ],
            'local'          => true,
            'state_resolver' => $resolver,
        ]);

        // Order 5: section.editable.update - Apply an inline editable update to section HTML.
        $sectionEditableUpdate = ControlDefinition::make([
            'id'             => 'section.editable.update',
            ...$canvasHiddenControlStrings->sectionEditableUpdate(),
            'zone'           => ControlZone::Tools,
            'canvas_area'    => CanvasArea::Hidden,
            'order'          => 5,
            'type'           => ControlType::Trigger,
            'client_hint'    => 'contenteditable',
            'presentation'   => $inlineEditingPresentationStrings->toArray(),
            'handler'        => SectionEditableUpdateHandler::class,
            'writes_editor_state' => true,
        ]);

        // Order 6: section.node.update - Apply a Design Lens node update to section HTML.
        $sectionNodeUpdate = ControlDefinition::make([
            'id'          => 'section.node.update',
            ...$canvasHiddenControlStrings->sectionNodeUpdate(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 6,
            'type'        => ControlType::Trigger,
            'handler'     => SectionNodeUpdateHandler::class,
            'writes_editor_state' => true,
            'contexts'    => ['canvas'],
        ]);

        // Order 8: section.rewrite_source - Replace section HTML and CSS.
        $sectionRewriteSource = ControlDefinition::make([
            'id'             => 'section.rewrite_source',
            ...$canvasHiddenControlStrings->sectionRewriteSource(),
            'zone'           => ControlZone::Tools,
            'canvas_area'    => CanvasArea::Hidden,
            'order'          => 8,
            'type'           => ControlType::Trigger,
            'handler'        => SectionRewriteSourceHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $sectionResolver,
            'contexts'       => ['canvas', 'agent'],
        ]);

        // Order 10: design.style.commit - Persist the pending design stack in one application batch.
        $designStyleCommit = ControlDefinition::make([
            'id'          => 'design.style.commit',
            ...$canvasHiddenControlStrings->designStyleCommit(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 10,
            'type'        => ControlType::Trigger,
            'handler'     => DesignStyleCommitHandler::class,
            'writes_editor_state' => true,
            'contexts'    => ['canvas'],
        ]);

        // Order 10: history.undo - Revert the previous editor operation.
        $historyUndo = ControlDefinition::make([
            'id'             => 'history.undo',
            ...$canvasVisibleControlStrings->historyUndo(),
            'zone'           => ControlZone::History,
            'canvas_area'    => CanvasArea::HistoryBar,
            'order'          => 10,
            'type'           => ControlType::Trigger,
            'icon'           => 'undo',
            'keybinding'     => 'meta+z',
            'presentation'   => [
                'enabled_state'       => 'canUndo',
                'icon_only'           => true,
                'responsive_priority' => 2,
            ],
            'handler'        => HistoryUndoHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $resolver,
        ]);

        // Order 10: page.title - Update the private working title.
        $pageTitle = ControlDefinition::make([
            'id'             => 'page.title',
            ...$canvasVisibleControlStrings->pageTitle(),
            'zone'           => ControlZone::Identity,
            'canvas_area'    => CanvasArea::PageIdentity,
            'order'          => 10,
            'type'           => ControlType::Input,
            'client_hint'    => 'contenteditable',
            'handler'        => PageTitleHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $resolver,
        ]);

        // Order 10: viewport.mode - Change the editor preview viewport.
        $viewportMode = ControlDefinition::make([
            'id'             => 'viewport.mode',
            ...$canvasVisibleControlStrings->viewportMode(),
            'zone'           => ControlZone::Viewport,
            'canvas_area'    => CanvasArea::ViewportSwitcher,
            'order'          => 10,
            'type'           => ControlType::Select,
            'icon'           => 'desktop',
            'local'          => true,
            'presentation'   => [
                'responsive_priority' => 2,
                ...$canvasVisibleControlStrings->viewportMode()['presentation'],
            ],
            'state_resolver' => $resolver,
        ]);

        // Order 11: page.details - Update the private working identity together.
        $pageDetails = ControlDefinition::make([
            'id'             => 'page.details',
            ...$canvasVisibleControlStrings->pageDetails(),
            'zone'           => ControlZone::Identity,
            'canvas_area'    => CanvasArea::PageIdentity,
            'order'          => 11,
            'type'           => ControlType::Input,
            'presentation'   => [
                'busy_group' => 'page_meta',
            ],
            'handler'        => PageDetailsHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $resolver,
        ]);

        // Order 11: page.manual_changes.commit - Persist one browser-owned page change set.
        $manualChangesCommit = ControlDefinition::make([
            'id'          => 'page.manual_changes.commit',
            ...$canvasHiddenControlStrings->manualChangesCommit(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 11,
            'type'        => ControlType::Trigger,
            'handler'     => ManualChangeSetHandler::class,
            'writes_editor_state' => true,
            'contexts'    => ['canvas'],
        ]);

        // Order 12: page.resume_draft - Record the human choice to load a parked draft.
        $pageResumeDraft = ControlDefinition::make([
            'id'          => 'page.resume_draft',
            ...$canvasHiddenControlStrings->pageResumeDraft(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 12,
            'type'        => ControlType::Trigger,
            'handler'     => PageResumeDraftHandler::class,
            'writes_editor_state' => true,
            'contexts'    => ['canvas'],
        ]);

        // Order 15: page.canvas_type - Current editor page type.
        $pageCanvasType = ControlDefinition::make([
            'id'             => 'page.canvas_type',
            ...$canvasVisibleControlStrings->pageCanvasType(),
            'zone'           => ControlZone::Identity,
            'canvas_area'    => CanvasArea::PageIdentity,
            'order'          => 15,
            'type'           => ControlType::Display,
            'state_resolver' => $resolver,
        ]);

        // Order 20: history.redo - Reapply the next editor operation.
        $historyRedo = ControlDefinition::make([
            'id'             => 'history.redo',
            ...$canvasVisibleControlStrings->historyRedo(),
            'zone'           => ControlZone::History,
            'canvas_area'    => CanvasArea::HistoryBar,
            'order'          => 20,
            'type'           => ControlType::Trigger,
            'icon'           => 'redo',
            'keybinding'     => 'meta+shift+z',
            'presentation'   => [
                'enabled_state'       => 'canRedo',
                'icon_only'           => true,
                'responsive_priority' => 2,
            ],
            'handler'        => HistoryRedoHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $resolver,
        ]);

        // Order 20: page.status - Current WordPress page status.
        $pageStatus = ControlDefinition::make([
            'id'             => 'page.status',
            ...$canvasVisibleControlStrings->pageStatus(),
            'zone'           => ControlZone::Identity,
            'canvas_area'    => CanvasArea::PageIdentity,
            'order'          => 20,
            'type'           => ControlType::Display,
            'state_resolver' => $resolver,
        ]);

        // Order 20: page.save_draft - Save this page as a draft.
        $pageSaveDraft = ControlDefinition::make([
            'id'             => 'page.save_draft',
            ...$canvasVisibleControlStrings->pageSaveDraft(),
            'zone'           => ControlZone::Document,
            'canvas_area'    => CanvasArea::TopBarRight,
            'order'          => 20,
            'type'           => ControlType::Trigger,
            'default_value'  => 'draft',
            'presentation'   => [
                'busy_group'          => 'page_meta',
                'action_group'        => 'page_status',
                'group_role'          => 'secondary',
                'menu_section'        => 'publish',
                'button_size'         => 'compact',
                'responsive_priority' => 0,
                ...$canvasVisibleControlStrings->pageSaveDraft()['presentation'],
            ],
            'handler'        => PageStatusHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $resolver,
        ]);

        // Order 20: page.save_published - Publish the working draft to an already-live page.
        $pageSavePublished = ControlDefinition::make([
            'id'             => 'page.save_published',
            ...$canvasVisibleControlStrings->pageSavePublished(),
            'zone'           => ControlZone::Document,
            'canvas_area'    => CanvasArea::TopBarRight,
            'order'          => 20,
            'type'           => ControlType::Trigger,
            'default_value'  => 'publish',
            'presentation'   => [
                'busy_group'          => 'page_meta',
                'action_group'        => 'page_status',
                'group_role'          => 'primary',
                'menu_section'        => 'publish',
                'button_size'         => 'compact',
                'responsive_priority' => 0,
                ...$canvasVisibleControlStrings->pageSavePublished()['presentation'],
            ],
            'handler'        => PageStatusHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $resolver,
        ]);

        // Order 24: page.make_live - Change visibility without publishing content.
        $pageMakeLive = ControlDefinition::make([
            'id'             => 'page.make_live',
            ...$canvasVisibleControlStrings->pageMakeLive(),
            'zone'           => ControlZone::Document,
            'canvas_area'    => CanvasArea::TopBarRight,
            'order'          => 24,
            'type'           => ControlType::Trigger,
            'capability'     => 'publish_post',
            'default_value'  => 'publish',
            'presentation'   => [
                'busy_group'   => 'page_visibility',
                'action_group' => 'page_visibility',
                'group_role'   => 'secondary',
                ...$canvasVisibleControlStrings->pageMakeLive()['presentation'],
            ],
            'handler'        => PageStatusHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $resolver,
        ]);

        // Order 25: page.switch_to_draft - Change visibility back to draft.
        $pageSwitchToDraft = ControlDefinition::make([
            'id'             => 'page.switch_to_draft',
            ...$canvasVisibleControlStrings->pageSwitchToDraft(),
            'zone'           => ControlZone::Document,
            'canvas_area'    => CanvasArea::TopBarRight,
            'order'          => 25,
            'type'           => ControlType::Trigger,
            'capability'     => 'publish_post',
            'default_value'  => 'draft',
            'presentation'   => [
                'busy_group'          => 'page_visibility',
                'action_group'        => 'page_status',
                'group_role'          => 'secondary',
                'menu_section'        => 'publish',
                'button_size'         => 'compact',
                'responsive_priority' => 0,
                ...$canvasVisibleControlStrings->pageSwitchToDraft()['presentation'],
            ],
            'handler'        => PageStatusHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $resolver,
        ]);

        // Order 30: page.publish - Publish working content without changing visibility.
        $pagePublish = ControlDefinition::make([
            'id'             => 'page.publish',
            ...$canvasVisibleControlStrings->pagePublish(),
            'zone'           => ControlZone::Document,
            'canvas_area'    => CanvasArea::TopBarRight,
            'order'          => 30,
            'type'           => ControlType::Trigger,
            'variant'        => 'primary',
            'default_value'  => 'publish',
            'presentation'   => [
                'busy_group'          => 'page_meta',
                'action_group'        => 'page_status',
                'group_role'          => 'primary',
                'menu_section'        => 'publish',
                'button_size'         => 'compact',
                'responsive_priority' => 0,
                ...$canvasVisibleControlStrings->pagePublish()['presentation'],
            ],
            'handler'        => PageStatusHandler::class,
            'writes_editor_state' => true,
            'state_resolver' => $resolver,
        ]);

        // Order 35: page.full_screen_mode - Open the standalone canvas editor.
        $pageFullScreenMode = ControlDefinition::make([
            'id'             => 'page.full_screen_mode',
            ...$canvasVisibleControlStrings->pageFullScreenMode(),
            'zone'           => ControlZone::Tools,
            'canvas_area'    => CanvasArea::TopBarRight,
            'order'          => 35,
            'type'           => ControlType::Trigger,
            'client_hint'    => 'external_url',
            'presentation'   => [
                'action_group'        => 'page_status',
                'group_role'          => 'secondary',
                'menu_section'        => 'view',
                'embedded_only'       => true,
                'responsive_priority' => 3,
                ...$canvasVisibleControlStrings->pageFullScreenMode()['presentation'],
            ],
            'local'          => true,
            'state_resolver' => $resolver,
        ]);

        // Order 35: shell.mode.open - Keep page status actions ahead of Layout.
        $shellModeOpen = ControlDefinition::make([
            'id'          => 'shell.mode.open',
            ...$canvasVisibleControlStrings->shellModeOpen(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::TopBarRight,
            'order'       => 35,
            'type'        => ControlType::Trigger,
            'icon'        => 'preformatted',
            'client_hint' => 'open_modal',
            'presentation' => [
                'surface'             => 'modal',
                'component'           => 'shell-mode',
                'icon_only'           => true,
                'responsive_priority' => 3,
            ],
            'state_resolver' => $resolver,
            'local'       => true,
        ]);

        // Order 36: page.exit_full_screen_mode - Return to the windowed canvas editor.
        $pageExitFullScreenMode = ControlDefinition::make([
            'id'             => 'page.exit_full_screen_mode',
            ...$canvasVisibleControlStrings->pageExitFullScreenMode(),
            'zone'           => ControlZone::Tools,
            'canvas_area'    => CanvasArea::TopBarRight,
            'order'          => 36,
            'type'           => ControlType::Trigger,
            'client_hint'    => 'external_url',
            'presentation'   => [
                'action_group'        => 'page_status',
                'group_role'          => 'secondary',
                'menu_section'        => 'view',
                'fullscreen_only'     => true,
                'responsive_priority' => 3,
                ...$canvasVisibleControlStrings->pageExitFullScreenMode()['presentation'],
            ],
            'local'          => true,
            'state_resolver' => $resolver,
        ]);

        // Order 40: page.preview - Preview the page in a new tab.
        $pagePreview = ControlDefinition::make([
            'id'          => 'page.preview',
            ...$canvasVisibleControlStrings->pagePreview(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::TopBarRight,
            'order'       => 40,
            'type'        => ControlType::Trigger,
            'icon'        => 'external-link',
            'client_hint' => 'external_url',
            'presentation' => [
                'fallback_href'       => 'preview',
                'link_style'          => 'tertiary',
                'target'              => '_blank',
                'rel'                 => 'noopener',
                'icon_only'           => true,
                'responsive_priority' => 3,
            ],
            'state_resolver' => $resolver,
            'local'       => true,
        ]);

        // Order 42: page.source_import - Choose a source package and create a new Page Builder draft.
        $pageSourceImport = ControlDefinition::make([
            'id'           => 'page.source_import',
            ...$canvasVisibleControlStrings->pageSourceImport(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 42,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'import_source_package',
            'presentation' => [
                'surface'      => 'admin_header',
                'action_group' => 'page_status',
                'group_role'   => 'secondary',
                'menu_section' => 'import_export',
                ...$canvasVisibleControlStrings->pageSourceImport()['presentation'],
            ],
            'state_resolver' => $resolver,
            'writes_editor_state' => false,
            'contexts'     => ['canvas'],
            'local'        => true,
        ]);

        // Order 43: page.source_export - Download an importable Page Builder source package.
        $pageSourceExport = ControlDefinition::make([
            'id'           => 'page.source_export',
            ...$canvasVisibleControlStrings->pageSourceExport(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 43,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'download_source_package',
            'presentation' => [
                'surface'       => 'admin_header',
                'action_group'  => 'page_status',
                'group_role'    => 'secondary',
                'menu_section'  => 'import_export',
                'download_name' => 'uncanny-page-builder-page-source',
                ...$canvasVisibleControlStrings->pageSourceExport()['presentation'],
            ],
            'state_resolver' => $resolver,
            'handler'      => PageSourceExportHandler::class,
            'writes_editor_state' => false,
            'contexts'     => ['canvas'],
        ]);

        // Order 45: page.static_export - Download a portable HTML version of this page.
        $pageStaticExport = ControlDefinition::make([
            'id'           => 'page.static_export',
            ...$canvasVisibleControlStrings->pageStaticExport(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 45,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'download_artifacts',
            'presentation' => [
                'surface'       => 'admin_header',
                'action_group'  => 'page_status',
                'group_role'    => 'menu',
                'menu_section'  => 'publish',
                'download_name' => 'uncanny-page-builder-static-export',
                ...$canvasVisibleControlStrings->pageStaticExport()['presentation'],
            ],
            'state_resolver' => $resolver,
            'handler'      => PageStaticExportHandler::class,
            'writes_editor_state' => false,
            'contexts'     => ['canvas'],
        ]);

        // Order 46: page.trash - Move the current page to the WordPress Trash.
        $pageTrash = ControlDefinition::make([
            'id'           => 'page.trash',
            ...$canvasVisibleControlStrings->pageTrash(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 46,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'external_url',
            'presentation' => [
                'action_group' => 'page_status',
                'group_role'   => 'menu',
                'menu_section' => 'publish',
                ...$canvasVisibleControlStrings->pageTrash()['presentation'],
            ],
            'state_resolver' => $resolver,
            'contexts'       => ['canvas'],
            'local'          => true,
        ]);

        // Order 46: page.settings - Open WordPress settings for this Page Builder page.
        $pageSettings = ControlDefinition::make([
            'id'           => 'page.settings',
            ...$canvasVisibleControlStrings->pageSettings(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 46,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'external_url',
            'presentation' => [
                'action_group' => 'page_status',
                'group_role'   => 'secondary',
                'menu_section' => 'secondary',
                ...$canvasVisibleControlStrings->pageSettings()['presentation'],
            ],
            'state_resolver' => $resolver,
            'contexts'       => ['canvas'],
            'local'          => true,
        ]);

        // Order 47: page.code_editor - Open this page in the WordPress editor to access code view.
        $pageCodeEditor = ControlDefinition::make([
            'id'           => 'page.code_editor',
            ...$canvasVisibleControlStrings->pageCodeEditor(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 47,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'external_url',
            'presentation' => [
                'action_group' => 'page_status',
                'group_role'   => 'secondary',
                'menu_section' => 'secondary',
                ...$canvasVisibleControlStrings->pageCodeEditor()['presentation'],
            ],
            'state_resolver' => $resolver,
            'contexts'       => ['canvas'],
            'local'          => true,
        ]);

        // Order 48: page.switch_to_wordpress - Restore WordPress editing for this page.
        $pageSwitchToWordPress = ControlDefinition::make([
            'id'           => 'page.switch_to_wordpress',
            ...$canvasVisibleControlStrings->pageSwitchToWordPress(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 48,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'switch_to_wordpress',
            'presentation' => [
                'action_group' => 'page_status',
                'group_role'   => 'secondary',
                'menu_section' => 'secondary',
                ...$canvasVisibleControlStrings->pageSwitchToWordPress()['presentation'],
            ],
            'state_resolver' => $resolver,
            'contexts'       => ['canvas'],
            'local'          => true,
        ]);

        // Order 49: page.admin_reusables - Open reusable parts.
        $pageAdminReusables = ControlDefinition::make([
            'id'           => 'page.admin_reusables',
            ...$canvasVisibleControlStrings->pageAdminReusables(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 49,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'external_url',
            'presentation' => [
                'action_group' => 'page_status',
                'group_role'   => 'secondary',
                'menu_section' => 'navigation',
                ...$canvasVisibleControlStrings->pageAdminReusables()['presentation'],
            ],
            'state_resolver' => $resolver,
            'contexts'       => ['canvas'],
            'local'          => true,
        ]);

        // Order 52: section.save_as_reusable - Save this section as a reusable part.
        $sectionSaveAsReusable = ControlDefinition::make([
            'id'              => 'section.save_as_reusable',
            ...$canvasSectionActionControlStrings->saveAsReusable(),
            'zone'            => ControlZone::Tools,
            'canvas_area'     => CanvasArea::SectionBadge,
            'order'           => 52,
            'type'            => ControlType::Trigger,
            'client_hint'     => 'open_modal',
            'presentation'    => [
                'surface'   => 'modal',
                'component' => 'save-as-reusable',
                ...$globalPartModalPresentationStrings->toArray(),
            ],
            'handler'         => SectionSaveAsReusableHandler::class,
            // Creates a new reusable object; it does not mutate the open page.
            'writes_editor_state' => false,
            'state_resolver'  => $sectionResolver,
        ]);

        // Order 52: page.admin_settings - Open shared Page Builder settings.
        $pageAdminSettings = ControlDefinition::make([
            'id'           => 'page.admin_settings',
            ...$canvasVisibleControlStrings->pageAdminSettings(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 52,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'external_url',
            'presentation' => [
                'action_group' => 'page_status',
                'group_role'   => 'secondary',
                'menu_section' => 'navigation',
                ...$canvasVisibleControlStrings->pageAdminSettings()['presentation'],
            ],
            'state_resolver' => $resolver,
            'contexts'       => ['canvas'],
            'local'          => true,
        ]);

        // Order 53: page.dashboard - Return to the WordPress dashboard.
        $pageDashboard = ControlDefinition::make([
            'id'           => 'page.dashboard',
            ...$canvasVisibleControlStrings->pageDashboard(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 53,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'external_url',
            'presentation' => [
                'action_group'     => 'page_status',
                'group_role'       => 'secondary',
                'menu_section'     => 'quick_links',
                ...$canvasVisibleControlStrings->pageDashboard()['presentation'],
            ],
            'state_resolver' => $resolver,
            'contexts'       => ['canvas'],
            'local'          => true,
        ]);

        // Order 54: page.automator_dashboard - Open the Automator dashboard.
        $pageAutomatorDashboard = ControlDefinition::make([
            'id'           => 'page.automator_dashboard',
            ...$canvasVisibleControlStrings->pageAutomatorDashboard(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 54,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'external_url',
            'presentation' => [
                'action_group'     => 'page_status',
                'group_role'       => 'secondary',
                'menu_section'     => 'quick_links',
                ...$canvasVisibleControlStrings->pageAutomatorDashboard()['presentation'],
            ],
            'state_resolver' => $resolver,
            'contexts'       => ['canvas'],
            'local'          => true,
        ]);

        // Order 55: page.automator_new_recipe - Create a new Automator recipe.
        $pageAutomatorNewRecipe = ControlDefinition::make([
            'id'           => 'page.automator_new_recipe',
            ...$canvasVisibleControlStrings->pageAutomatorNewRecipe(),
            'zone'         => ControlZone::Document,
            'canvas_area'  => CanvasArea::TopBarRight,
            'order'        => 55,
            'type'         => ControlType::Trigger,
            'client_hint'  => 'external_url',
            'presentation' => [
                'action_group'     => 'page_status',
                'group_role'       => 'secondary',
                'menu_section'     => 'quick_links',
                ...$canvasVisibleControlStrings->pageAutomatorNewRecipe()['presentation'],
            ],
            'state_resolver' => $resolver,
            'contexts'       => ['canvas'],
            'local'          => true,
        ]);

        // Order 60: section.delete - Delete this section from the page.
        $sectionDelete = ControlDefinition::make([
            'id'              => 'section.delete',
            ...$canvasSectionActionControlStrings->deleteSection(),
            'zone'            => ControlZone::Tools,
            'canvas_area'     => CanvasArea::SectionBadge,
            'order'           => 60,
            'type'            => ControlType::Trigger,
            'variant'         => 'danger',
            'client_hint'     => 'open_modal',
            'presentation'    => [
                'surface'   => 'modal',
                'component' => 'delete-section',
                ...$deleteConfirmationModalPresentationStrings->toArray(),
            ],
            'confirm'         => $canvasSectionActionControlStrings->deleteSection()['confirm'],
            'handler'         => SectionDeleteHandler::class,
            'writes_editor_state' => true,
            'state_resolver'  => $sectionResolver,
        ]);

        // Order 70: section.reorder - Persist the section order for this page.
        $sectionReorder = ControlDefinition::make([
            'id'              => 'section.reorder',
            ...$canvasHiddenControlStrings->sectionReorder(),
            'zone'            => ControlZone::Tools,
            'canvas_area'     => CanvasArea::Hidden,
            'order'           => 70,
            'type'            => ControlType::Trigger,
            'client_hint'     => 'drag_reorder',
            'handler'         => SectionReorderHandler::class,
            'writes_editor_state' => true,
            'state_resolver'  => $sectionResolver,
        ]);

        // Order 83: branding.site_design - Read the site design system in customer language.
        $siteDesignRead = ControlDefinition::make([
            ...$this->agentTool('get_site_design', 'read'),
            'id'          => 'branding.site_design',
            ...$canvasHiddenControlStrings->siteDesignRead(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 83,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 85: binding.manage - Read or update dynamic bindings.
        $bindingManage = ControlDefinition::make([
            ...$this->agentTool('manage_binding', 'write', true),
            'id'          => 'binding.manage',
            ...$canvasHiddenControlStrings->bindingManage(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 85,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 86: nav.manage - Read and update navigation menus.
        $manageNavigation = ControlDefinition::make([
            ...$this->agentTool('manage_navigation', 'write', true),
            'id'          => 'nav.manage',
            ...$canvasHiddenControlStrings->manageNavigation(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 86,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 86: tools.media - Search, inspect, and upload media.
        $manageMedia = ControlDefinition::make([
            ...$this->agentTool('manage_media', 'write'),
            'id'          => 'tools.media',
            ...$canvasHiddenControlStrings->manageMedia(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 86,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 87: page.read_context - Read page outline and next steps.
        $pageReadContext = ControlDefinition::make([
            ...$this->agentTool('read_page_context', 'read'),
            'id'          => 'page.read_context',
            ...$canvasHiddenControlStrings->pageReadContext(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 87,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 88: page.read_part - Read details for a section or reusable part.
        $partRead = ControlDefinition::make([
            ...$this->agentTool('read_part', 'read'),
            'id'          => 'page.read_part',
            ...$canvasHiddenControlStrings->partRead(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 88,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 88: page.read_runtime - Read page or reusable custom JavaScript.
        $runtimeRead = ControlDefinition::make([
            ...$this->agentTool('read_runtime', 'read'),
            'id'          => 'page.read_runtime',
            ...$canvasHiddenControlStrings->runtimeRead(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 88,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 88: tools.find_lucide_icons - Find valid Lucide icon names after warning recovery.
        $findLucideIcons = ControlDefinition::make([
            ...$this->agentTool('find_lucide_icons', 'read'),
            'id'          => 'tools.find_lucide_icons',
            ...$canvasHiddenControlStrings->findLucideIcons(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 88,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 89: section.edit_part - Edit a section or reusable part.
        $editPart = ControlDefinition::make([
            ...$this->agentTool('edit_part', 'write', true),
            'id'          => 'section.edit_part',
            ...$canvasHiddenControlStrings->editPart(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 89,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 89: page.edit_runtime - Replace or clear page or reusable custom JavaScript.
        $editRuntime = ControlDefinition::make([
            ...$this->agentTool('edit_runtime', 'write', true),
            'id'          => 'page.edit_runtime',
            ...$canvasHiddenControlStrings->editRuntime(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 89,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 89: page.preview_change - Preview a proposed HTML or CSS change.
        $previewChange = ControlDefinition::make([
            ...$this->agentTool('preview_change', 'read'),
            'id'          => 'page.preview_change',
            ...$canvasHiddenControlStrings->previewChange(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 89,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 89: page.preview_runtime_change - Preview a proposed JavaScript runtime change.
        $previewRuntimeChange = ControlDefinition::make([
            ...$this->agentTool('preview_runtime_change', 'read'),
            'id'          => 'page.preview_runtime_change',
            ...$canvasHiddenControlStrings->previewRuntimeChange(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 89,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 90: page.manage_sections - Manage page sections.
        $manageSections = ControlDefinition::make([
            ...$this->agentTool('manage_sections', 'write', true),
            'id'          => 'page.manage_sections',
            ...$canvasHiddenControlStrings->manageSections(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 90,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 91: page.manage_canvas - Manage canvas lifecycle.
        $manageCanvas = ControlDefinition::make([
            ...$this->agentTool('manage_canvas', 'write'),
            'id'          => 'page.manage_canvas',
            ...$canvasHiddenControlStrings->manageCanvas(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 91,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        // Order 92: page.manage_reusable - Manage reusable lifecycle.
        $manageReusable = ControlDefinition::make([
            ...$this->agentTool('manage_reusable', 'write'),
            'id'          => 'page.manage_reusable',
            ...$canvasHiddenControlStrings->manageReusable(),
            'zone'        => ControlZone::Tools,
            'canvas_area' => CanvasArea::Hidden,
            'order'       => 92,
            'type'        => ControlType::Trigger,
            'contexts'    => ['agent'],
        ]);

        $controls = [
            $sectionCreate,
            $pageNewCanvas,
            $sectionEditableUpdate,
            $sectionNodeUpdate,
            $sectionRewriteSource,
            $designStyleCommit,
            $historyUndo,
            $pageTitle,
            $viewportMode,
            $pageDetails,
            $manualChangesCommit,
            $pageResumeDraft,
            $pageCanvasType,
            $historyRedo,
            $pageStatus,
            $pageSaveDraft,
            $pageSavePublished,
            $pageMakeLive,
            $pageSwitchToDraft,
            $pagePublish,
            $pageFullScreenMode,
            $shellModeOpen,
            $pageExitFullScreenMode,
            $pagePreview,
            $pageSourceImport,
            $pageSourceExport,
            $pageStaticExport,
            $pageTrash,
            $pageSettings,
            $pageCodeEditor,
            $pageSwitchToWordPress,
            $pageAdminReusables,
            $sectionSaveAsReusable,
            $pageAdminSettings,
            $pageDashboard,
            $pageAutomatorDashboard,
            $pageAutomatorNewRecipe,
            $sectionDelete,
            $sectionReorder,
            $siteDesignRead,
            $bindingManage,
            $manageNavigation,
            $manageMedia,
            $pageReadContext,
            $partRead,
            $runtimeRead,
            $findLucideIcons,
            $editPart,
            $editRuntime,
            $previewChange,
            $previewRuntimeChange,
            $manageSections,
            $manageCanvas,
            $manageReusable,
        ];

        return $this->sortControlsByOrder($controls);
    }

    /**
     * Keep bootstrap output ordered by the control order value while preserving
     * declaration order for ties across separate zones.
     *
     * @param ControlDefinition[] $controls
     * @return ControlDefinition[]
     */
    private function sortControlsByOrder(array $controls): array
    {
        $indexed = [];
        foreach ($controls as $index => $control) {
            $indexed[] = [
            'index' => $index,
            'control' => $control,
            ];
        }

        usort(
            $indexed,
            static function (array $a, array $b): int {
                $orderCompare = $a['control']->order() <=> $b['control']->order();
                if ($orderCompare !== 0) {
                    return $orderCompare;
                }

                return $a['index'] <=> $b['index'];
            },
        );

        return array_map(
            static fn (array $item): ControlDefinition => $item['control'],
            $indexed,
        );
    }

    /** @return array<string, mixed> */
    private function agentTool(string $toolName, string $exposure, bool $requiresReadBeforeWrite = false): array
    {
        $path = UNCANNY_PB_PATH . 'tools/' . $toolName . '.json';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tool manifests are plugin-local JSON files, not remote URLs.
        $json = is_readable($path) ? file_get_contents($path) : false;
        if ($json === false) {
            if ($exposure === 'hidden') {
                return [
                    'agent_exposure'                   => $exposure,
                    'agent_name'                       => $toolName,
                    'agent_description'                => '',
                    'agent_input_schema'               => [],
                    'agent_output_schema'              => [],
                    'agent_auto_approve'               => false,
                    'agent_requires_read_before_write' => $requiresReadBeforeWrite,
                ];
            }

            throw new \RuntimeException(sprintf('Missing agent tool contract for "%s".', $toolName));
        }

        $tool = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($tool)) {
            throw new \RuntimeException(sprintf('Invalid agent tool contract for "%s".', $toolName));
        }

        return [
            'agent_exposure'                   => $exposure,
            'agent_name'                       => (string) ($tool['name'] ?? $toolName),
            'agent_description'                => (string) ($tool['description'] ?? ''),
            'agent_input_schema'               => is_array($tool['parameters'] ?? null) ? $tool['parameters'] : [],
            'agent_output_schema'              => is_array($tool['output'] ?? null) ? $tool['output'] : [],
            'agent_auto_approve'               => (bool) ($tool['auto_approve'] ?? false),
            'agent_requires_read_before_write' => $requiresReadBeforeWrite,
        ];
    }
}
