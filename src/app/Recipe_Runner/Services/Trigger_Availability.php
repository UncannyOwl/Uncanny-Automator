<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Bridge\Automator_Trigger_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Definition_Bridge;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Integration_Catalog\Services\Hidden_Items;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Whether a recipe's trigger can fire on this site, for the triggers an "All" recipe waits for.
 *
 * A trigger needs Pro when Pro is not active and no active plugin registers its code: Pro registers
 * every Pro trigger, including those in an integration Free also ships (WordPress, WooCommerce), so
 * with Pro inactive nothing listens for one. A trigger that hid itself through its own
 * `requirements_met()` is registered by Free under its integration and recorded in `Hidden_Items`,
 * so it is not counted here.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   8.0.1
 */
final class Trigger_Availability {

	/**
	 * The registered trigger definitions.
	 *
	 * @var Trigger_Definition_Bridge
	 */
	private Trigger_Definition_Bridge $definitions;

	/**
	 * Whether Pro is active; null reads the shared license manager on each call.
	 *
	 * @var License_Manager|null
	 */
	private ?License_Manager $licenses;

	/**
	 * @since 8.0.1
	 *
	 * @param Trigger_Definition_Bridge|null $definitions Optional trigger definition bridge.
	 * @param License_Manager|null           $licenses    Optional license manager; the shared one when null.
	 */
	public function __construct( ?Trigger_Definition_Bridge $definitions = null, ?License_Manager $licenses = null ) {
		$this->definitions = $definitions ?? new Automator_Trigger_Definition_Bridge();
		$this->licenses    = $licenses;
	}

	/**
	 * Whether the trigger needs Pro and Pro is not active, so it cannot fire on this site.
	 *
	 * @since 8.0.1
	 *
	 * @param string $integration The trigger's integration code, as saved on the trigger.
	 * @param string $code        The trigger's code, as saved on the trigger.
	 *
	 * @return bool
	 */
	public function needs_inactive_pro( string $integration, string $code ): bool {

		if ( $this->license_manager()->is_pro_active() ) {
			return false;
		}

		if ( null !== $this->definitions->get_trigger_definition( $code ) ) {
			return false;
		}

		return null === Hidden_Items::reason( $integration, 'trigger', $code );
	}

	/**
	 * The license manager that says whether Pro is active.
	 *
	 * @return License_Manager
	 */
	private function license_manager(): License_Manager {
		return $this->licenses ?? automator_license_manager();
	}
}
