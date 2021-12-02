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

		// Sanity check that there was a trigger passed
		if ( null === $integration || ! is_string( $integration ) ) {
			Automator()->error->add_error( 'get_plugin_status', 'ERROR: You are try to get a plugin\'s status without passing its proper integration code.', $this );

			return null;
		}

		$active = 0;

		if ( in_array( $integration, Set_Up_Automator::$active_integrations_code, true ) ) {
			$active = 1;
		}

		return absint( apply_filters( 'uncanny_automator_maybe_add_integration', $active, $integration ) );
	}

}
