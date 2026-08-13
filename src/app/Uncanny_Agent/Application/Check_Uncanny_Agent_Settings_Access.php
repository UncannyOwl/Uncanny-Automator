<?php
/**
 * Check Uncanny Agent settings access use case.
 *
 * @package Uncanny_Automator
 * @since 7.6
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Uncanny_Agent\Application;

use Uncanny_Automator\App\Uncanny_Agent\Domain\Uncanny_Agent_Settings_Access;
use Uncanny_Automator\App\Uncanny_Agent\Ports\License_Facts_Port;

/**
 * Checks access to the Uncanny Agent settings page.
 */
final class Check_Uncanny_Agent_Settings_Access {

	/**
	 * License facts source.
	 *
	 * @var License_Facts_Port
	 */
	private License_Facts_Port $licenses;

	/**
	 * Create the use case.
	 *
	 * @param License_Facts_Port $licenses License facts source.
	 */
	public function __construct( License_Facts_Port $licenses ) {
		$this->licenses = $licenses;
	}

	/**
	 * Check the current settings access.
	 *
	 * @return Uncanny_Agent_Settings_Access
	 */
	public function execute(): Uncanny_Agent_Settings_Access {
		return Uncanny_Agent_Settings_Access::evaluate( $this->licenses->get_facts() );
	}
}
