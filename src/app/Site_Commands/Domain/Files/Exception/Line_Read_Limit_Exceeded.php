<?php
/**
 * One line was longer than a read may hold.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Exception;

use RuntimeException;

/**
 * A line exceeded Bounded_Lines::MAX_BYTES; nothing of it was yielded.
 */
final class Line_Read_Limit_Exceeded extends RuntimeException {
}
