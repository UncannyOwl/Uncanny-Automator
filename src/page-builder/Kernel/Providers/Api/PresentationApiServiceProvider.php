<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\Api;

use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Controls\ControlDispatcher;
use UncannyPageBuilder\Application\Controls\ControlRegistry;
use UncannyPageBuilder\Application\EditorLock\CheckHumanWriteOwnership;
use UncannyPageBuilder\Application\Canvas\CanvasGlobalPartsProviderInterface;
use UncannyPageBuilder\Application\Controls\ControlStateService;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\Editor\EditorStateService;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\Export\StaticPageExportService;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\ShellImportService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Application\SourcePackage\ReusableSourcePackageService;
use UncannyPageBuilder\Application\UpdatePageLayout;
use UncannyPageBuilder\Domain\EditorLock\EditorLockStoreInterface;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;
use UncannyPageBuilder\Presentation\Api\ControlController;
use UncannyPageBuilder\Presentation\Api\DesignStandardsController;
use UncannyPageBuilder\Presentation\Api\EditorStateController;
use UncannyPageBuilder\Presentation\Api\EditorLockWriteGuard;
use UncannyPageBuilder\Presentation\Api\GlobalPartController;
use UncannyPageBuilder\Presentation\Api\LayoutController;
use UncannyPageBuilder\Presentation\Api\MediaController;
use UncannyPageBuilder\Presentation\Api\SectionController;
use UncannyPageBuilder\Presentation\Api\ShellController;
use UncannyPageBuilder\Presentation\Api\ShellModeController;
use UncannyPageBuilder\Presentation\Api\StaticExportController;

final class PresentationApiServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->factory(EditorLockWriteGuard::class, static function (Container $c): EditorLockWriteGuard {
            return new EditorLockWriteGuard(
                $c->typed(CheckHumanWriteOwnership::class),
                $c->typed(EditorLockStoreInterface::class),
                $c->typed(PermissionChecker::class),
            );
        });

        $container->factory(LayoutController::class, static function (Container $c): LayoutController {
            return new LayoutController(
                $c->typed(SectionService::class),
                $c->typed(PermissionChecker::class),
                $c->typed(GlobalPartService::class),
                $c->typed(CanvasGlobalPartsProviderInterface::class),
                $c->typed(EditorLockWriteGuard::class),
                $c->typed(SelectEditorPageSource::class),
                $c->typed(\UncannyPageBuilder\Domain\Compiler\ShadowCompiler::class),
            );
        });

        $container->factory(SectionController::class, static function (Container $c): SectionController {
            return new SectionController(
                $c->typed(SectionService::class),
                $c->typed(PermissionChecker::class),
                $c->typed(EditorLockWriteGuard::class),
            );
        });

        $container->factory(GlobalPartController::class, static function (Container $c): GlobalPartController {
            return new GlobalPartController(
                $c->typed(GlobalPartService::class),
                $c->typed(PermissionChecker::class),
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(ReusableSourcePackageService::class),
            );
        });

        $container->factory(DesignStandardsController::class, static function (Container $c): DesignStandardsController {
            return new DesignStandardsController(
                $c->typed(DesignStandardsService::class),
                $c->typed(SectionService::class),
                $c->typed(PermissionChecker::class),
                $c->typed(WorkingCanvasRefresherInterface::class),
                $c->typed(EditorLockWriteGuard::class),
                $c->typed(\UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface::class),
            );
        });

        $container->factory(ShellModeController::class, static function (Container $c): ShellModeController {
            return new ShellModeController(
                $c->typed(ShellModeService::class),
                $c->typed(SectionService::class),
                $c->typed(PermissionChecker::class),
                $c->typed(UpdatePageLayout::class),
                $c->typed(WorkingCanvasRefresherInterface::class),
                $c->typed(EditorLockWriteGuard::class),
            );
        });

        $container->factory(ShellController::class, static function (Container $c): ShellController {
            return new ShellController(
                $c->typed(ShellImportService::class),
                $c->typed(PermissionChecker::class),
            );
        });

        $container->factory(MediaController::class, static function (Container $c): MediaController {
            return new MediaController(
                $c->typed(PermissionChecker::class),
            );
        });

        $container->factory(EditorStateController::class, static function (Container $c): EditorStateController {
            return new EditorStateController(
                $c->typed(EditorStateService::class),
                $c->typed(SectionService::class),
                $c->typed(PermissionChecker::class),
            );
        });

        $container->factory(ControlController::class, static function (Container $c): ControlController {
            return new ControlController(
                $c->typed(ControlStateService::class),
                $c->typed(ControlDispatcher::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
                $c->typed(ControlRegistry::class),
                $c->typed(EditorLockWriteGuard::class),
            );
        });

        $container->factory(StaticExportController::class, static function (Container $c): StaticExportController {
            return new StaticExportController(
                $c->typed(StaticPageExportService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(PermissionChecker::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        $controllers = [
            $container->typed(LayoutController::class),
            $container->typed(SectionController::class),
            $container->typed(GlobalPartController::class),
            $container->typed(DesignStandardsController::class),
            $container->typed(ShellModeController::class),
            $container->typed(ShellController::class),
            $container->typed(MediaController::class),
            $container->typed(EditorStateController::class),
            $container->typed(ControlController::class),
            $container->typed(StaticExportController::class),
        ];

        add_action('rest_api_init', static function () use ($controllers): void {
            foreach ($controllers as $controller) {
                $controller->registerRoutes();
            }
        });
    }
}
