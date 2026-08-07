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
        // The function get_the_ID() can return false when the wp hook has no current post.
        // PublicPageRenderPolicy rejects the normalized 0 and keeps wpautop active.
        if (is_singular() && $this->publicPageRenderPolicy->isReady((int) get_the_ID())) {
            remove_filter('the_content', 'wpautop');
        }
    }
}
