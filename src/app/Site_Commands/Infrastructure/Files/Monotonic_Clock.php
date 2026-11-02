<?php
/**
 * The system clock, for file-command budgets and dates.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Files;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Clock;

/**
 * Monotonic time for budgets, wall time for file ages.
 */
final class Monotonic_Clock implements Clock {

	/**
	 * Monotonic seconds. Only differences are meaningful.
	 *
	 * @return float
	 */
	public function now(): float {
		return hrtime( true ) / 1e9;
	}

	/**
	 * Unix epoch seconds.
	 *
	 * @return int
	 */
	public function wall_time(): int {
		return time();
	}
}
