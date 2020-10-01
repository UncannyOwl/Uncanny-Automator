<?php

namespace Uncanny_Automator;

/**
 * Class class Automator_Integrations_Status {
 * @package Uncanny_Automator
 */
class Automator_Integrations_Status {

	public function __construct() {
	}

	/**
	 * Check if a plugin is active
	 *
	 * @param $integration This is either the plugins file name or the integrations code
	 *
	 * @return bool || null
	 */
	public function get( $integration = null ) {

		// Sanity check that there was a trigger passed
		if ( null === $integration || ! is_string( $integration ) ) {
			Utilities::log( 'ERROR: You are try to get a plugin\'s status without passing its proper integration code.', 'get_plugin_status ERROR', false, 'uap - errors' );

			return null;
		}

		$active = 0;

		if ( in_array( $integration, Set_Up_Automator::$active_integrations_code, true ) ) {
			$active = 1;
		}

		return absint( $active );
	}

}