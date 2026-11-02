<?php
/**
 * The database did not answer a post meta read.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/** Thrown instead of returning no rows, so a failed read never looks like an absent key. */
final class Post_Meta_Store_Unavailable extends \RuntimeException {
}
