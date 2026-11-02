<?php
/**
 * Where refused paths of file commands are recorded.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Ports\Files;

/**
 * Receives each path the sandbox refused, after the command ran.
 */
interface Access_Audit {

	/**
	 * Record one refused path.
	 *
	 * @param string $command   The command.
	 * @param string $requested The path as the caller wrote it.
	 * @return void
	 */
	public function denied( string $command, string $requested ): void;
}
