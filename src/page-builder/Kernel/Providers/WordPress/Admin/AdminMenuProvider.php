<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\WordPress\Admin;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\EditorLock\EnterEditorOwnership;
use UncannyPageBuilder\Application\EditorLock\InspectEditorOwnership;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\EditorLock\RefreshEditorOwnership;
use UncannyPageBuilder\Application\EditorLock\TakeOverEditorOwnership;
use UncannyPageBuilder\Application\GetAvailableFontFamilies;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\Settings\LoadSettingsUseCase;
use UncannyPageBuilder\Application\Settings\ListContentTypesUseCase;
use UncannyPageBuilder\Application\Settings\SaveDesignDirectionSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveContentTypeSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveFontSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveLogoSettingsUseCase;
use UncannyPageBuilder\Application\Settings\SaveToolSettingsUseCase;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Application\SourcePackage\PageSourcePackageService;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveArtifactStoreInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveDownloadUrlInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveService;
use UncannyPageBuilder\Domain\Canvas\PageOwnershipRepositoryInterface;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Infrastructure\Rendering\CanvasRenderer;
use UncannyPageBuilder\Infrastructure\WordPress\AdminBarButton;
use UncannyPageBuilder\Infrastructure\WordPress\AdminBrandingPage;
use UncannyPageBuilder\Infrastructure\WordPress\AdminContentTypesPage;
use UncannyPageBuilder\Infrastructure\WordPress\AdminCanvasPage;
use UncannyPageBuilder\Infrastructure\WordPress\AdminCanvasEditorWindowedGlobalPartPage;
use UncannyPageBuilder\Infrastructure\WordPress\AdminCanvasEditorWindowedPage;
use UncannyPageBuilder\Infrastructure\WordPress\AdminMenu;
use UncannyPageBuilder\Infrastructure\WordPress\AdminPersonalizationPage;
use UncannyPageBuilder\Infrastructure\WordPress\AdminSettingsJavaScriptPage;
use UncannyPageBuilder\Infrastructure\WordPress\AdminSettingsPage;
use UncannyPageBuilder\Infrastructure\WordPress\AdminSettingsToolsPage;
use UncannyPageBuilder\Infrastructure\WordPress\CanvasAdminBar;
use UncannyPageBuilder\Infrastructure\WordPress\CanvasAssetAllowlist;
use UncannyPageBuilder\Infrastructure\WordPress\EditorLockDialogRenderer;
use UncannyPageBuilder\Infrastructure\WordPress\EditorLockHeartbeat;
use UncannyPageBuilder\Infrastructure\WordPress\EditorLockTakeoverAction;
use UncannyPageBuilder\Infrastructure\WordPress\MagicBridgeEnqueuer;
use UncannyPageBuilder\Infrastructure\WordPress\NativePageListPresenter;
use UncannyPageBuilder\Infrastructure\WordPress\PageFactory;
use UncannyPageBuilder\Infrastructure\WordPress\PageSourceArchiveDownloadAction;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageSourceArchiveArtifactStore;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPageSourceArchiveDownloadUrl;
use UncannyPageBuilder\Infrastructure\WordPress\RestNonceRefresher;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressCallbackBoundary;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressFontFamilyCatalogSource;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressFontSettings;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;

final class AdminMenuProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->factory(WordPressPageSourceArchiveArtifactStore::class, static fn (): WordPressPageSourceArchiveArtifactStore => new WordPressPageSourceArchiveArtifactStore());
        $container->factory(PageSourceArchiveArtifactStoreInterface::class, static fn (Container $c): PageSourceArchiveArtifactStoreInterface => $c->typed(WordPressPageSourceArchiveArtifactStore::class));
        $container->factory(WordPressPageSourceArchiveDownloadUrl::class, static fn (): WordPressPageSourceArchiveDownloadUrl => new WordPressPageSourceArchiveDownloadUrl());
        $container->factory(PageSourceArchiveDownloadUrlInterface::class, static fn (Container $c): PageSourceArchiveDownloadUrlInterface => $c->typed(WordPressPageSourceArchiveDownloadUrl::class));
        $container->factory(PageSourceArchiveDownloadAction::class, static function (Container $c): PageSourceArchiveDownloadAction {
            return new PageSourceArchiveDownloadAction(
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PageSourceArchiveArtifactStoreInterface::class),
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });
        $container->factory(AdminSettingsPage::class, static function (Container $c): AdminSettingsPage {
            return new AdminSettingsPage(
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
            );
        });

        $container->factory(AdminContentTypesPage::class, static function (Container $c): AdminContentTypesPage {
            return new AdminContentTypesPage(
                $c->typed(ListContentTypesUseCase::class),
                $c->typed(SaveContentTypeSettingsUseCase::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
            );
        });

        $container->factory(GetAvailableFontFamilies::class, static function (Container $c): GetAvailableFontFamilies {
            return new GetAvailableFontFamilies(new WordPressFontFamilyCatalogSource(
                $c->typed(WordPressFontSettings::class),
            ));
        });

        $container->factory(AdminBrandingPage::class, static function (Container $c): AdminBrandingPage {
            return new AdminBrandingPage(
                $c->typed(LoadSettingsUseCase::class),
                $c->typed(SaveLogoSettingsUseCase::class),
                $c->typed(SaveFontSettingsUseCase::class),
                $c->typed(DesignStandardsService::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(GetAvailableFontFamilies::class),
            );
        });

        $container->factory(AdminPersonalizationPage::class, static function (Container $c): AdminPersonalizationPage {
            return new AdminPersonalizationPage(
                $c->typed(LoadSettingsUseCase::class),
                $c->typed(SaveDesignDirectionSettingsUseCase::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
            );
        });

        $container->factory(AdminSettingsJavaScriptPage::class, static function (Container $c): AdminSettingsJavaScriptPage {
            return new AdminSettingsJavaScriptPage(
                $c->typed(LoadSettingsUseCase::class),
                $c->typed(SaveToolSettingsUseCase::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
            );
        });

        $container->factory(AdminSettingsToolsPage::class, static function (Container $c): AdminSettingsToolsPage {
            return new AdminSettingsToolsPage(
                $c->typed(\UncannyPageBuilder\Infrastructure\WordPress\WorkingCanvasAdminActions::class),
                $c->typed(\UncannyPageBuilder\Infrastructure\WordPress\WpCronWorkingCanvasRefreshQueue::class),
            );
        });

        $container->factory(AdminMenu::class, static function (Container $c): AdminMenu {
            $menu = new AdminMenu(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(PageBuilderAvailabilityInterface::class),
            );
            $menu->setSettingsPage($c->typed(AdminSettingsPage::class));
            $menu->setContentTypesPage($c->typed(AdminContentTypesPage::class));
            $menu->setJavaScriptPage($c->typed(AdminSettingsJavaScriptPage::class));
            $menu->setToolsPage($c->typed(AdminSettingsToolsPage::class));
            $menu->setBrandingPage($c->typed(AdminBrandingPage::class));
            $menu->setPersonalizationPage($c->typed(AdminPersonalizationPage::class));
            $menu->setCanvasEditorWindowedPage($c->typed(AdminCanvasEditorWindowedPage::class));
            $menu->setCanvasEditorWindowedGlobalPartPage($c->typed(AdminCanvasEditorWindowedGlobalPartPage::class));
            return $menu;
        });

        $container->factory(AdminCanvasEditorWindowedPage::class, static function (Container $c): AdminCanvasEditorWindowedPage {
            return new AdminCanvasEditorWindowedPage(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PageDetailsPortInterface::class),
                $c->typed(InspectEditorOwnership::class),
                $c->typed(EditorLockDialogRenderer::class),
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(AdminCanvasEditorWindowedGlobalPartPage::class, static function (Container $c): AdminCanvasEditorWindowedGlobalPartPage {
            return new AdminCanvasEditorWindowedGlobalPartPage(
                $c->typed(InspectEditorOwnership::class),
                $c->typed(EditorLockDialogRenderer::class),
            );
        });

        $container->factory(CanvasAdminBar::class, static function (): CanvasAdminBar {
            return new CanvasAdminBar();
        });

        $container->factory(AdminCanvasPage::class, static function (Container $c): AdminCanvasPage {
            return new AdminCanvasPage(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(CanvasRenderer::class),
                $c->typed(MagicBridgeEnqueuer::class),
                $c->typed(CanvasAssetAllowlist::class),
                $c->typed(CanvasAdminBar::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(EnterEditorOwnership::class),
                $c->typed(EditorLockStoreInterface::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(EditorLockDialogRenderer::class),
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(EditorLockDialogRenderer::class, static function (): EditorLockDialogRenderer {
            return new EditorLockDialogRenderer();
        });

        $container->factory(EditorLockTakeoverAction::class, static function (Container $c): EditorLockTakeoverAction {
            return new EditorLockTakeoverAction(
                $c->typed(TakeOverEditorOwnership::class),
                $c->typed(EditorLockStoreInterface::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(EditorLockDialogRenderer::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(EditorLockHeartbeat::class, static function (Container $c): EditorLockHeartbeat {
            return new EditorLockHeartbeat(
                $c->typed(RefreshEditorOwnership::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(PageFactory::class, static function (Container $c): PageFactory {
            return new PageFactory(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(ShellModeService::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(PageDetailsPortInterface::class),
                $c->typed(PageBuilderAvailabilityInterface::class),
                $c->typed(PageSourcePackageService::class),
                $c->typed(PageSourceArchiveService::class),
            );
        });

        $container->factory(NativePageListPresenter::class, static function (Container $c): NativePageListPresenter {
            return new NativePageListPresenter(
                $c->typed(PageOwnershipRepositoryInterface::class),
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(AdminBarButton::class, static function (Container $c): AdminBarButton {
            return new AdminBarButton(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(PageBuilderAvailabilityInterface::class),
                $c->typed(SupportsPostTypeUseCase::class),
            );
        });

        $container->factory(RestNonceRefresher::class, static function (Container $c): RestNonceRefresher {
            return new RestNonceRefresher(
                $c->typed(GetPageBuilderAllowedCapabilities::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        $adminMenu         = $container->typed(AdminMenu::class);
        $pageFactory       = $container->typed(PageFactory::class);
        $pageArchiveExport = $container->typed(PageSourceArchiveDownloadAction::class);
        $pageArchiveArtifacts = $container->typed(WordPressPageSourceArchiveArtifactStore::class);
        $adminBarButton    = $container->typed(AdminBarButton::class);
        $nonceRefresher    = $container->typed(RestNonceRefresher::class);
        $nativePageList    = $container->typed(NativePageListPresenter::class);
        $lockTakeover      = $container->typed(EditorLockTakeoverAction::class);
        $lockHeartbeat     = $container->typed(EditorLockHeartbeat::class);

        $adminCanvas = $container->typed(AdminCanvasPage::class);
        $callbacks = new WordPressCallbackBoundary();

        add_action('admin_menu', $callbacks->action('admin_menu.register', [$adminMenu, 'register']));
        add_action('admin_menu', [$adminCanvas, 'register']);
        add_action('admin_enqueue_scripts', [$adminMenu, 'enqueueSettingsNewAssets']);
        add_action('admin_enqueue_scripts', [$adminMenu, 'enqueueCanvasEditorWindowedPageAssets']);
        add_action('admin_notices', [$adminMenu, 'renderNativePageNotices']);
        add_action('admin_footer-edit.php', [$adminMenu, 'renderNativePageImportForm']);

        // Run after editor plugins such as Elementor, SeedProd, and Classic
        // Editor. Their source may remain dormant, but their later filters must
        // not advertise a second active editor for a UPB-owned page.
        add_filter('display_post_states', $callbacks->filter('page_list.states', [$nativePageList, 'addOwnershipState']), PHP_INT_MAX, 2);
        add_filter('page_row_actions', $callbacks->filter('page_list.actions', [$nativePageList, 'routeOwnedPageActions']), PHP_INT_MAX, 2);
        add_filter('post_row_actions', $callbacks->filter('post_list.actions', [$nativePageList, 'routeOwnedPageActions']), PHP_INT_MAX, 2);
        add_filter('get_edit_post_link', $callbacks->filter('page_list.edit_link', [$nativePageList, 'routeOwnedPageEditLink']), PHP_INT_MAX, 3);
        add_action('admin_post_' . PageFactory::CREATE_ACTION, [$pageFactory, 'create']);
        add_action('admin_post_' . PageFactory::IMPORT_ACTION, [$pageFactory, 'importPage']);
        add_action('admin_post_' . PageSourceArchiveDownloadAction::ACTION, [$pageArchiveExport, 'handle']);
        $pageArchiveArtifacts->register();
        add_action('admin_post_' . EditorLockTakeoverAction::ACTION, [$lockTakeover, 'handle']);
        add_filter('heartbeat_received', $callbacks->filter('editor_lock.heartbeat', [$lockHeartbeat, 'refresh']), 20, 3);
        add_action('admin_bar_menu', $callbacks->action('admin_bar.register', [$adminBarButton, 'register']), 80);
        $nonceRefresher->register();
    }
}
