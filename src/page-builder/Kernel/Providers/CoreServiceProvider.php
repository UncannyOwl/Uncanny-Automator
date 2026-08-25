<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers;

use UncannyPageBuilder\Application\Filesystem\LocalFileReaderInterface;
use UncannyPageBuilder\Application\Filesystem\LocalFilesystemPortInterface;
use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;
use UncannyPageBuilder\Application\Canvas\PagePasswordProtectionInterface;
use UncannyPageBuilder\Application\Rendering\PublishedPageAssetResolverInterface;
use UncannyPageBuilder\Application\Rendering\PublishedPageReaderInterface;
use UncannyPageBuilder\Application\Rendering\PublicPageIdentityReaderInterface;
use UncannyPageBuilder\Application\Rendering\ReadPublishedPage;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressLocalFilesystem;
use UncannyPageBuilder\Application\Canvas\CanvasGlobalPartRendererInterface;
use UncannyPageBuilder\Application\Canvas\CanvasRefreshRendererInterface;
use UncannyPageBuilder\Application\Canvas\CanvasGlobalPartsProviderInterface;
use UncannyPageBuilder\Application\Canvas\CanvasGlobalPartsService;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Canvas\AdoptPageUseCase;
use UncannyPageBuilder\Application\Canvas\AttachReusableToCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\CanvasPortInterface;
use UncannyPageBuilder\Application\Canvas\CreateCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\DeleteCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\EditCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\ListCanvasUseCase;
use UncannyPageBuilder\Application\Canvas\OriginalPageContentStoreInterface;
use UncannyPageBuilder\Application\Canvas\OriginalPageContentReaderInterface;
use UncannyPageBuilder\Application\Canvas\ResolveEmptyCanvasInvitation;
use UncannyPageBuilder\Application\Canvas\ReturnPageToWordPressUseCase;
use UncannyPageBuilder\Application\Canvas\ReturnPageToWordPressTransitionInterface;
use UncannyPageBuilder\Application\Controls\PageDetailsProjectionInterface;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Controls\PageDetailsService;
use UncannyPageBuilder\Application\Controls\PageTrashUrlPortInterface;
use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Options\OptionsPortInterface;
use UncannyPageBuilder\Application\Settings\LoadSettingsUseCase;
use UncannyPageBuilder\Application\Settings\ListContentTypesUseCase;
use UncannyPageBuilder\Application\Settings\ListDisplayableContentTypesUseCase;
use UncannyPageBuilder\Application\Settings\ListEnabledContentTypesUseCase;
use UncannyPageBuilder\Application\ContentType\PostTypeIntentForPostInterface;
use UncannyPageBuilder\Application\Reusable\CreateReusableUseCase;
use UncannyPageBuilder\Application\Reusable\ConvertSectionToReusableUseCase;
use UncannyPageBuilder\Application\Reusable\DeleteReusableUseCase;
use UncannyPageBuilder\Application\Reusable\ListReusableUseCase;
use UncannyPageBuilder\Application\Reusable\ReusablePortInterface;
use UncannyPageBuilder\Application\Reusable\UpdateReusableUseCase;
use UncannyPageBuilder\Application\Settings\SaveBrandStylesSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveContentTypeSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveDesignDirectionSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveFontSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveLogoSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SavePageLayoutSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveToolSettingsUseCase;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Application\Settings\ValidateContentTypeSelectionUseCase;
use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Access\AgentAuthoringAvailabilityInterface;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\BindingContractReplacementService;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\Editor\EditorStateService;
use UncannyPageBuilder\Application\Editor\PublishedSourceSnapshotMigrationInterface;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\Editor\RestorePublishedSourceToWorkingDraft;
use UncannyPageBuilder\Application\Editing\EditableUpdateService;
use UncannyPageBuilder\Application\Editing\EditableHtmlMutator;
use UncannyPageBuilder\Application\Editing\GlobalPartEditableUpdateService;
use UncannyPageBuilder\Application\Editing\GlobalPartNodeUpdateService;
use UncannyPageBuilder\Application\Editing\SectionNodeHtmlMutator;
use UncannyPageBuilder\Application\EditorLock\CheckHumanWriteOwnership;
use UncannyPageBuilder\Application\EditorLock\EnterEditorOwnership;
use UncannyPageBuilder\Application\EditorLock\InspectEditorOwnership;
use UncannyPageBuilder\Application\EditorLock\RefreshEditorOwnership;
use UncannyPageBuilder\Application\EditorLock\ReleaseEditorOwnership;
use UncannyPageBuilder\Application\EditorLock\TakeOverEditorOwnership;
use UncannyPageBuilder\Application\Export\StaticPageExportService;
use UncannyPageBuilder\Application\Export\StaticPageExportBuilderInterface;
use UncannyPageBuilder\Application\Export\PageJavaScriptExportRendererInterface;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\History\OperationHistoryService;
use UncannyPageBuilder\Application\History\PageDetailsHistoryRestorerInterface;
use UncannyPageBuilder\Application\History\SectionHistoryRestorerInterface;
use UncannyPageBuilder\Application\NavigationMenuService;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\PageGlobalPartSelectionService;
use UncannyPageBuilder\Application\Personalization\SitePersonalizationService;
use UncannyPageBuilder\Application\Publishing\BuildPageArtifact;
use UncannyPageBuilder\Application\Publishing\CapturePageSourceSnapshot;
use UncannyPageBuilder\Application\Publishing\PageArtifactBuilderInterface;
use UncannyPageBuilder\Application\Publishing\OwnedPageFinderInterface;
use UncannyPageBuilder\Application\Publishing\PagePublicationAuthorizerInterface;
use UncannyPageBuilder\Application\Publishing\PageDraftStatusPortInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshQueueInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Application\Publishing\ReadPageLiveState;
use UncannyPageBuilder\Application\Publishing\PageLiveStateReaderInterface;
use UncannyPageBuilder\Application\Publishing\PagePublisherInterface;
use UncannyPageBuilder\Application\Publishing\PageDeactivationFallbackAssetResolverInterface;
use UncannyPageBuilder\Application\Publishing\PublishPage;
use UncannyPageBuilder\Application\Publishing\SwitchPageToDraft;
use UncannyPageBuilder\Application\Publishing\SwitchPageToDraftInterface;
use UncannyPageBuilder\Application\Publishing\RefreshWorkingCanvas;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\Section\SectionPostCommitFailureReporterInterface;
use UncannyPageBuilder\Application\Section\SectionSourceSanitizerInterface;
use UncannyPageBuilder\Application\ShellImportService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Application\SourcePackage\PageSourcePackageService;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveService;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveWriterInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceImageCollectorInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceImageImporterInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceImageUrlRewriter;
use UncannyPageBuilder\Application\SourcePackage\ReusableSourcePackageService;
use UncannyPageBuilder\Application\System\SchemaInstallerInterface;
use UncannyPageBuilder\Application\ThemeCompositionPageTemplateSynchronizerInterface;
use UncannyPageBuilder\Application\UpdatePageLayout;
use UncannyPageBuilder\Domain\Access\PageBuilderAllowedCapabilityPort;
use UncannyPageBuilder\Domain\ContentType\ContentTypeCatalogInterface;
use UncannyPageBuilder\Domain\ContentType\PageBuilderDisplayPolicy;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiffer;
use UncannyPageBuilder\Domain\Editing\ExactSourcePatcher;
use UncannyPageBuilder\Domain\Export\StaticExportGlobalPartResolverInterface;
use UncannyPageBuilder\Domain\Export\StaticExportContextProviderInterface;
use UncannyPageBuilder\Domain\Export\StaticRenderingPolicy;
use UncannyPageBuilder\Domain\History\OperationHistoryRepositoryInterface;
use UncannyPageBuilder\Domain\JavaScriptRuntime\CustomJavaScriptRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelectionRepositoryInterface;
use UncannyPageBuilder\Domain\Navigation\NavigationMenuRepositoryInterface;
use UncannyPageBuilder\Domain\Personalization\SitePersonalizationRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifactRepositoryInterface;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;
use UncannyPageBuilder\Domain\Section\HtmlCssProcessor;
use UncannyPageBuilder\Infrastructure\Automator\BearerAuthenticator;
use UncannyPageBuilder\Infrastructure\Automator\AutomatorAgentAuthoringAvailability;
use UncannyPageBuilder\Infrastructure\Automator\AutomatorPageBuilderAvailability;
use UncannyPageBuilder\Infrastructure\Automator\AutomatorSetupWizardUrl;
use UncannyPageBuilder\Infrastructure\Compiler\CssMinifier;
use UncannyPageBuilder\Infrastructure\Export\CanvasStaticExportHtmlRenderer;
use UncannyPageBuilder\Infrastructure\Export\PluginStaticExportAssetSource;
use UncannyPageBuilder\Infrastructure\Export\WordPressStaticExportContextProvider;
use UncannyPageBuilder\Infrastructure\Export\WordPressStaticExportGlobalPartResolver;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseGlobalPartRepository;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseOperationHistoryRepository;
use UncannyPageBuilder\Infrastructure\Persistence\DatabasePageStateRepository;
use UncannyPageBuilder\Infrastructure\Persistence\DatabasePageSourceSnapshotRepository;
use UncannyPageBuilder\Infrastructure\Persistence\DatabasePublishedPageArtifactRepository;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Infrastructure\Persistence\LazyPublishedSourceSnapshotMigrator;
use UncannyPageBuilder\Infrastructure\Persistence\WordPressSchemaInstaller;
use UncannyPageBuilder\Infrastructure\Persistence\WordPressSourceGenerationStore;
use UncannyPageBuilder\Infrastructure\Persistence\WpPostMetaCustomJavaScriptRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpDesignStandardsRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpOptionsRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpPageOwnershipRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpPageGlobalPartSelectionRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpSettingsRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpSettingsSitePersonalizationRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpSitePersonalizationRepository;
use UncannyPageBuilder\Infrastructure\Persistence\WpShellModeRepository;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Canvas\PageOwnershipRepositoryInterface;
use UncannyPageBuilder\Domain\Export\StaticExportAssetSourceInterface;
use UncannyPageBuilder\Domain\Export\StaticExportHtmlRendererInterface;
use UncannyPageBuilder\Domain\Section\BindingSchema;
use UncannyPageBuilder\Domain\Section\LucideIconValidator;
use UncannyPageBuilder\Infrastructure\Binding\BindingLoader;
use UncannyPageBuilder\Infrastructure\Section\LucideIconFinder;
use UncannyPageBuilder\Infrastructure\Section\StaticLucideIconCatalog;
use UncannyPageBuilder\Infrastructure\Rendering\CanvasRenderer;
use UncannyPageBuilder\Infrastructure\Rendering\CanvasRefreshRenderer;
use UncannyPageBuilder\Infrastructure\Rendering\DynamicRenderer;
use UncannyPageBuilder\Infrastructure\Rendering\PageJavaScriptRuntimeRenderer;
use UncannyPageBuilder\Infrastructure\Rendering\PublishedPageAssetResolver;
use UncannyPageBuilder\Infrastructure\Rendering\PublishedCanvasRenderer;
use UncannyPageBuilder\Infrastructure\Rendering\ShortcodeBindingNormalizer;
use UncannyPageBuilder\Infrastructure\Section\ComponentCategoryClassifier;
use UncannyPageBuilder\Infrastructure\Section\CssRulePatcher;
use UncannyPageBuilder\Infrastructure\Section\DomSectionBindingContractInspector;
use UncannyPageBuilder\Infrastructure\Section\DomSectionManifestExtractor;
use UncannyPageBuilder\Infrastructure\Section\HtmlBridgeArtifactCleanerAdapter;
use UncannyPageBuilder\Infrastructure\Shell\RenderedShellAnalyzer;
use UncannyPageBuilder\Infrastructure\Section\WordPressSectionSourceSanitizer;
use UncannyPageBuilder\Infrastructure\WordPress\WpDynamicContentConfigProvider;
use UncannyPageBuilder\Infrastructure\WordPress\KsesSanitizer;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageBuilderAllowedCapabilityPort;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressContentTypeCatalog;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressSectionPostCommitFailureReporter;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressFailureReporter;
use UncannyPageBuilder\Infrastructure\WordPress\WpCronWorkingCanvasRefreshQueue;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressNavigationMenuRepository;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressCanvasPort;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageDetailsProjection;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPagePasswordProtection;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPagePublicationAuthorizer;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPagePublisher;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressUploadedFallbackAssetResolver;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPublishedFallbackComposer;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageDraftStatusPort;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageHandover;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressFontSettings;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressEditorLockStore;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPublicPageIdentityReader;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageTrashUrlPort;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageSourceImageCollector;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageSourceImageImporter;
use UncannyPageBuilder\Infrastructure\WordPress\ZipPageSourceArchiveWriter;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressReusablePort;
use UncannyPageBuilder\Infrastructure\WordPress\WpOwnedPageFinder;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostTypeIntentForPost;
use UncannyPageBuilder\Infrastructure\WordPress\WpOriginalPageContentStore;
use UncannyPageBuilder\Infrastructure\WordPress\WpPageGlobalPartResolver;
use UncannyPageBuilder\Infrastructure\WordPress\WpThemeEnvironment;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;

final class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // ── Infrastructure / Persistence ──────────────────
        $container->factory(WordPressSchemaInstaller::class, static function (): WordPressSchemaInstaller {
            return new WordPressSchemaInstaller();
        });

        $container->factory(SchemaInstallerInterface::class, static function (Container $c): SchemaInstallerInterface {
            return $c->typed(WordPressSchemaInstaller::class);
        });

        $container->factory(WordPressSourceGenerationStore::class, static function (): WordPressSourceGenerationStore {
            return new WordPressSourceGenerationStore();
        });

        $container->factory(SourceGenerationStoreInterface::class, static function (Container $c): SourceGenerationStoreInterface {
            return $c->typed(WordPressSourceGenerationStore::class);
        });

        // ── Human editor ownership ────────────────────────
        $container->factory(WordPressEditorLockStore::class, static function (): WordPressEditorLockStore {
            return new WordPressEditorLockStore();
        });

        $container->factory(EditorLockStoreInterface::class, static function (Container $c): EditorLockStoreInterface {
            return $c->typed(WordPressEditorLockStore::class);
        });

        $container->factory(EnterEditorOwnership::class, static function (Container $c): EnterEditorOwnership {
            return new EnterEditorOwnership($c->typed(EditorLockStoreInterface::class));
        });

        $container->factory(InspectEditorOwnership::class, static function (Container $c): InspectEditorOwnership {
            return new InspectEditorOwnership($c->typed(EditorLockStoreInterface::class));
        });

        $container->factory(TakeOverEditorOwnership::class, static function (Container $c): TakeOverEditorOwnership {
            return new TakeOverEditorOwnership($c->typed(EditorLockStoreInterface::class));
        });

        $container->factory(CheckHumanWriteOwnership::class, static function (Container $c): CheckHumanWriteOwnership {
            return new CheckHumanWriteOwnership($c->typed(EditorLockStoreInterface::class));
        });

        $container->factory(RefreshEditorOwnership::class, static function (Container $c): RefreshEditorOwnership {
            return new RefreshEditorOwnership($c->typed(EditorLockStoreInterface::class));
        });

        $container->factory(ReleaseEditorOwnership::class, static function (Container $c): ReleaseEditorOwnership {
            return new ReleaseEditorOwnership($c->typed(EditorLockStoreInterface::class));
        });

        $container->factory(GlobalSourceMutation::class, static function (Container $c): GlobalSourceMutation {
            return new GlobalSourceMutation($c->typed(SourceGenerationStoreInterface::class));
        });

        $container->factory(PageSourceMutation::class, static function (Container $c): PageSourceMutation {
            return new PageSourceMutation(
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(OperationHistoryRepositoryInterface::class),
            );
        });

        $container->factory(WpPageGlobalPartSelectionRepository::class, static function (): WpPageGlobalPartSelectionRepository {
            return new WpPageGlobalPartSelectionRepository();
        });

        $container->factory(PageGlobalPartSelectionRepositoryInterface::class, static function (Container $c): PageGlobalPartSelectionRepositoryInterface {
            return $c->typed(WpPageGlobalPartSelectionRepository::class);
        });

        $container->factory(DatabaseSectionRepository::class, static function (Container $c): DatabaseSectionRepository {
            return new DatabaseSectionRepository(
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(PageSourceMutation::class),
                $c->typed(ShadowCompiler::class),
            );
        });

        $container->factory(SupportsPostTypeUseCase::class, static function (Container $c): SupportsPostTypeUseCase {
            return new SupportsPostTypeUseCase(
                $c->typed(ListDisplayableContentTypesUseCase::class),
                $c->typed(ListEnabledContentTypesUseCase::class),
            );
        });

        $container->factory(WpPageOwnershipRepository::class, static function (): WpPageOwnershipRepository {
            return new WpPageOwnershipRepository();
        });

        $container->factory(PageOwnershipRepositoryInterface::class, static function (Container $c): PageOwnershipRepositoryInterface {
            return $c->typed(WpPageOwnershipRepository::class);
        });

        $container->factory(WpOriginalPageContentStore::class, static function (): WpOriginalPageContentStore {
            return new WpOriginalPageContentStore();
        });

        $container->factory(OriginalPageContentStoreInterface::class, static function (Container $c): OriginalPageContentStoreInterface {
            return $c->typed(WpOriginalPageContentStore::class);
        });

        $container->factory(OriginalPageContentReaderInterface::class, static function (Container $c): OriginalPageContentReaderInterface {
            return $c->typed(WpOriginalPageContentStore::class);
        });

        $container->factory(WordPressPageHandover::class, static function (Container $c): WordPressPageHandover {
            return new WordPressPageHandover(
                $c->typed(PageOwnershipRepositoryInterface::class),
                $c->typed(OriginalPageContentStoreInterface::class),
            );
        });

        $container->factory(ReturnPageToWordPressTransitionInterface::class, static function (Container $c): ReturnPageToWordPressTransitionInterface {
            return $c->typed(WordPressPageHandover::class);
        });

        $container->factory(PublicPageRenderPolicy::class, static function (Container $c): PublicPageRenderPolicy {
            return new PublicPageRenderPolicy(
                $c->typed(PublishedPageReaderInterface::class),
                $c->typed(PageOwnershipRepositoryInterface::class),
                $c->typed(PagePasswordProtectionInterface::class),
                $c->typed(PostTypeIntentForPostInterface::class),
            );
        });

        $container->factory(WordPressPostTypeIntentForPost::class, static function (Container $c): WordPressPostTypeIntentForPost {
            return new WordPressPostTypeIntentForPost(
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(PostTypeIntentForPostInterface::class, static function (Container $c): PostTypeIntentForPostInterface {
            return $c->typed(WordPressPostTypeIntentForPost::class);
        });

        $container->factory(WordPressPagePasswordProtection::class, static fn (): WordPressPagePasswordProtection => new WordPressPagePasswordProtection());

        $container->factory(PagePasswordProtectionInterface::class, static function (Container $c): PagePasswordProtectionInterface {
            return $c->typed(WordPressPagePasswordProtection::class);
        });

        $container->factory(DatabaseGlobalPartRepository::class, static function (Container $c): DatabaseGlobalPartRepository {
            return new DatabaseGlobalPartRepository(
                $c->typed(KsesSanitizer::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(GlobalSourceMutation::class),
            );
        });

        $container->factory(DatabaseOperationHistoryRepository::class, static function (): DatabaseOperationHistoryRepository {
            return new DatabaseOperationHistoryRepository();
        });

        $container->factory(DatabasePageStateRepository::class, static function (Container $c): DatabasePageStateRepository {
            return new DatabasePageStateRepository(
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(PageSourceMutation::class),
            );
        });

        $container->factory(DatabasePublishedPageArtifactRepository::class, static function (): DatabasePublishedPageArtifactRepository {
            return new DatabasePublishedPageArtifactRepository();
        });

        $container->factory(DatabasePageSourceSnapshotRepository::class, static function (): DatabasePageSourceSnapshotRepository {
            return new DatabasePageSourceSnapshotRepository();
        });

        $container->factory(OperationHistoryRepositoryInterface::class, static function (Container $c): OperationHistoryRepositoryInterface {
            return $c->typed(DatabaseOperationHistoryRepository::class);
        });

        $container->factory(PageStateRepositoryInterface::class, static function (Container $c): PageStateRepositoryInterface {
            return $c->typed(DatabasePageStateRepository::class);
        });

        $container->factory(PublishedPageArtifactRepositoryInterface::class, static function (Container $c): PublishedPageArtifactRepositoryInterface {
            return $c->typed(DatabasePublishedPageArtifactRepository::class);
        });

        $container->factory(PageSourceSnapshotRepositoryInterface::class, static function (Container $c): PageSourceSnapshotRepositoryInterface {
            return $c->typed(DatabasePageSourceSnapshotRepository::class);
        });

        $container->factory(CapturePageSourceSnapshot::class, static function (Container $c): CapturePageSourceSnapshot {
            return new CapturePageSourceSnapshot(
                $c->typed(SectionService::class),
                $c->typed(DesignStandardsService::class),
                $c->typed(PageJavaScriptRuntimeService::class),
                $c->typed(PageGlobalPartSelectionRepositoryInterface::class),
                $c->typed(ShellModeService::class),
            );
        });

        $container->factory(LazyPublishedSourceSnapshotMigrator::class, static function (Container $c): LazyPublishedSourceSnapshotMigrator {
            return new LazyPublishedSourceSnapshotMigrator(
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(PublishedPageArtifactRepositoryInterface::class),
                $c->typed(PageSourceSnapshotRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(CapturePageSourceSnapshot::class),
            );
        });

        $container->factory(PublishedSourceSnapshotMigrationInterface::class, static function (Container $c): PublishedSourceSnapshotMigrationInterface {
            return $c->typed(LazyPublishedSourceSnapshotMigrator::class);
        });

        $container->factory(SelectEditorPageSource::class, static function (Container $c): SelectEditorPageSource {
            return new SelectEditorPageSource(
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(PageSourceSnapshotRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(PageDraftStatusPortInterface::class),
                $c->typed(PublishedPageArtifactRepositoryInterface::class),
            );
        });

        $container->factory(RestorePublishedSourceToWorkingDraft::class, static function (Container $c): RestorePublishedSourceToWorkingDraft {
            return new RestorePublishedSourceToWorkingDraft(
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(PageSourceSnapshotRepositoryInterface::class),
                $c->typed(PageSourceMutation::class),
                $c->typed(SectionService::class),
                $c->typed(DesignStandardsService::class),
                $c->typed(PageJavaScriptRuntimeService::class),
                $c->typed(ShellModeService::class),
                $c->typed(PageGlobalPartSelectionService::class),
                $c->typed(PageDetailsPortInterface::class),
                $c->typed(OperationHistoryService::class),
            );
        });

        $container->factory(WordPressLocalFilesystem::class, static fn (): WordPressLocalFilesystem => new WordPressLocalFilesystem());
        $container->factory(LocalFileReaderInterface::class, static fn (Container $c): LocalFileReaderInterface => $c->typed(WordPressLocalFilesystem::class));
        $container->factory(LocalFilesystemPortInterface::class, static fn (Container $c): LocalFilesystemPortInterface => $c->typed(WordPressLocalFilesystem::class));

        $container->factory(PublishedPageAssetResolver::class, static function (): PublishedPageAssetResolver {
            return new PublishedPageAssetResolver(UNCANNY_PB_PATH, UNCANNY_PB_URL);
        });

        $container->factory(PublishedPageAssetResolverInterface::class, static function (Container $c): PublishedPageAssetResolverInterface {
            return $c->typed(PublishedPageAssetResolver::class);
        });

        $container->factory(WordPressUploadedFallbackAssetResolver::class, static function (Container $c): WordPressUploadedFallbackAssetResolver {
            return new WordPressUploadedFallbackAssetResolver(
                $c->typed(PublishedPageAssetResolver::class),
                UNCANNY_PB_PATH,
            );
        });

        $container->factory(PageDeactivationFallbackAssetResolverInterface::class, static function (Container $c): PageDeactivationFallbackAssetResolverInterface {
            return $c->typed(WordPressUploadedFallbackAssetResolver::class);
        });

        $container->factory(WordPressPublishedFallbackComposer::class, static function (): WordPressPublishedFallbackComposer {
            return new WordPressPublishedFallbackComposer();
        });

        $container->factory(WordPressPublicPageIdentityReader::class, static function (): WordPressPublicPageIdentityReader {
            return new WordPressPublicPageIdentityReader();
        });

        $container->factory(PublicPageIdentityReaderInterface::class, static function (Container $c): PublicPageIdentityReaderInterface {
            return $c->typed(WordPressPublicPageIdentityReader::class);
        });

        $container->factory(ReadPublishedPage::class, static function (Container $c): ReadPublishedPage {
            return new ReadPublishedPage(
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(PublishedPageArtifactRepositoryInterface::class),
                $c->typed(PublishedPageAssetResolverInterface::class),
                $c->typed(PublicPageIdentityReaderInterface::class),
            );
        });

        $container->factory(PublishedPageReaderInterface::class, static function (Container $c): PublishedPageReaderInterface {
            return $c->typed(ReadPublishedPage::class);
        });

        $container->factory(WpSettingsRepository::class, static function (): WpSettingsRepository {
            return new WpSettingsRepository();
        });

        $container->factory(WordPressFontSettings::class, static function (Container $c): WordPressFontSettings {
            return new WordPressFontSettings(
                $c->typed(WpSettingsRepository::class),
            );
        });

        $container->factory(SettingsRepositoryInterface::class, static function (Container $c): SettingsRepositoryInterface {
            return $c->typed(WpSettingsRepository::class);
        });

        $container->factory(WordPressContentTypeCatalog::class, static function (): WordPressContentTypeCatalog {
            return new WordPressContentTypeCatalog();
        });

        $container->factory(ContentTypeCatalogInterface::class, static function (Container $c): ContentTypeCatalogInterface {
            return $c->typed(WordPressContentTypeCatalog::class);
        });

        $container->factory(PageBuilderDisplayPolicy::class, static function (): PageBuilderDisplayPolicy {
            return new PageBuilderDisplayPolicy();
        });

        $container->factory(WpDesignStandardsRepository::class, static function (Container $c): WpDesignStandardsRepository {
            return new WpDesignStandardsRepository(
                $c->typed(WpSettingsRepository::class),
            );
        });

        $container->factory(WpSitePersonalizationRepository::class, static function (): WpSitePersonalizationRepository {
            return new WpSitePersonalizationRepository();
        });

        $container->factory(WpSettingsSitePersonalizationRepository::class, static function (Container $c): WpSettingsSitePersonalizationRepository {
            return new WpSettingsSitePersonalizationRepository(
                $c->typed(WpSettingsRepository::class),
            );
        });

        $container->factory(WpOptionsRepository::class, static function (): WpOptionsRepository {
            return new WpOptionsRepository();
        });

        $container->factory(OptionsPortInterface::class, static function (Container $c): OptionsPortInterface {
            return $c->typed(WpOptionsRepository::class);
        });

        $container->factory(WpPostMetaCustomJavaScriptRepository::class, static function (): WpPostMetaCustomJavaScriptRepository {
            return new WpPostMetaCustomJavaScriptRepository();
        });

        $container->factory(CustomJavaScriptRepositoryInterface::class, static function (Container $c): CustomJavaScriptRepositoryInterface {
            return $c->typed(WpPostMetaCustomJavaScriptRepository::class);
        });

        $container->factory(SitePersonalizationRepositoryInterface::class, static function (Container $c): SitePersonalizationRepositoryInterface {
            return $c->typed(WpSettingsSitePersonalizationRepository::class);
        });

        $container->factory(WpShellModeRepository::class, static function (): WpShellModeRepository {
            return new WpShellModeRepository();
        });

        $container->factory(WordPressNavigationMenuRepository::class, static function (): WordPressNavigationMenuRepository {
            return new WordPressNavigationMenuRepository();
        });

        $container->factory(NavigationMenuRepositoryInterface::class, static function (Container $c): NavigationMenuRepositoryInterface {
            return $c->typed(WordPressNavigationMenuRepository::class);
        });

        $container->factory(WordPressCanvasPort::class, static function (Container $c): WordPressCanvasPort {
            return new WordPressCanvasPort(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(SectionService::class),
                $c->typed(GlobalPartService::class),
                $c->typed(ShellModeService::class),
                $c->typed(PageDetailsPortInterface::class),
                $c->typed(GlobalSourceMutation::class),
                $c->typed(WorkingCanvasRefresherInterface::class),
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(CanvasPortInterface::class, static function (Container $c): CanvasPortInterface {
            return $c->typed(WordPressCanvasPort::class);
        });

        $container->factory(WordPressPageDetailsProjection::class, static function (Container $c): WordPressPageDetailsProjection {
            return new WordPressPageDetailsProjection(
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(PageDetailsProjectionInterface::class, static function (Container $c): PageDetailsProjectionInterface {
            return $c->typed(WordPressPageDetailsProjection::class);
        });

        $container->factory(PageDetailsService::class, static function (Container $c): PageDetailsService {
            return new PageDetailsService(
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(PageDetailsProjectionInterface::class),
                null,
                $c->typed(OperationHistoryService::class),
            );
        });

        $container->factory(PageDetailsPortInterface::class, static function (Container $c): PageDetailsPortInterface {
            return $c->typed(PageDetailsService::class);
        });

        $container->factory(PageDetailsHistoryRestorerInterface::class, static function (Container $c): PageDetailsHistoryRestorerInterface {
            return $c->typed(PageDetailsService::class);
        });

        $container->factory(WordPressPageTrashUrlPort::class, static function (Container $c): WordPressPageTrashUrlPort {
            return new WordPressPageTrashUrlPort(
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(PageTrashUrlPortInterface::class, static function (Container $c): PageTrashUrlPortInterface {
            return $c->typed(WordPressPageTrashUrlPort::class);
        });

        $container->factory(AttachReusableToCanvasUseCase::class, static function (Container $c): AttachReusableToCanvasUseCase {
            return new AttachReusableToCanvasUseCase($c->typed(CanvasPortInterface::class));
        });

        $container->factory(WordPressReusablePort::class, static function (Container $c): WordPressReusablePort {
            return new WordPressReusablePort(
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(GlobalPartService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(GlobalSourceMutation::class),
            );
        });

        $container->factory(ReusablePortInterface::class, static function (Container $c): ReusablePortInterface {
            return $c->typed(WordPressReusablePort::class);
        });

        // ── Infrastructure / Section ──────────────────────
        $container->factory(DomSectionManifestExtractor::class, static function (): DomSectionManifestExtractor {
            return new DomSectionManifestExtractor();
        });

        $container->factory(DomSectionBindingContractInspector::class, static function (Container $c): DomSectionBindingContractInspector {
            return new DomSectionBindingContractInspector(
                $c->typed(DomSectionManifestExtractor::class),
                $c->typed(BindingRegistry::class),
            );
        });

        $container->factory(ComponentCategoryClassifier::class, static function (): ComponentCategoryClassifier {
            return new ComponentCategoryClassifier();
        });

        $container->factory(CssRulePatcher::class, static function (): CssRulePatcher {
            return new CssRulePatcher();
        });

        // ── Binding Registry (loads bindings/*/declaration.json) ──
        $container->factory(BindingRegistry::class, static function (): BindingRegistry {
            $loader = new BindingLoader();
            $registry = new BindingRegistry(
                $loader->load(UNCANNY_PB_PATH . 'bindings')
            );
            BindingSchema::init($registry);
            return $registry;
        });

        // ── Infrastructure / Rendering ────────────────────
        $container->factory(DynamicRenderer::class, static function (Container $c): DynamicRenderer {
            return new DynamicRenderer($c->typed(BindingRegistry::class));
        });

        $container->factory(ShortcodeBindingNormalizer::class, static function (): ShortcodeBindingNormalizer {
            return new ShortcodeBindingNormalizer();
        });

        $container->factory(WpPageGlobalPartResolver::class, static function (Container $c): WpPageGlobalPartResolver {
            return new WpPageGlobalPartResolver(
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(PageGlobalPartSelectionRepositoryInterface::class),
            );
        });

        $container->factory(CanvasRenderer::class, static function (Container $c): CanvasRenderer {
            return new CanvasRenderer(
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(DynamicRenderer::class),
                UNCANNY_PB_PATH,
                $c->typed(ShellModeService::class),
                $c->typed(WpPageGlobalPartResolver::class),
                $c->typed(ShortcodeBindingNormalizer::class),
                $c->typed(PageJavaScriptRuntimeRenderer::class),
                pageSources: $c->typed(SelectEditorPageSource::class),
                compiler: $c->typed(ShadowCompiler::class),
                globalPartDefaults: $c->typed(GlobalPartDefaultsService::class),
                resolveEmptyCanvasInvitation: $c->typed(ResolveEmptyCanvasInvitation::class),
                agentSetupUrl: $c->typed(AutomatorSetupWizardUrl::class),
            );
        });

        $container->factory(PublishedCanvasRenderer::class, static function (Container $c): PublishedCanvasRenderer {
            return new PublishedCanvasRenderer(
                $c->typed(PublicPageRenderPolicy::class),
                UNCANNY_PB_PATH,
                $c->typed(DynamicRenderer::class),
                $c->typed(OriginalPageContentReaderInterface::class),
            );
        });

        $container->factory(CanvasGlobalPartRendererInterface::class, static function (Container $c): CanvasGlobalPartRendererInterface {
            return $c->typed(CanvasRenderer::class);
        });

        $container->factory(CanvasRefreshRendererInterface::class, static function (Container $c): CanvasRefreshRendererInterface {
            return new CanvasRefreshRenderer(
                $c->typed(CanvasRenderer::class),
                $c->typed(PageJavaScriptRuntimeRenderer::class),
            );
        });

        $container->factory(CanvasGlobalPartsService::class, static function (Container $c): CanvasGlobalPartsService {
            return new CanvasGlobalPartsService(
                $c->typed(ShellModeService::class),
                $c->typed(WpPageGlobalPartResolver::class),
                $c->typed(CanvasGlobalPartRendererInterface::class),
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(GlobalPartDefaultsService::class),
            );
        });

        $container->factory(CanvasGlobalPartsProviderInterface::class, static function (Container $c): CanvasGlobalPartsProviderInterface {
            return $c->typed(CanvasGlobalPartsService::class);
        });

        // ── Infrastructure / Automator ────────────────────
        $container->factory(BearerAuthenticator::class, static function (): BearerAuthenticator {
            return new BearerAuthenticator();
        });

        // ── Infrastructure / Access Policy ───────────────
        $container->factory(WordPressPageBuilderAllowedCapabilityPort::class, static function (): WordPressPageBuilderAllowedCapabilityPort {
            return new WordPressPageBuilderAllowedCapabilityPort();
        });

        $container->factory(PageBuilderAllowedCapabilityPort::class, static function (Container $c): PageBuilderAllowedCapabilityPort {
            return $c->typed(WordPressPageBuilderAllowedCapabilityPort::class);
        });

        $container->factory(AutomatorPageBuilderAvailability::class, static function (): AutomatorPageBuilderAvailability {
            return new AutomatorPageBuilderAvailability();
        });

        $container->factory(PageBuilderAvailabilityInterface::class, static function (Container $c): PageBuilderAvailabilityInterface {
            return $c->typed(AutomatorPageBuilderAvailability::class);
        });

        $container->factory(AutomatorAgentAuthoringAvailability::class, static function (): AutomatorAgentAuthoringAvailability {
            return new AutomatorAgentAuthoringAvailability();
        });

        $container->factory(AgentAuthoringAvailabilityInterface::class, static function (Container $c): AgentAuthoringAvailabilityInterface {
            return $c->typed(AutomatorAgentAuthoringAvailability::class);
        });

        $container->factory(ResolveEmptyCanvasInvitation::class, static function (Container $c): ResolveEmptyCanvasInvitation {
            return new ResolveEmptyCanvasInvitation(
                $c->typed(AgentAuthoringAvailabilityInterface::class),
            );
        });

        $container->factory(AutomatorSetupWizardUrl::class, static function (): AutomatorSetupWizardUrl {
            return new AutomatorSetupWizardUrl();
        });

        // ── Infrastructure / Static Export ──────────────────
        $container->factory(StaticExportAssetSourceInterface::class, static function (): StaticExportAssetSourceInterface {
            return new PluginStaticExportAssetSource(UNCANNY_PB_PATH);
        });

        $container->factory(StaticExportHtmlRendererInterface::class, static function (Container $c): StaticExportHtmlRendererInterface {
            return new CanvasStaticExportHtmlRenderer(
                $c->typed(CanvasRenderer::class),
                $c->typed(ShortcodeBindingNormalizer::class),
            );
        });

        $container->factory(StaticExportGlobalPartResolverInterface::class, static function (Container $c): StaticExportGlobalPartResolverInterface {
            return new WordPressStaticExportGlobalPartResolver(
                $c->typed(WpPageGlobalPartResolver::class),
                $c->typed(ShellModeService::class),
            );
        });

        $container->factory(StaticExportContextProviderInterface::class, static function (Container $c): StaticExportContextProviderInterface {
            return new WordPressStaticExportContextProvider(
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(PageGlobalPartSelectionRepositoryInterface::class),
                $c->typed(WpSettingsRepository::class),
            );
        });

        // ── Infrastructure / Other ────────────────────────
        $container->factory(WpThemeEnvironment::class, static function (): WpThemeEnvironment {
            return new WpThemeEnvironment();
        });

        $container->factory(RenderedShellAnalyzer::class, static function (): RenderedShellAnalyzer {
            return new RenderedShellAnalyzer();
        });

        $container->factory(OwnedPageFinderInterface::class, static function (Container $c): OwnedPageFinderInterface {
            return new WpOwnedPageFinder(
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(WpCronWorkingCanvasRefreshQueue::class, static function (): WpCronWorkingCanvasRefreshQueue {
            return new WpCronWorkingCanvasRefreshQueue();
        });

        $container->factory(WorkingCanvasRefreshQueueInterface::class, static function (Container $c): WorkingCanvasRefreshQueueInterface {
            return $c->typed(WpCronWorkingCanvasRefreshQueue::class);
        });

        $container->factory(WorkingCanvasRefreshScheduler::class, static function (Container $c): WorkingCanvasRefreshScheduler {
            return new WorkingCanvasRefreshScheduler(
                $c->typed(OwnedPageFinderInterface::class),
                $c->typed(WorkingCanvasRefreshQueueInterface::class),
            );
        });

        $container->factory(WordPressSectionPostCommitFailureReporter::class, static function (): WordPressSectionPostCommitFailureReporter {
            return new WordPressSectionPostCommitFailureReporter();
        });

        $container->factory(SectionPostCommitFailureReporterInterface::class, static function (Container $c): SectionPostCommitFailureReporterInterface {
            return $c->typed(WordPressSectionPostCommitFailureReporter::class);
        });

        $container->factory(WordPressFailureReporter::class, static function (): WordPressFailureReporter {
            return new WordPressFailureReporter();
        });

        $container->factory(FailureReporterInterface::class, static function (Container $c): FailureReporterInterface {
            return $c->typed(WordPressFailureReporter::class);
        });

        // ── Domain ────────────────────────────────────────
        $container->factory(CssMinifier::class, static function (): CssMinifier {
            return new CssMinifier();
        });

        $container->factory(ShadowCompiler::class, static function (Container $c): ShadowCompiler {
            return new ShadowCompiler(
                $c->typed(CssMinifier::class),
            );
        });

        // ── Application Services ──────────────────────────
        $container->factory(ShellModeService::class, static function (Container $c): ShellModeService {
            return new ShellModeService(
                $c->typed(WpShellModeRepository::class),
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(WpThemeEnvironment::class),
                $c->typed(WorkingCanvasRefreshScheduler::class),
                $c->typed(WpPageGlobalPartResolver::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(PageSourceMutation::class),
                $c->typed(FailureReporterInterface::class),
            );
        });

        $container->factory(GlobalPartDefaultsService::class, static function (Container $c): GlobalPartDefaultsService {
            return new GlobalPartDefaultsService(
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(SettingsRepositoryInterface::class),
                $c->typed(GlobalSourceMutation::class),
                $c->typed(WorkingCanvasRefreshScheduler::class),
                $c->typed(FailureReporterInterface::class),
            );
        });

        $container->factory(PageGlobalPartSelectionService::class, static function (Container $c): PageGlobalPartSelectionService {
            return new PageGlobalPartSelectionService(
                $c->typed(PageGlobalPartSelectionRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(PageSourceMutation::class),
            );
        });

        $container->factory(UpdatePageLayout::class, static function (Container $c): UpdatePageLayout {
            return new UpdatePageLayout(
                $c->typed(PageSourceMutation::class),
                $c->typed(ShellModeService::class),
                $c->typed(PageGlobalPartSelectionService::class),
            );
        });

        $container->factory(LoadSettingsUseCase::class, static function (Container $c): LoadSettingsUseCase {
            return new LoadSettingsUseCase(
                $c->typed(SettingsRepositoryInterface::class),
            );
        });

        $container->factory(ListContentTypesUseCase::class, static function (Container $c): ListContentTypesUseCase {
            return new ListContentTypesUseCase(
                $c->typed(ContentTypeCatalogInterface::class),
                $c->typed(PageBuilderDisplayPolicy::class),
                $c->typed(SettingsRepositoryInterface::class),
            );
        });

        $container->factory(ListDisplayableContentTypesUseCase::class, static function (Container $c): ListDisplayableContentTypesUseCase {
            return new ListDisplayableContentTypesUseCase(
                $c->typed(SettingsRepositoryInterface::class),
                $c->typed(ContentTypeCatalogInterface::class),
                $c->typed(PageBuilderDisplayPolicy::class),
            );
        });

        $container->factory(ListEnabledContentTypesUseCase::class, static function (Container $c): ListEnabledContentTypesUseCase {
            return new ListEnabledContentTypesUseCase(
                $c->typed(SettingsRepositoryInterface::class),
                $c->typed(PageBuilderDisplayPolicy::class),
            );
        });

        $container->factory(ValidateContentTypeSelectionUseCase::class, static function (Container $c): ValidateContentTypeSelectionUseCase {
            return new ValidateContentTypeSelectionUseCase(
                $c->typed(PageBuilderDisplayPolicy::class),
            );
        });

        $container->factory(SaveContentTypeSettingsUseCase::class, static function (Container $c): SaveContentTypeSettingsUseCase {
            return new SaveContentTypeSettingsUseCase(
                $c->typed(SettingsRepositoryInterface::class),
                $c->typed(ValidateContentTypeSelectionUseCase::class),
            );
        });

        $container->factory(SaveBrandStylesSettingsUseCase::class, static function (Container $c): SaveBrandStylesSettingsUseCase {
            return new SaveBrandStylesSettingsUseCase(
                $c->typed(SettingsRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
            );
        });

        $container->factory(SaveLogoSettingsUseCase::class, static function (Container $c): SaveLogoSettingsUseCase {
            return new SaveLogoSettingsUseCase(
                $c->typed(SettingsRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
            );
        });

        $container->factory(SaveFontSettingsUseCase::class, static function (Container $c): SaveFontSettingsUseCase {
            return new SaveFontSettingsUseCase(
                $c->typed(SettingsRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
            );
        });

        $container->factory(SaveDesignDirectionSettingsUseCase::class, static function (Container $c): SaveDesignDirectionSettingsUseCase {
            return new SaveDesignDirectionSettingsUseCase(
                $c->typed(SettingsRepositoryInterface::class),
            );
        });

        $container->factory(SavePageLayoutSettingsUseCase::class, static function (Container $c): SavePageLayoutSettingsUseCase {
            return new SavePageLayoutSettingsUseCase(
                $c->typed(SettingsRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
            );
        });

        $container->factory(SaveToolSettingsUseCase::class, static function (Container $c): SaveToolSettingsUseCase {
            return new SaveToolSettingsUseCase(
                $c->typed(SettingsRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
            );
        });

        $container->factory(ToolSettingsAccess::class, static function (Container $c): ToolSettingsAccess {
            return new ToolSettingsAccess(
                $c->typed(LoadSettingsUseCase::class),
            );
        });

        $container->factory(DesignStandardsService::class, static function (Container $c): DesignStandardsService {
            return new DesignStandardsService(
                $c->typed(WpDesignStandardsRepository::class),
                $c->typed(WorkingCanvasRefreshScheduler::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(PageSourceMutation::class),
            );
        });

        $container->factory(SitePersonalizationService::class, static function (Container $c): SitePersonalizationService {
            return new SitePersonalizationService(
                $c->typed(SitePersonalizationRepositoryInterface::class),
            );
        });

        $container->factory(BindingContractReplacementService::class, static function (Container $c): BindingContractReplacementService {
            return new BindingContractReplacementService(
                $c->typed(DomSectionBindingContractInspector::class),
            );
        });

        $container->factory(ExactSourcePatcher::class, static function (): ExactSourcePatcher {
            return new ExactSourcePatcher();
        });

        $container->factory(HtmlBridgeArtifactCleanerAdapter::class, static function (): HtmlBridgeArtifactCleanerAdapter {
            return new HtmlBridgeArtifactCleanerAdapter();
        });

        $container->factory(CompactSourceDiffer::class, static function (): CompactSourceDiffer {
            return new CompactSourceDiffer();
        });

        $container->factory(HtmlCssProcessor::class, static function (Container $c): HtmlCssProcessor {
            return new HtmlCssProcessor(
                $c->typed(BindingRegistry::class),
                $c->typed(ExactSourcePatcher::class),
                $c->typed(HtmlBridgeArtifactCleanerAdapter::class),
            );
        });

        $container->factory(WordPressSectionSourceSanitizer::class, static function (): WordPressSectionSourceSanitizer {
            return new WordPressSectionSourceSanitizer();
        });

        $container->factory(SectionSourceSanitizerInterface::class, static function (Container $c): SectionSourceSanitizerInterface {
            return $c->typed(WordPressSectionSourceSanitizer::class);
        });

        $container->factory(StaticLucideIconCatalog::class, static function (): StaticLucideIconCatalog {
            return new StaticLucideIconCatalog();
        });

        $container->factory(LucideIconFinder::class, static function (Container $c): LucideIconFinder {
            return new LucideIconFinder(
                $c->typed(StaticLucideIconCatalog::class),
            );
        });

        $container->factory(LucideIconValidator::class, static function (Container $c): LucideIconValidator {
            return new LucideIconValidator(
                $c->typed(StaticLucideIconCatalog::class),
            );
        });

        $container->factory(SectionService::class, static function (Container $c): SectionService {
            return new SectionService(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(ShadowCompiler::class),
                $c->typed(BindingContractReplacementService::class),
                $c->typed(DomSectionManifestExtractor::class),
                $c->typed(SectionSourceSanitizerInterface::class),
                $c->typed(HtmlCssProcessor::class),
                new \UncannyPageBuilder\Infrastructure\WordPress\WpSectionEventDispatcher(),
                $c->typed(OperationHistoryService::class),
                $c->typed(WorkingCanvasRefresherInterface::class),
                $c->typed(LucideIconValidator::class),
                $c->typed(WorkingCanvasRefreshQueueInterface::class),
                $c->typed(SectionPostCommitFailureReporterInterface::class),
            );
        });

        $container->factory(OperationHistoryService::class, static function (Container $c): OperationHistoryService {
            return new OperationHistoryService(
                $c->typed(OperationHistoryRepositoryInterface::class),
                $c->typed(PageSourceMutation::class),
            );
        });

        $container->factory(SectionHistoryRestorerInterface::class, static function (Container $c): SectionHistoryRestorerInterface {
            return $c->typed(SectionService::class);
        });

        // ── Application / Access Policy ──────────────────
        $container->factory(GetPageBuilderAllowedCapabilities::class, static function (Container $c): GetPageBuilderAllowedCapabilities {
            return new GetPageBuilderAllowedCapabilities(
                $c->typed(PageBuilderAllowedCapabilityPort::class),
            );
        });

        $container->factory(EditableUpdateService::class, static function (Container $c): EditableUpdateService {
            return new EditableUpdateService(
                $c->typed(SectionService::class),
                $c->typed(EditableHtmlMutator::class),
            );
        });

        $container->factory(CreateCanvasUseCase::class, static function (Container $c): CreateCanvasUseCase {
            return new CreateCanvasUseCase(
                $c->typed(CanvasPortInterface::class),
                $c->typed(PageBuilderAvailabilityInterface::class),
            );
        });

        $container->factory(AdoptPageUseCase::class, static function (Container $c): AdoptPageUseCase {
            return new AdoptPageUseCase(
                $c->typed(PageOwnershipRepositoryInterface::class),
                $c->typed(OriginalPageContentStoreInterface::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(ShellModeService::class),
                $c->typed(WorkingCanvasRefresherInterface::class),
                $c->typed(PageDetailsPortInterface::class),
                $c->typed(PageBuilderAvailabilityInterface::class),
            );
        });

        $container->factory(ReturnPageToWordPressUseCase::class, static function (Container $c): ReturnPageToWordPressUseCase {
            return new ReturnPageToWordPressUseCase(
                $c->typed(ReturnPageToWordPressTransitionInterface::class),
            );
        });

        $container->factory(EditCanvasUseCase::class, static function (Container $c): EditCanvasUseCase {
            return new EditCanvasUseCase(
                $c->typed(CanvasPortInterface::class),
            );
        });

        $container->factory(DeleteCanvasUseCase::class, static function (Container $c): DeleteCanvasUseCase {
            return new DeleteCanvasUseCase(
                $c->typed(CanvasPortInterface::class),
            );
        });

        $container->factory(ListCanvasUseCase::class, static function (Container $c): ListCanvasUseCase {
            return new ListCanvasUseCase(
                $c->typed(CanvasPortInterface::class),
            );
        });

        $container->factory(CreateReusableUseCase::class, static function (Container $c): CreateReusableUseCase {
            return new CreateReusableUseCase(
                $c->typed(ReusablePortInterface::class),
            );
        });

        $container->factory(ConvertSectionToReusableUseCase::class, static function (Container $c): ConvertSectionToReusableUseCase {
            return new ConvertSectionToReusableUseCase(
                $c->typed(ReusablePortInterface::class),
            );
        });

        $container->factory(UpdateReusableUseCase::class, static function (Container $c): UpdateReusableUseCase {
            return new UpdateReusableUseCase(
                $c->typed(ReusablePortInterface::class),
            );
        });

        $container->factory(DeleteReusableUseCase::class, static function (Container $c): DeleteReusableUseCase {
            return new DeleteReusableUseCase(
                $c->typed(ReusablePortInterface::class),
            );
        });

        $container->factory(ListReusableUseCase::class, static function (Container $c): ListReusableUseCase {
            return new ListReusableUseCase(
                $c->typed(ReusablePortInterface::class),
            );
        });

        $container->factory(EditableHtmlMutator::class, static function (): EditableHtmlMutator {
            return new EditableHtmlMutator();
        });

        $container->factory(GlobalPartEditableUpdateService::class, static function (Container $c): GlobalPartEditableUpdateService {
            return new GlobalPartEditableUpdateService(
                $c->typed(GlobalPartService::class),
                $c->typed(EditableHtmlMutator::class),
            );
        });

        $container->factory(SectionNodeHtmlMutator::class, static function (): SectionNodeHtmlMutator {
            return new SectionNodeHtmlMutator();
        });

        $container->factory(GlobalPartNodeUpdateService::class, static function (Container $c): GlobalPartNodeUpdateService {
            return new GlobalPartNodeUpdateService(
                $c->typed(GlobalPartService::class),
                $c->typed(SectionNodeHtmlMutator::class),
            );
        });

        $container->factory(NavigationMenuService::class, static function (Container $c): NavigationMenuService {
            return new NavigationMenuService(
                $c->typed(NavigationMenuRepositoryInterface::class),
            );
        });

        $container->factory(GlobalPartService::class, static function (Container $c): GlobalPartService {
            return new GlobalPartService(
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(ShadowCompiler::class),
                $c->typed(BindingContractReplacementService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(WorkingCanvasRefreshScheduler::class),
                $c->typed(LucideIconValidator::class),
                $c->typed(FailureReporterInterface::class),
            );
        });

        $container->factory(ShellImportService::class, static function (Container $c): ShellImportService {
            return new ShellImportService(
                $c->typed(RenderedShellAnalyzer::class),
                $c->typed(GlobalPartService::class),
                $c->typed(FailureReporterInterface::class),
            );
        });

        $container->factory(EditorStateService::class, static function (Container $c): EditorStateService {
            return new EditorStateService(
                $c->typed(SectionService::class),
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(ShellModeService::class),
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(DesignStandardsService::class),
                $c->typed(PageDetailsPortInterface::class),
                $c->typed(SelectEditorPageSource::class),
                $c->typed(PageGlobalPartSelectionService::class),
                $c->typed(PublishedSourceSnapshotMigrationInterface::class),
            );
        });

        $container->factory(PageJavaScriptRuntimeService::class, static function (Container $c): PageJavaScriptRuntimeService {
            return new PageJavaScriptRuntimeService(
                $c->typed(CustomJavaScriptRepositoryInterface::class),
                $c->typed(WorkingCanvasRefreshScheduler::class),
                $c->typed(ExactSourcePatcher::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(GlobalSourceMutation::class),
                $c->typed(PageSourceMutation::class),
                $c->typed(FailureReporterInterface::class),
            );
        });

        $container->factory(PageJavaScriptRuntimeRenderer::class, static function (Container $c): PageJavaScriptRuntimeRenderer {
            return new PageJavaScriptRuntimeRenderer(
                $c->typed(PageJavaScriptRuntimeService::class),
                $c->typed(ToolSettingsAccess::class),
            );
        });

        $container->factory(PageJavaScriptExportRendererInterface::class, static function (Container $c): PageJavaScriptExportRendererInterface {
            return $c->typed(PageJavaScriptRuntimeRenderer::class);
        });

        $container->factory(StaticPageExportService::class, static function (Container $c): StaticPageExportService {
            return new StaticPageExportService(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(ShadowCompiler::class),
                $c->typed(DesignStandardsService::class),
                $c->typed(StaticExportHtmlRendererInterface::class),
                $c->typed(StaticExportAssetSourceInterface::class),
                $c->typed(StaticExportGlobalPartResolverInterface::class),
                new StaticRenderingPolicy($c->typed(BindingRegistry::class)),
                $c->typed(StaticExportContextProviderInterface::class),
                $c->typed(PageJavaScriptExportRendererInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
            );
        });

        $container->factory(StaticPageExportBuilderInterface::class, static function (Container $c): StaticPageExportBuilderInterface {
            return $c->typed(StaticPageExportService::class);
        });

        // ── Working canvas and explicit publication ───────────────────────
        $container->factory(RefreshWorkingCanvas::class, static function (Container $c): RefreshWorkingCanvas {
            return new RefreshWorkingCanvas(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(ShadowCompiler::class),
                $c->typed(SourceGenerationStoreInterface::class),
            );
        });

        $container->factory(WorkingCanvasRefresherInterface::class, static function (Container $c): WorkingCanvasRefresherInterface {
            return $c->typed(RefreshWorkingCanvas::class);
        });

        $container->factory(BuildPageArtifact::class, static function (Container $c): BuildPageArtifact {
            return new BuildPageArtifact(
                $c->typed(StaticPageExportBuilderInterface::class),
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(ShellModeService::class),
                $c->typed(PageDetailsProjectionInterface::class),
                $c->typed(SectionService::class),
                $c->typed(DesignStandardsService::class),
                $c->typed(PageJavaScriptRuntimeService::class),
                $c->typed(PageGlobalPartSelectionRepositoryInterface::class),
                $c->typed(CapturePageSourceSnapshot::class),
            );
        });

        $container->factory(PageArtifactBuilderInterface::class, static function (Container $c): PageArtifactBuilderInterface {
            return $c->typed(BuildPageArtifact::class);
        });

        $container->factory(WordPressPagePublicationAuthorizer::class, static function (Container $c): WordPressPagePublicationAuthorizer {
            return new WordPressPagePublicationAuthorizer(
                $c->typed(PermissionChecker::class),
                $c->typed(PageOwnershipRepositoryInterface::class),
            );
        });

        $container->factory(PagePublicationAuthorizerInterface::class, static function (Container $c): PagePublicationAuthorizerInterface {
            return $c->typed(WordPressPagePublicationAuthorizer::class);
        });

        $container->factory(WordPressPagePublisher::class, static function (Container $c): WordPressPagePublisher {
            return new WordPressPagePublisher(
                $c->typed(PublishedPageArtifactRepositoryInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
                sourceSnapshots: $c->typed(PageSourceSnapshotRepositoryInterface::class),
                themeTemplates: $c->typed(ThemeCompositionPageTemplateSynchronizerInterface::class),
                supportsPostType: $c->typed(SupportsPostTypeUseCase::class),
                fallbackAssets: $c->typed(PageDeactivationFallbackAssetResolverInterface::class),
                fallbackComposer: $c->typed(WordPressPublishedFallbackComposer::class),
                originalContent: $c->typed(WpOriginalPageContentStore::class),
            );
        });

        $container->factory(PagePublisherInterface::class, static function (Container $c): PagePublisherInterface {
            return $c->typed(WordPressPagePublisher::class);
        });

        $container->factory(PublishPage::class, static function (Container $c): PublishPage {
            return new PublishPage(
                $c->typed(PagePublicationAuthorizerInterface::class),
                $c->typed(PageArtifactBuilderInterface::class),
                $c->typed(PagePublisherInterface::class),
            );
        });

        $container->factory(WordPressPageDraftStatusPort::class, static fn (): WordPressPageDraftStatusPort => new WordPressPageDraftStatusPort());
        $container->factory(PageDraftStatusPortInterface::class, static function (Container $c): PageDraftStatusPortInterface {
            return $c->typed(WordPressPageDraftStatusPort::class);
        });
        $container->factory(SwitchPageToDraft::class, static function (Container $c): SwitchPageToDraft {
            return new SwitchPageToDraft(
                $c->typed(PageDraftStatusPortInterface::class),
            );
        });
        $container->factory(SwitchPageToDraftInterface::class, static function (Container $c): SwitchPageToDraftInterface {
            return $c->typed(SwitchPageToDraft::class);
        });

        $container->factory(PageSourcePackageService::class, static function (Container $c): PageSourcePackageService {
            return new PageSourcePackageService(
                $c->typed(SectionService::class),
                $c->typed(DesignStandardsService::class),
                $c->typed(PageJavaScriptRuntimeService::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(WorkingCanvasRefresherInterface::class),
            );
        });
        $container->factory(WordPressPageSourceImageCollector::class, static fn (): WordPressPageSourceImageCollector => new WordPressPageSourceImageCollector());
        $container->factory(PageSourceImageCollectorInterface::class, static fn (Container $c): PageSourceImageCollectorInterface => $c->typed(WordPressPageSourceImageCollector::class));
        $container->factory(ZipPageSourceArchiveWriter::class, static fn (): ZipPageSourceArchiveWriter => new ZipPageSourceArchiveWriter());
        $container->factory(PageSourceArchiveWriterInterface::class, static fn (Container $c): PageSourceArchiveWriterInterface => $c->typed(ZipPageSourceArchiveWriter::class));
        $container->factory(WordPressPageSourceImageImporter::class, static fn (): WordPressPageSourceImageImporter => new WordPressPageSourceImageImporter());
        $container->factory(PageSourceImageImporterInterface::class, static fn (Container $c): PageSourceImageImporterInterface => $c->typed(WordPressPageSourceImageImporter::class));
        $container->factory(PageSourceImageUrlRewriter::class, static fn (): PageSourceImageUrlRewriter => new PageSourceImageUrlRewriter());
        $container->factory(PageSourceArchiveService::class, static function (Container $c): PageSourceArchiveService {
            return new PageSourceArchiveService(
                $c->typed(PageSourcePackageService::class),
                $c->typed(PageSourceImageCollectorInterface::class),
                $c->typed(PageSourceArchiveWriterInterface::class),
                $c->typed(PageSourceImageImporterInterface::class),
                $c->typed(PageSourceImageUrlRewriter::class),
                $c->typed(SourceGenerationStoreInterface::class),
            );
        });

        $container->factory(ReusableSourcePackageService::class, static function (Container $c): ReusableSourcePackageService {
            return new ReusableSourcePackageService(
                $c->typed(GlobalPartService::class),
                $c->typed(PageJavaScriptRuntimeService::class),
                $c->typed(DatabaseGlobalPartRepository::class),
            );
        });

        $container->factory(ReadPageLiveState::class, static function (Container $c): ReadPageLiveState {
            return new ReadPageLiveState(
                $c->typed(PublishedPageReaderInterface::class),
                $c->typed(\UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface::class),
            );
        });
        $container->factory(PageLiveStateReaderInterface::class, static fn (Container $c): PageLiveStateReaderInterface => $c->typed(ReadPageLiveState::class));

        // ── API ───────────────────────────────────────────
        $container->factory(PermissionChecker::class, static function (Container $c): PermissionChecker {
            return new PermissionChecker(
                $c->typed(BearerAuthenticator::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(SupportsPostTypeUseCase::class),
                $c->typed(FailureReporterInterface::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        /*
         * This gate intentionally runs before REST routes and public render
         * hooks. If a required WordPress table is not transactional, Page
         * Builder does not serve even an existing artifact. The host can then
         * keep the WordPress body fallback without opening a partly booted
         * runtime against an unsafe persistence boundary.
         */
        $container->typed(SchemaInstallerInterface::class)->ensureCurrentSite();
        (new WpDynamicContentConfigProvider())->register();
    }
}
