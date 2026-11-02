<?php
/**
 * How much memory the process uses and may use.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Budget;

/**
 * How much memory the process has used and may use. The ledger stops a
 * command before PHP's own limit turns the request into a fatal error.
 */
interface Memory_Gauge {

	/**
	 * Bytes in use.
	 *
	 * @return int
	 */
	public function used(): int;

	/**
	 * Bytes allowed. Zero when unlimited.
	 *
	 * @return int
	 */
	public function limit(): int;
}
