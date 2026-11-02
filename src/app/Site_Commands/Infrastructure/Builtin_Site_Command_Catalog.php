<?php
/**
 * The site command groups shipped with Automator.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure;

use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Content_Commands;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Core_Commands;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Cron_Commands;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Navigation_Commands;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Plugin_Commands;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Post_Type_Commands;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Settings_Commands;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Theme_Commands;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Files\File_Commands;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Catalog;

/**
 * A closed list: a command exists only when its group is listed here.
 */
final class Builtin_Site_Command_Catalog implements Site_Command_Catalog {

	/**
	 * The command groups.
	 *
	 * @return array
	 */
	public function groups(): array {
		return array(
			new Core_Commands(),
			new Plugin_Commands(),
			new Theme_Commands(),
			new Post_Type_Commands(),
			new Content_Commands(),
			new Navigation_Commands(),
			new Cron_Commands(),
			new Settings_Commands(),
			new File_Commands(),
		);
	}
}
