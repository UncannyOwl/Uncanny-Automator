<?php
/**
 * Automator license facts adapter for Uncanny Agent.
 *
 * @package Uncanny_Automator
 * @since 7.6
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Uncanny_Agent\Infrastructure;

use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;
use Uncanny_Automator\App\Uncanny_Agent\Domain\License_Facts;
use Uncanny_Automator\App\Uncanny_Agent\Ports\License_Facts_Port;

/**
 * Reads local license facts from Automator's license provider.
 */
final class Automator_License_Facts_Adapter implements License_Facts_Port {

	/**
	 * Automator license provider.
	 *
	 * @var License_Provider_Interface
	 */
	private License_Provider_Interface $licenses;

	/**
	 * Create the adapter.
	 *
	 * @param License_Provider_Interface $licenses Automator license provider.
	 */
	public function __construct( License_Provider_Interface $licenses ) {
		$this->licenses = $licenses;
	}

	/**
	 * Get the local license facts.
	 *
	 * Automator's license type includes local validity. A stored key shows that
	 * the site is connected to the Automator API.
	 *
	 * @return License_Facts
	 */
	public function get_facts(): License_Facts {
		return new License_Facts(
			$this->licenses->get_type(),
			'' !== trim( $this->licenses->get_key() )
		);
	}
}
