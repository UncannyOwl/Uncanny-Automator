<?php
/**
 * Time, for the file commands' budgets and dates.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Budget;

/**
 * Time, for the wall-time budget and for date formatting.
 */
interface Clock {

	/**
	 * Monotonic seconds. Only differences are meaningful.
	 *
	 * @return float
	 */
	public function now(): float;

	/**
	 * Unix epoch seconds, for `ls` and `find` age comparisons.
	 *
	 * @return int
	 */
	public function wall_time(): int;
}
