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
        if (!is_singular()) {
            return;
        }

        $postId = WordPressPostId::fromCurrentQuery(get_queried_object_id());
        if ($postId !== null && $this->publicPageRenderPolicy->isReady($postId)) {
            remove_filter('the_content', 'wpautop');
        }
    }
}
