<?php
/**
 * Build the command index from the catalog.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Application;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Index;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Catalog;

/**
 * Running and help share one index, built once per request.
 */
final class Index_Site_Commands {

	private Site_Command_Catalog $catalog;
	private ?Command_Index $index = null;

	/**
	 * Read commands from this catalog.
	 *
	 * @param Site_Command_Catalog $catalog Command groups.
	 */
	public function __construct( Site_Command_Catalog $catalog ) {
		$this->catalog = $catalog;
	}

	/**
	 * The index of every declared command.
	 *
	 * @return Command_Index
	 */
	public function execute(): Command_Index {
		if ( null === $this->index ) {
			$definitions = array();
			foreach ( $this->catalog->groups() as $group ) {
				foreach ( $group->commands() as $definition ) {
					$definitions[] = $definition;
				}
			}
			$this->index = new Command_Index( $definitions );
		}

		return $this->index;
	}
}
