<?php

declare(strict_types=1);

namespace UncannyPageBuilder;

use UncannyPageBuilder\Infrastructure\Rendering\CanvasRenderer;
use UncannyPageBuilder\Infrastructure\Rendering\PublishedCanvasRenderer;
use UncannyPageBuilder\Kernel\ApplicationKernel;
use UncannyPageBuilder\Kernel\Providers\Api\AgentApiServiceProvider;
use UncannyPageBuilder\Kernel\Providers\Api\PresentationApiServiceProvider;
use UncannyPageBuilder\Kernel\Providers\Controls\ControlPlaneProvider;
use UncannyPageBuilder\Kernel\Providers\CoreServiceProvider;
use UncannyPageBuilder\Kernel\Providers\Integrations\Automator\McpAgentProvider;
use UncannyPageBuilder\Kernel\Providers\WordPress\Admin\AdminMenuProvider;
use UncannyPageBuilder\Kernel\Providers\WordPress\Admin\EditorEnvironmentProvider;
use UncannyPageBuilder\Kernel\Providers\WordPress\Admin\GlobalPartEditorProvider;
use UncannyPageBuilder\Kernel\Providers\WordPress\Admin\PageEditorEnhancementProvider;
use UncannyPageBuilder\Kernel\Providers\WordPress\Frontend\CanvasIntegrationProvider;
use UncannyPageBuilder\Kernel\Providers\WordPress\Frontend\ContentPipelineProvider;
use UncannyPageBuilder\Kernel\Providers\WordPress\System\SystemEnvironmentProvider;

final class Plugin
{
    private static ?ApplicationKernel $kernel = null;

    public static function boot(): void
    {
        if (self::$kernel !== null) {
            return;
        }

        self::$kernel = new ApplicationKernel();
        self::$kernel->bootstrap([
            CoreServiceProvider::class,
            SystemEnvironmentProvider::class,
            ControlPlaneProvider::class,
            PresentationApiServiceProvider::class,
            AgentApiServiceProvider::class,
            ContentPipelineProvider::class,
            CanvasIntegrationProvider::class,
            McpAgentProvider::class,
            AdminMenuProvider::class,
            EditorEnvironmentProvider::class,
            GlobalPartEditorProvider::class,
            PageEditorEnhancementProvider::class,
        ]);
    }

    /**
     * Static accessor for the CanvasRenderer.
     * Used by templates/canvas.php for the thin delegation pattern.
     */
    public static function getCanvasRenderer(): CanvasRenderer
    {
        if (self::$kernel === null) {
            throw new \LogicException('Plugin::boot() must be called before accessing the canvas renderer.');
        }
        return self::$kernel->getContainer()->typed(CanvasRenderer::class);
    }

    /**
     * Exact-pointer native renderer used by the Phase 4 loader after cutover.
     */
    public static function getPublishedCanvasRenderer(): PublishedCanvasRenderer
    {
        if (self::$kernel === null) {
            throw new \LogicException('Plugin::boot() must be called before accessing the published canvas renderer.');
        }

        return self::$kernel->getContainer()->typed(PublishedCanvasRenderer::class);
    }
}
