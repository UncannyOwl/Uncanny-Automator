<?php
/**
 * Run a command's handler so that it cannot write to the site.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Ports;

/**
 * Read-only commands read through WordPress, whose read functions fill caches
 * as they go. An isolation keeps those writes away from the site's database
 * while the handler runs, and from its object cache as far as the site's
 * cache code allows; it never lets a command flush that cache.
 */
interface Read_Isolation {

	/**
	 * Run the work, isolated.
	 *
	 * @param callable $work The command's handler, bound to its input.
	 * @return mixed What the work returned.
	 */
	public function run( callable $work );

	/**
	 * A stats line when the isolation is only best effort, such as when a
	 * read may refresh the site's object cache; null when it is complete.
	 *
	 * @return string|null
	 */
	public function limitation(): ?string;
}
