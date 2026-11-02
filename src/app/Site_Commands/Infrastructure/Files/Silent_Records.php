<?php
/**
 * Refused paths and failures of file commands, recorded nowhere.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Files;

use Uncanny_Automator\App\Site_Commands\Ports\Files\Access_Audit;
use Uncanny_Automator\App\Site_Commands\Ports\Files\Failure_Reporter;

/**
 * Site commands write nothing to the site, not even a line to the error
 * log (Domain/POLICY.md). The caller already sees each refusal and failure
 * in the command's output, so these records are dropped.
 */
final class Silent_Records implements Access_Audit, Failure_Reporter {

	/**
	 * Drop a refused path.
	 *
	 * @param string $command   The command.
	 * @param string $requested The path as the caller wrote it.
	 * @return void
	 */
	public function denied( string $command, string $requested ): void {
	}

	/**
	 * Drop a failure.
	 *
	 * @param string     $command The command or "pipeline".
	 * @param \Throwable $failure What went wrong.
	 * @return void
	 */
	public function report( string $command, \Throwable $failure ): void {
	}
}
