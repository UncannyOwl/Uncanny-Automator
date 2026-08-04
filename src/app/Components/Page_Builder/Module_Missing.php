<?php
/**
 * Missing embedded Page Builder runtime.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Components\Page_Builder;

/**
 * Separates a packaging/autoload failure from a module boot failure.
 *
 * @since 7.4.1
 */
class Module_Missing extends \RuntimeException {
}
