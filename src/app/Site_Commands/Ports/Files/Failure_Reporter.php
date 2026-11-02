<?php
/**
 * Where unexpected failures of file commands are reported.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Ports\Files;

/**
 * Receives a failure that is not a path or content condition, such as a
 * bug, after the caller was told "internal error".
 */
interface Failure_Reporter {

	/**
	 * Report one failure.
	 *
	 * @param string     $command The command or "pipeline".
	 * @param \Throwable $failure What went wrong.
	 * @return void
	 */
	public function report( string $command, \Throwable $failure ): void;
}
