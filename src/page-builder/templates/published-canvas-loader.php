<?php

declare(strict_types=1);

use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostId;
use UncannyPageBuilder\Plugin;

defined('ABSPATH') || exit;

$postId = WordPressPostId::fromCurrentQuery(get_queried_object_id());
if ($postId !== null) {
    Plugin::getPublishedCanvasRenderer()->render($postId);
}
