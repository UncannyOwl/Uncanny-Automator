<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Canvas\PublicPageRenderPolicy;

final class WpAutopDisabler
{
    public function __construct(
        private readonly PublicPageRenderPolicy $publicPageRenderPolicy,
    ) {}

    public function maybeDisable(): void
    {
        if (is_singular() && $this->publicPageRenderPolicy->isReady(get_the_ID())) {
            remove_filter('the_content', 'wpautop');
        }
    }
}
