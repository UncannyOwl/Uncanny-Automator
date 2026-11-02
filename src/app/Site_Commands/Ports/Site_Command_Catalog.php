<?php
/**
 * Every site command group this runtime offers.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Ports;

/**
 * Only declared commands exist. Anything else is "not supported".
 */
interface Site_Command_Catalog {

	/**
	 * The command groups.
	 *
	 * @return Site_Command_Group[]
	 */
	public function groups(): array;
}
