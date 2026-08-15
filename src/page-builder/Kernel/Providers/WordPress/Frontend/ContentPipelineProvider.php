<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\WordPress\Frontend;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Canvas\OriginalPageContentReaderInterface;
use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;
use UncannyPageBuilder\Infrastructure\Rendering\ContentRenderer;
use UncannyPageBuilder\Infrastructure\Rendering\DynamicRenderer;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPublishedFallbackParser;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;

final class ContentPipelineProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->factory(ContentRenderer::class, static function (Container $c): ContentRenderer {
            return new ContentRenderer(
                $c->typed(PublicPageRenderPolicy::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(OriginalPageContentReaderInterface::class),
                $c->typed(DynamicRenderer::class),
                new WordPressPublishedFallbackParser(),
            );
        });
    }

    public function boot(Container $container): void
    {
        $contentRenderer = $container->typed(ContentRenderer::class);

        // Select fallback source before WordPress formatting, then replace it
        // only from the exact artifact selected by the public pointer.
        add_filter('the_content', [$contentRenderer, 'selectOriginalContent'], 7);
        add_filter('the_content', [$contentRenderer, 'filter'], 99);
        add_action('wp_head', [$contentRenderer, 'injectCss'], 99);
        add_action('wp_footer', [$contentRenderer, 'renderBridgeRoot'], 10);
        add_action('wp_footer', [$contentRenderer, 'renderCustomJavaScript'], 15);
        add_action('wp_footer', [$contentRenderer, 'checkRenderFallback'], 999);
        add_filter('body_class', [$contentRenderer, 'bodyClasses']);
    }
}
