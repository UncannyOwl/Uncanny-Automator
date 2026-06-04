<?php

namespace Uncanny_Automator;

/**
 * Class class Automator_Integrations_Status {
 *
 * @package Uncanny_Automator
 */
class Automator_Integrations_Status {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * Automator_Integrations_Status constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return Automator_Integrations_Status
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check if a plugin is active
	 *
	 * this is either the plugins file name or the integrations code
	 *
	 * @param $integration
	 *
	 * @return bool || null
	 */
	public function get( $integration = null ) {

		// Forward to Integration_Registry — the recipe runner owns this logic now.
		// Falls back to legacy during early init before recipe_runner is constructed.
		if ( isset( Automator()->recipe_runner ) ) {
			return Automator()->recipe_runner->integration_registry()->get_plugin_status( $integration );
		}

		return $this->get_legacy( $integration );
	}

	/**
	 * Legacy implementation — used during early init before recipe_runner exists.
	 *
	 * @param mixed $integration The integration code.
	 *
	 * @return int|null
	 */
	private function get_legacy( $integration ) {

		if ( null === $integration || ! is_string( $integration ) ) {
			Automator()->wp_error->add_error( 'get_plugin_status', 'ERROR: You are try to get a plugin\'s status without passing its proper integration code.', $this );

			return null;
		}

		$active = 0;

		if ( in_array( $integration, Set_Up_Automator::$active_integrations_code, true ) ) {
			$active = 1;
		}

		return absint( apply_filters( 'uncanny_automator_maybe_add_integration', $active, $integration ) );
	}
}
