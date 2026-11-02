<?php
/**
 * The built-in commands that change the site.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Catalog;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * A closed list: commands that change the site exist only here, apart from
 * the read-only catalog, so the read-only tool can never reach them. No
 * command deletes or uninstalls plugins, themes or content.
 */
final class Builtin_Site_Manage_Catalog implements Site_Command_Catalog {

	/**
	 * The command groups.
	 *
	 * @return Site_Command_Group[]
	 */
	public function groups(): array {
		return array(
			new Plugin_Manage_Commands(),
			new Theme_Manage_Commands(),
			new Language_Manage_Commands(),
			new Comment_Manage_Commands(),
			new Site_Settings_Commands(),
			new Upkeep_Commands(),
		);
	}
}
