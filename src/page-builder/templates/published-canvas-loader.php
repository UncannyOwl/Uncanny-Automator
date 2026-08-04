<?php

declare(strict_types=1);

use UncannyPageBuilder\Plugin;

defined('ABSPATH') || exit;

Plugin::getPublishedCanvasRenderer()->render((int) get_the_ID());
