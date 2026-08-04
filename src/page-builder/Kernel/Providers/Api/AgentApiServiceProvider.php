<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\Api;

use UncannyPageBuilder\Api\AgentGuideController;
use UncannyPageBuilder\Api\AgentIconController;
use UncannyPageBuilder\Api\AgentMediaController;
use UncannyPageBuilder\Api\AgentNavigationController;
use UncannyPageBuilder\Api\AgentPageController;
use UncannyPageBuilder\Api\AgentPageController\AgentWrite\AgentWriteErrorMapper;
use UncannyPageBuilder\Api\AgentPageController\AgentWrite\AgentWriteGuard;
use UncannyPageBuilder\Api\AgentPageController\BindingController;
use UncannyPageBuilder\Api\AgentPageController\CanvasController;
use UncannyPageBuilder\Api\AgentPageController\ContentTargetController;
use UncannyPageBuilder\Api\AgentPageController\DesignStyleController;
use UncannyPageBuilder\Api\AgentPageController\ElementController;
use UncannyPageBuilder\Api\AgentPageController\GlobalPartSource\GlobalPartSourceController;
use UncannyPageBuilder\Api\AgentPageController\GlobalPartSource\GlobalPartSourcePatcher;
use UncannyPageBuilder\Api\AgentPageController\GlobalPartSource\GlobalPartSourceResolver;
use UncannyPageBuilder\Api\AgentPageController\PageContextController;
use UncannyPageBuilder\Api\AgentPageController\PartEdit\PartEditController;
use UncannyPageBuilder\Api\AgentPageController\PartEdit\PartEditRequestAdapter;
use UncannyPageBuilder\Api\AgentPageController\PartEdit\PartEditResponseFormatter;
use UncannyPageBuilder\Api\AgentPageController\PartRead\GlobalPartReader;
use UncannyPageBuilder\Api\AgentPageController\PartRead\PartDetailPresenter;
use UncannyPageBuilder\Api\AgentPageController\PartRead\PartReadController;
use UncannyPageBuilder\Api\AgentPageController\PartRead\PartSourcePresenter;
use UncannyPageBuilder\Api\AgentPageController\PartRead\SectionPartReader;
use UncannyPageBuilder\Api\AgentPageController\ReusableController;
use UncannyPageBuilder\Api\AgentPageController\RuntimeController;
use UncannyPageBuilder\Api\AgentPageController\Routes\AgentPageRouteRegistrar;
use UncannyPageBuilder\Api\AgentPageController\SectionCreate\CreateTargetResolver;
use UncannyPageBuilder\Api\AgentPageController\SectionCreate\GlobalPartSourceCreator;
use UncannyPageBuilder\Api\AgentPageController\SectionCreate\SectionCreateController;
use UncannyPageBuilder\Api\AgentPageController\SectionCreate\SectionCreateResponseFormatter;
use UncannyPageBuilder\Api\AgentPageController\SectionManagementController;
use UncannyPageBuilder\Api\AgentPageController\SectionSourceReplaceController;
use UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch\PatchPayloadPreparer;
use UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch\PatchResponseFormatter;
use UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch\PreviewStyleWarning;
use UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch\SectionSourcePatchController;
use UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch\SectionSourceWriter;
use UncannyPageBuilder\Api\AgentPageController\SectionWriteRequestResolver;
use UncannyPageBuilder\Api\AgentToolsController;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Canvas\CreateCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\AttachReusableToCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\DeleteCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\EditCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\ListCanvasUseCase;
use UncannyPageBuilder\Application\Controls\ControlRegistry;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Reusable\CreateReusableUseCase;
use UncannyPageBuilder\Application\Reusable\ConvertSectionToReusableUseCase;
use UncannyPageBuilder\Application\Reusable\DeleteReusableUseCase;
use UncannyPageBuilder\Application\Reusable\ListReusableUseCase;
use UncannyPageBuilder\Application\Reusable\UpdateReusableUseCase;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\DesignStyles\DesignStyleCommitService;
use UncannyPageBuilder\Application\Editing\SectionNodeUpdateService;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\NavigationMenuService;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiffer;
use UncannyPageBuilder\Domain\Editing\ExactSourcePatcher;
use UncannyPageBuilder\Domain\Section\HtmlCssProcessor;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Infrastructure\Section\ComponentCategoryClassifier;
use UncannyPageBuilder\Infrastructure\Section\CssRulePatcher;
use UncannyPageBuilder\Infrastructure\Section\DomSectionBindingContractInspector;
use UncannyPageBuilder\Infrastructure\Section\DomSectionManifestExtractor;
use UncannyPageBuilder\Infrastructure\Section\DomSectionTargetInspector;
use UncannyPageBuilder\Infrastructure\Section\LucideIconFinder;
use UncannyPageBuilder\Infrastructure\WordPress\AgentTextResponseServer;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;
use UncannyPageBuilder\Presentation\Api\MediaController;

final class AgentApiServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->factory(AgentTextResponseServer::class, static function (): AgentTextResponseServer {
            return new AgentTextResponseServer();
        });

        $container->factory(AgentGuideController::class, static function (Container $c): AgentGuideController {
            return new AgentGuideController(
                $c->typed(PermissionChecker::class),
                $c->typed(BindingRegistry::class),
                $c->typed(DesignStandardsService::class),
            );
        });

        $container->factory(AgentToolsController::class, static function (Container $c): AgentToolsController {
            return new AgentToolsController(
                $c->typed(PermissionChecker::class),
                $c->typed(ControlRegistry::class),
            );
        });

        $container->factory(AgentIconController::class, static function (Container $c): AgentIconController {
            return new AgentIconController(
                $c->typed(PermissionChecker::class),
                $c->typed(LucideIconFinder::class),
            );
        });

        $container->factory(AgentNavigationController::class, static function (Container $c): AgentNavigationController {
            return new AgentNavigationController(
                $c->typed(PermissionChecker::class),
                $c->typed(NavigationMenuService::class),
            );
        });

        $container->factory(AgentMediaController::class, static function (Container $c): AgentMediaController {
            return new AgentMediaController(
                $c->typed(PermissionChecker::class),
                $c->typed(MediaController::class),
            );
        });

        $container->factory(RuntimeController::class, static function (Container $c): RuntimeController {
            return new RuntimeController(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(GlobalPartService::class),
                $c->typed(CompactSourceDiffer::class),
                $c->typed(PageJavaScriptRuntimeService::class),
                $c->typed(\UncannyPageBuilder\Application\Settings\ToolSettingsAccess::class),
            );
        });

        $container->factory(SectionWriteRequestResolver::class, static function (Container $c): SectionWriteRequestResolver {
            return new SectionWriteRequestResolver(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
            );
        });

        $container->factory(ElementController::class, static function (Container $c): ElementController {
            return new ElementController(
                $c->typed(SectionWriteRequestResolver::class),
                new DomSectionTargetInspector(),
                $c->typed(SectionNodeUpdateService::class),
                $c->typed(CompactSourceDiffer::class),
            );
        });

        $container->factory(ContentTargetController::class, static function (Container $c): ContentTargetController {
            return new ContentTargetController(
                $c->typed(SectionWriteRequestResolver::class),
                new DomSectionTargetInspector(),
                $c->typed(SectionNodeUpdateService::class),
                $c->typed(CompactSourceDiffer::class),
            );
        });

        $container->factory(SectionManagementController::class, static function (Container $c): SectionManagementController {
            return new SectionManagementController(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(PageDetailsPortInterface::class),
            );
        });

        $container->factory(ReusableController::class, static function (Container $c): ReusableController {
            return new ReusableController(
                $c->typed(CreateReusableUseCase::class),
                $c->typed(ConvertSectionToReusableUseCase::class),
                $c->typed(UpdateReusableUseCase::class),
                $c->typed(DeleteReusableUseCase::class),
                $c->typed(ListReusableUseCase::class),
            );
        });

        $container->factory(BindingController::class, static function (Container $c): BindingController {
            return new BindingController(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(DomSectionBindingContractInspector::class),
                $c->typed(HtmlCssProcessor::class),
                $c->typed(CompactSourceDiffer::class),
                $c->typed(BindingRegistry::class),
                $c->typed(PageDetailsPortInterface::class),
            );
        });

        $container->factory(PageContextController::class, static function (Container $c): PageContextController {
            return new PageContextController(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(ComponentCategoryClassifier::class),
                $c->typed(DomSectionManifestExtractor::class),
                $c->typed(GlobalPartService::class),
                $c->typed(\UncannyPageBuilder\Application\ShellModeService::class),
                $c->typed(\UncannyPageBuilder\Infrastructure\WordPress\WpPageGlobalPartResolver::class),
                $c->typed(PageDetailsPortInterface::class),
            );
        });

        $container->factory(SectionSourceReplaceController::class, static function (Container $c): SectionSourceReplaceController {
            return new SectionSourceReplaceController(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(HtmlCssProcessor::class),
                $c->typed(CompactSourceDiffer::class),
                $c->typed(BindingRegistry::class),
                $c->typed(PageDetailsPortInterface::class),
            );
        });

        $container->factory(CanvasController::class, static function (Container $c): CanvasController {
            return new CanvasController(
                $c->typed(PermissionChecker::class),
                $c->typed(CreateCanvasUseCase::class),
                $c->typed(EditCanvasUseCase::class),
                $c->typed(DeleteCanvasUseCase::class),
                $c->typed(ListCanvasUseCase::class),
                $c->typed(AttachReusableToCanvasUseCase::class),
            );
        });

        $container->factory(DesignStyleController::class, static function (Container $c): DesignStyleController {
            return new DesignStyleController(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(DesignStyleCommitService::class),
            );
        });

        $container->factory(PatchPayloadPreparer::class, static function (Container $c): PatchPayloadPreparer {
            return new PatchPayloadPreparer(
                $c->typed(ExactSourcePatcher::class),
                $c->typed(CssRulePatcher::class),
                $c->typed(BindingRegistry::class),
            );
        });

        $container->factory(SectionSourceWriter::class, static function (Container $c): SectionSourceWriter {
            return new SectionSourceWriter(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(CompactSourceDiffer::class),
                $c->typed(BindingRegistry::class),
                $c->typed(PageDetailsPortInterface::class),
            );
        });

        $container->factory(PreviewStyleWarning::class, static function (): PreviewStyleWarning {
            return new PreviewStyleWarning(new DomSectionTargetInspector());
        });

        $container->factory(PatchResponseFormatter::class, static function (): PatchResponseFormatter {
            return new PatchResponseFormatter();
        });

        $container->factory(SectionSourcePatchController::class, static function (Container $c): SectionSourcePatchController {
            return new SectionSourcePatchController(
                $c->typed(PatchPayloadPreparer::class),
                $c->typed(SectionSourceWriter::class),
                $c->typed(PreviewStyleWarning::class),
                $c->typed(PatchResponseFormatter::class),
            );
        });

        $container->factory(GlobalPartSourceResolver::class, static function (Container $c): GlobalPartSourceResolver {
            return new GlobalPartSourceResolver(
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(GlobalPartService::class),
            );
        });

        $container->factory(GlobalPartSourcePatcher::class, static function (Container $c): GlobalPartSourcePatcher {
            return new GlobalPartSourcePatcher(
                $c->typed(CssRulePatcher::class),
                $c->typed(ExactSourcePatcher::class),
                $c->typed(BindingRegistry::class),
            );
        });

        $container->factory(GlobalPartSourceController::class, static function (Container $c): GlobalPartSourceController {
            return new GlobalPartSourceController(
                $c->typed(PermissionChecker::class),
                $c->typed(GlobalPartService::class),
                $c->typed(GlobalPartSourceResolver::class),
                $c->typed(GlobalPartSourcePatcher::class),
                $c->typed(CompactSourceDiffer::class),
            );
        });

        $container->factory(PartSourcePresenter::class, static function (Container $c): PartSourcePresenter {
            return new PartSourcePresenter($c->typed(BindingRegistry::class));
        });

        $container->factory(PartDetailPresenter::class, static function (Container $c): PartDetailPresenter {
            return new PartDetailPresenter(
                $c->typed(ComponentCategoryClassifier::class),
                $c->typed(DomSectionManifestExtractor::class),
                new DomSectionTargetInspector(),
                $c->typed(DomSectionBindingContractInspector::class),
                $c->typed(PartSourcePresenter::class),
            );
        });

        $container->factory(SectionPartReader::class, static function (Container $c): SectionPartReader {
            return new SectionPartReader(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(PartDetailPresenter::class),
            );
        });

        $container->factory(GlobalPartReader::class, static function (Container $c): GlobalPartReader {
            return new GlobalPartReader(
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(GlobalPartService::class),
                $c->typed(PermissionChecker::class),
                $c->typed(PartDetailPresenter::class),
            );
        });

        $container->factory(PartReadController::class, static function (Container $c): PartReadController {
            return new PartReadController(
                $c->typed(SectionPartReader::class),
                $c->typed(GlobalPartReader::class),
                $c->typed(PartDetailPresenter::class),
            );
        });

        $container->factory(PartEditRequestAdapter::class, static function (): PartEditRequestAdapter {
            return new PartEditRequestAdapter();
        });

        $container->factory(PartEditResponseFormatter::class, static function (): PartEditResponseFormatter {
            return new PartEditResponseFormatter();
        });

        $container->factory(PartEditController::class, static function (Container $c): PartEditController {
            return new PartEditController(
                $c->typed(ContentTargetController::class),
                $c->typed(DesignStyleController::class),
                $c->typed(ElementController::class),
                $c->typed(SectionSourcePatchController::class),
                $c->typed(SectionSourceReplaceController::class),
                $c->typed(GlobalPartSourceController::class),
                $c->typed(GlobalPartSourceResolver::class),
                $c->typed(PartEditRequestAdapter::class),
                $c->typed(PartEditResponseFormatter::class),
            );
        });

        $container->factory(CreateTargetResolver::class, static function (): CreateTargetResolver {
            return new CreateTargetResolver();
        });

        $container->factory(SectionCreateResponseFormatter::class, static function (Container $c): SectionCreateResponseFormatter {
            return new SectionCreateResponseFormatter($c->typed(PageDetailsPortInterface::class));
        });

        $container->factory(GlobalPartSourceCreator::class, static function (Container $c): GlobalPartSourceCreator {
            return new GlobalPartSourceCreator(
                $c->typed(GlobalPartService::class),
                $c->typed(SectionCreateResponseFormatter::class),
            );
        });

        $container->factory(SectionCreateController::class, static function (Container $c): SectionCreateController {
            return new SectionCreateController(
                $c->typed(SectionService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(CreateTargetResolver::class),
                $c->typed(GlobalPartSourceCreator::class),
                $c->typed(SectionCreateResponseFormatter::class),
            );
        });

        $container->factory(AgentWriteErrorMapper::class, static function (): AgentWriteErrorMapper {
            return new AgentWriteErrorMapper();
        });

        $container->factory(AgentWriteGuard::class, static function (Container $c): AgentWriteGuard {
            return new AgentWriteGuard(
                $c->typed(AgentWriteErrorMapper::class),
                $c->typed(\UncannyPageBuilder\Application\Concurrency\PageSourceMutation::class),
                $c->typed(\UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface::class),
                $c->typed(\UncannyPageBuilder\Application\Editor\SelectEditorPageSource::class),
                $c->typed(\UncannyPageBuilder\Application\Publishing\PageLiveStateReaderInterface::class),
            );
        });

        $container->factory(AgentPageRouteRegistrar::class, static function (Container $c): AgentPageRouteRegistrar {
            return new AgentPageRouteRegistrar(
                $c->typed(PermissionChecker::class),
                $c->typed(AgentWriteGuard::class),
            );
        });

        $container->factory(AgentPageController::class, static function (Container $c): AgentPageController {
            return new AgentPageController(
                $c->typed(RuntimeController::class),
                $c->typed(SectionManagementController::class),
                $c->typed(ReusableController::class),
                $c->typed(BindingController::class),
                $c->typed(PageContextController::class),
                $c->typed(CanvasController::class),
                $c->typed(SectionSourcePatchController::class),
                $c->typed(PartReadController::class),
                $c->typed(PartEditController::class),
                $c->typed(SectionCreateController::class),
                $c->typed(AgentPageRouteRegistrar::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        $controllers = [
            $container->typed(AgentGuideController::class),
            $container->typed(AgentToolsController::class),
            $container->typed(AgentIconController::class),
            $container->typed(AgentNavigationController::class),
            $container->typed(AgentMediaController::class),
            $container->typed(AgentPageController::class),
        ];

        // Agent media upload proxies to the Presentation MediaController
        // so the agent can use /agent/media/upload instead of /media/upload.
        $mediaController = $container->typed(MediaController::class);
        $permissions = $container->typed(PermissionChecker::class);

        add_action('rest_api_init', static function () use ($controllers, $mediaController, $permissions): void {
            foreach ($controllers as $controller) {
                $controller->registerRoutes();
            }

            register_rest_route('uncanny-page-builder/v1', '/agent/media/upload', [
                'methods' => 'POST',
                'callback' => [$mediaController, 'upload'],
                'permission_callback' => [$permissions, 'canEdit'],
            ]);
        });

        $container->typed(AgentTextResponseServer::class)->register();
    }
}
