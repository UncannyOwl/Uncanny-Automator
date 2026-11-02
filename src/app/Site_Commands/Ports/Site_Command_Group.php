<?php
/**
 * One WP-CLI-style command group, such as "plugin".
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Ports;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;

/**
 * A group declares its commands, each with its own handler. Run_Site_Command
 * checks every command before calling a handler; a group may only refuse
 * more, as the file commands do for the owner's switch and for users who
 * may not read the site's files.
 */
interface Site_Command_Group {

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array;
}
