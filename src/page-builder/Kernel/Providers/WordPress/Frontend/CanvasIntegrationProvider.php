<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\WordPress\Frontend;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\GetAvailableFontFamilies;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseGlobalPartRepository;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Infrastructure\i18\PageBuilderJsStrings;
use UncannyPageBuilder\Infrastructure\WordPress\CanvasAssetAllowlist;
use UncannyPageBuilder\Infrastructure\WordPress\CanvasHijacker;
use UncannyPageBuilder\Infrastructure\WordPress\DesignTokenInjector;
use UncannyPageBuilder\Infrastructure\WordPress\FontInjector;
use UncannyPageBuilder\Infrastructure\WordPress\MagicBridgeEnqueuer;
use UncannyPageBuilder\Infrastructure\WordPress\PublishedPageAssetEnqueuer;
use UncannyPageBuilder\Infrastructure\WordPress\WorkingDesignTokenCss;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressFontSettings;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostId;
use UncannyPageBuilder\Infrastructure\WordPress\WorkingCanvasAssetEnqueuer;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;

final class CanvasIntegrationProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->factory(PageBuilderJsStrings::class, static function (): PageBuilderJsStrings {
            return new PageBuilderJsStrings();
        });

        $container->factory(CanvasHijacker::class, static function (Container $c): CanvasHijacker {
            return new CanvasHijacker(
                UNCANNY_PB_PATH,
                $c->typed(PublicPageRenderPolicy::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(CanvasAssetAllowlist::class),
            );
        });

        $container->factory(CanvasAssetAllowlist::class, static function (): CanvasAssetAllowlist {
            return new CanvasAssetAllowlist();
        });

        $container->factory(PublishedPageAssetEnqueuer::class, static function (Container $c): PublishedPageAssetEnqueuer {
            return new PublishedPageAssetEnqueuer(
                $c->typed(PublicPageRenderPolicy::class),
            );
        });

        $container->factory(WorkingCanvasAssetEnqueuer::class, static function (Container $c): WorkingCanvasAssetEnqueuer {
            return new WorkingCanvasAssetEnqueuer(
                UNCANNY_PB_URL,
                UNCANNY_PB_VERSION,
                $c->typed(DatabaseSectionRepository::class),
            );
        });

        $container->factory(WorkingDesignTokenCss::class, static function (Container $c): WorkingDesignTokenCss {
            return new WorkingDesignTokenCss(
                $c->typed(DesignStandardsService::class),
                $c->typed(\UncannyPageBuilder\Application\Editor\SelectEditorPageSource::class),
            );
        });

        $container->factory(DesignTokenInjector::class, static function (Container $c): DesignTokenInjector {
            return new DesignTokenInjector(
                $c->typed(WorkingDesignTokenCss::class),
            );
        });

        $container->factory(FontInjector::class, static function (Container $c): FontInjector {
            return new FontInjector(
                $c->typed(PublicPageRenderPolicy::class),
                $c->typed(WordPressFontSettings::class),
            );
        });

        $container->factory(MagicBridgeEnqueuer::class, static function (Container $c): MagicBridgeEnqueuer {
            return new MagicBridgeEnqueuer(
                UNCANNY_PB_URL,
                UNCANNY_PB_VERSION,
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(ShellModeService::class),
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(DatabaseGlobalPartRepository::class),
                $c->typed(DesignStandardsService::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(GetAvailableFontFamilies::class),
                $c->typed(PageBuilderJsStrings::class),
                $c->typed(PageDetailsPortInterface::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        $hijacker       = $container->typed(CanvasHijacker::class);
        $publishedAssets = $container->typed(PublishedPageAssetEnqueuer::class);
        $workingAssets  = $container->typed(WorkingCanvasAssetEnqueuer::class);
        $tokenInjector  = $container->typed(DesignTokenInjector::class);
        $fontInjector   = $container->typed(FontInjector::class);

        // Canvas routing is frontend-only. Admin chrome is hidden only by
        // AdminCanvasPage when rendering the dedicated wp-admin canvas editor.
        add_filter('template_include', [$hijacker, 'hijack'], 99);

        // 7. Site design custom properties (must come AFTER Bootstrap CSS <link> tag)
        add_action('wp_head', [$tokenInjector, 'inject'], 50);
        add_action('wp_head', [$fontInjector, 'injectWorking'], 51);
        add_action('wp_head', [$fontInjector, 'injectPublished'], 51);

        add_action('wp_enqueue_scripts', static function () use ($workingAssets, $publishedAssets): void {
            if (!is_singular()) {
                return;
            }
            $postId = WordPressPostId::fromCurrentQuery(get_queried_object_id());
            if ($postId === null) {
                return;
            }
            $isGlobalPart = get_post_type($postId) === 'upb_global_part';

            if (is_admin() || $isGlobalPart) {
                $workingAssets->enqueue($postId, $isGlobalPart);
                return;
            }

            $publishedAssets->enqueue();
        });

        // Editor chrome assets are loaded only by AdminCanvasPage. Frontend
        // preview and public page requests must render content without the
        // builder stylesheet or SDK.
    }
}
