<?php
/**
 * Uncanny Agent license facts port.
 *
 * @package Uncanny_Automator
 * @since 7.6
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Uncanny_Agent\Ports;

use Uncanny_Automator\App\Uncanny_Agent\Domain\License_Facts;

/**
 * Supplies local Automator license facts.
 */
interface License_Facts_Port {

	/**
	 * Get the local license facts.
	 *
	 * @return License_Facts
	 */
	public function get_facts(): License_Facts;
}
