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
class LinkedIn_Settings {

	/**
	 * This trait defines properties and methods shared across all the
	 * settings pages of Premium Integrations
	 */
	use Settings\Premium_Integrations;

	protected $helper = '';
	/**
	 * Creates the settings page
	 */
	public function __construct( $helper ) {

		$this->helper = $helper;

		// Register the tab
		$this->setup_settings();

		// The methods above load even if the tab is not selected
		if ( ! $this->is_current_page_settings() ) {
			return;
		}

	}

	/**
	 * Sets up the properties of the settings page
	 */
	protected function set_properties() {

		$this->set_id( 'linkedin' );

		$this->set_icon( 'LINKEDIN' );

		$this->set_name( 'LinkedIn Pages' );

		$is_user_connected = ! empty( $this->helper->get_client() );

		$this->set_status( $is_user_connected ? 'success' : '' );

	}

	/**
	 * Returns the helper class.
	 *
	 * @return object The helper object.
	 */
	public function get_helper() {

		return $this->helper;

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$is_user_connected = ! empty( $this->helper->get_client() );

		$authentication_url = $this->helper->get_authentication_url();

		$user = $this->helper->get_connected_user();

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
