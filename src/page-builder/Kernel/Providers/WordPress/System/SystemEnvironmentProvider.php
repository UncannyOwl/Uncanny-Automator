<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\WordPress\System;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;
use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Infrastructure\WordPress\GlobalPartCpt;
use UncannyPageBuilder\Infrastructure\WordPress\KsesSanitizer;
use UncannyPageBuilder\Infrastructure\WordPress\MultisiteSchemaProvisioner;
use UncannyPageBuilder\Infrastructure\WordPress\WpAutopDisabler;
use UncannyPageBuilder\Infrastructure\WordPress\WorkingCanvasAdminActions;
use UncannyPageBuilder\Infrastructure\WordPress\PublishedPageCleanup;
use UncannyPageBuilder\Infrastructure\WordPress\WorkingCanvasInputVersionListener;
use UncannyPageBuilder\Infrastructure\WordPress\WorkingCanvasRefreshNotice;
use UncannyPageBuilder\Infrastructure\WordPress\WorkingCanvasRefreshCommand;
use UncannyPageBuilder\Infrastructure\WordPress\WorkingCanvasMenuChangeListener;
use UncannyPageBuilder\Infrastructure\WordPress\FontSettingsFingerprint;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressFontSettings;
use UncannyPageBuilder\Infrastructure\WordPress\PageBuilderAccessCapability;
use UncannyPageBuilder\Infrastructure\WordPress\ThemeCompositionPageTemplateSynchronizer;
use UncannyPageBuilder\Infrastructure\WordPress\WpCronWorkingCanvasRefreshQueue;
use UncannyPageBuilder\Infrastructure\WordPress\WpCronWorkingCanvasRefreshRunner;
use UncannyPageBuilder\Application\Publishing\OwnedPageFinderInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\ThemeCompositionPageTemplateSynchronizerInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceRowsCleanupInterface;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifactRepositoryInterface;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Export\StaticRenderingPolicy;
use UncannyPageBuilder\Infrastructure\Persistence\DatabasePageSourceRowsCleanup;

final class SystemEnvironmentProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->factory(GlobalPartCpt::class, static function (Container $c): GlobalPartCpt {
            unset($c);

            return new GlobalPartCpt();
        });

        $container->factory(MultisiteSchemaProvisioner::class, static function (Container $c): MultisiteSchemaProvisioner {
            return new MultisiteSchemaProvisioner(
                $c->typed(\UncannyPageBuilder\Application\System\SchemaInstallerInterface::class),
            );
        });

        $container->factory(PageBuilderAccessCapability::class, static function (Container $c): PageBuilderAccessCapability {
            return new PageBuilderAccessCapability(
                $c->typed(GetPageBuilderAllowedCapabilities::class),
            );
        });

        $container->factory(KsesSanitizer::class, static function (): KsesSanitizer {
            return new KsesSanitizer();
        });

        $container->factory(WpAutopDisabler::class, static function (Container $c): WpAutopDisabler {
            return new WpAutopDisabler(
                $c->typed(PublicPageRenderPolicy::class),
            );
        });

        $container->factory(PublishedPageCleanup::class, static function (Container $c): PublishedPageCleanup {
            return new PublishedPageCleanup(
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(PublishedPageArtifactRepositoryInterface::class),
                $c->typed(PageSourceRowsCleanupInterface::class),
                $c->typed(\UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface::class),
                $c->typed(WpCronWorkingCanvasRefreshQueue::class),
            );
        });
        $container->factory(DatabasePageSourceRowsCleanup::class, static fn (): DatabasePageSourceRowsCleanup => new DatabasePageSourceRowsCleanup());
        $container->factory(PageSourceRowsCleanupInterface::class, static fn (Container $c): PageSourceRowsCleanupInterface => $c->typed(DatabasePageSourceRowsCleanup::class));

        $container->factory(WorkingCanvasInputVersionListener::class, static function (Container $c): WorkingCanvasInputVersionListener {
            $policy = new StaticRenderingPolicy($c->typed(BindingRegistry::class));
            $fontFingerprint = new FontSettingsFingerprint(
                $c->typed(WordPressFontSettings::class),
            );

            return new WorkingCanvasInputVersionListener(
                $c->typed(WorkingCanvasRefreshScheduler::class),
                (defined('UNCANNY_PB_VERSION') ? (string) UNCANNY_PB_VERSION : 'unknown')
                    . ':' . $policy->fingerprint()
                    . ':' . $fontFingerprint->compute(),
            );
        });

        $container->factory(WorkingCanvasAdminActions::class, static function (Container $c): WorkingCanvasAdminActions {
            return new WorkingCanvasAdminActions(
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(WorkingCanvasRefreshScheduler::class),
                $c->typed(WpCronWorkingCanvasRefreshRunner::class),
                $c->typed(WpCronWorkingCanvasRefreshQueue::class),
            );
        });

        $container->factory(WorkingCanvasRefreshCommand::class, static function (Container $c): WorkingCanvasRefreshCommand {
            return new WorkingCanvasRefreshCommand(
                $c->typed(OwnedPageFinderInterface::class),
                $c->typed(WorkingCanvasRefresherInterface::class),
            );
        });

        $container->factory(WorkingCanvasRefreshNotice::class, static function (Container $c): WorkingCanvasRefreshNotice {
            return new WorkingCanvasRefreshNotice(
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(WpCronWorkingCanvasRefreshQueue::class),
            );
        });

        $container->factory(WorkingCanvasMenuChangeListener::class, static function (Container $c): WorkingCanvasMenuChangeListener {
            return new WorkingCanvasMenuChangeListener(
                $c->typed(GlobalSourceMutation::class),
                $c->typed(WorkingCanvasRefreshScheduler::class),
            );
        });

        $container->factory(WpCronWorkingCanvasRefreshRunner::class, static function (Container $c): WpCronWorkingCanvasRefreshRunner {
            return new WpCronWorkingCanvasRefreshRunner(
                $c->typed(WpCronWorkingCanvasRefreshQueue::class),
                $c->typed(WorkingCanvasRefresherInterface::class),
            );
        });

        $container->factory(ThemeCompositionPageTemplateSynchronizerInterface::class, static function (): ThemeCompositionPageTemplateSynchronizerInterface {
            return new ThemeCompositionPageTemplateSynchronizer();
        });
    }

    public function boot(Container $container): void
    {
        $cpt           = $container->typed(GlobalPartCpt::class);
        $autopDisabler = $container->typed(WpAutopDisabler::class);
        $publishedPageCleanup = $container->typed(PublishedPageCleanup::class);
        $workingCanvasAdminActions = $container->typed(WorkingCanvasAdminActions::class);
        $workingCanvasInputVersion = $container->typed(WorkingCanvasInputVersionListener::class);
        $workingCanvasRefreshCommand = $container->typed(WorkingCanvasRefreshCommand::class);
        $workingCanvasRefreshNotice = $container->typed(WorkingCanvasRefreshNotice::class);
        $workingCanvasMenuChanges = $container->typed(WorkingCanvasMenuChangeListener::class);
        $workingCanvasRefreshRunner = $container->typed(WpCronWorkingCanvasRefreshRunner::class);
        $pageBuilderAccessCapability = $container->typed(PageBuilderAccessCapability::class);
        $allowedCapabilities = $container->typed(GetPageBuilderAllowedCapabilities::class);
        $schemaProvisioner = $container->typed(MultisiteSchemaProvisioner::class);

        // 1. Capabilities, schema lifecycle & CPT
        $pageBuilderAccessCapability->register();
        $schemaProvisioner->register();
        add_action('init', [$cpt, 'register']);

        // 3. Core Overrides
        // Page Builder KSES allowances are registered only around its own
        // durable HTML writes by KsesSanitizer.
        add_action('wp', [$autopDisabler, 'maybeDisable']);

        // 3b. Allow font uploads (admin only, wp-admin only)
        add_filter('upload_mimes', static function (array $mimes) use ($allowedCapabilities): array {
            if (!is_admin() || !$allowedCapabilities->currentUserHasAllowedCapability()) {
                return $mimes;
            }
            $mimes['woff2'] = 'font/woff2';
            $mimes['woff']  = 'font/woff';
            $mimes['ttf']   = 'font/sfnt';
            $mimes['otf']   = 'font/otf';
            return $mimes;
        });

        $publishedPageCleanup->register();
        $workingCanvasAdminActions->register();
        $workingCanvasInputVersion->register();
        $workingCanvasRefreshCommand->register();
        $workingCanvasRefreshNotice->register();
        $workingCanvasMenuChanges->register();
        $workingCanvasRefreshRunner->register();
    }
}
