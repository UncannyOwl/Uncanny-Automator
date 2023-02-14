<?php
/**
 * Creates the settings page
 *
 * @since   4.3
 * @version 1.0
 * @package Uncanny_Automator
 * @author  Joseph G.
 */

namespace Uncanny_Automator;

/**
 * LinkedIn_Settings Settings
 */
class LinkedIn_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'linkedin' );

		$this->set_icon( 'LINKEDIN' );

		$this->set_name( 'LinkedIn Pages' );

	}

	public function get_status() {

		$is_user_connected = ! empty( $this->helpers->get_client() );

		return $is_user_connected ? 'success' : '';
	}

	/**
	 * Returns the helper class.
	 *
	 * @return object The helper object.
	 */
	public function get_helper() {

		return $this->helpers;

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$is_user_connected = ! empty( $this->helpers->get_client() );

		$authentication_url = $this->helpers->get_authentication_url();

		$user = $this->helpers->get_connected_user();

		$display_name = '';

		if ( ! empty( $user ) ) {
			$display_name = implode(
				' ',
				array(
					$user['localizedFirstName'],
					$user['localizedLastName'],
				)
			);
		}

		include_once 'view-linkedin.php';

	}

}
