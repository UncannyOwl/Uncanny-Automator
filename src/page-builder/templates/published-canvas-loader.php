<?php

declare(strict_types=1);

use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostId;
use UncannyPageBuilder\Plugin;

defined('ABSPATH') || exit;

// WordPress includes this file from template-loader.php at global scope, so the
// work happens in a closure and leaves no global variable behind.
(static function (): void {
    $postId = WordPressPostId::fromCurrentQuery(get_queried_object_id());
    if ($postId !== null) {
        Plugin::getPublishedCanvasRenderer()->render($postId);
    }
})();
