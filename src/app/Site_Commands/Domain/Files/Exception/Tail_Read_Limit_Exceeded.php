<?php
/**
 * A tail read needed more than it may hold.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Exception;

use RuntimeException;

/**
 * Reading the requested tail would need more than 1 MB.
 */
final class Tail_Read_Limit_Exceeded extends RuntimeException {
}
