<?php
/**
 * Creates the settings page
 *
 * @since   4.8
 * @version 4.8
 * @package Uncanny_Automator
 * @author  Ajay V.
 */

namespace Uncanny_Automator;

/**
 * Drip_Settings
 */
class Drip_Settings {

	/**
	 * This trait defines properties and methods shared across all the
	 * settings pages of Premium Integrations
	 */
	use Settings\Premium_Integrations;

	protected $functions;
	protected $client;

	/**
	 * Creates the settings page
	 */
	public function __construct() {

		$this->functions = new Drip_Functions();

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

		$this->set_id( 'drip' );

		$this->set_icon( 'DRIP' );

		$this->set_name( 'Drip' );

		$this->set_status( $this->functions->integration_status() );
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$auth_url = $this->functions->get_auth_url();

		$disconnect_url = $this->functions->get_disconnect_url();

		$connect_status = automator_filter_input( 'connect' );

		if ( ! empty( $connect_status ) ) {
			if ( '1' === $connect_status ) {
				$this->add_alert(
					array(
						'type'    => 'success',
						'heading' => __( 'You have successfully connected your Drip account!', 'uncanny-automator' ),
					)
				);
			} elseif ( '2' === $connect_status ) {
				$this->add_alert(
					array(
						'type'    => 'error',
						'heading' => 'Connection error',
						'content' => __( 'There was an error connecting your Drip account.', 'uncanny-automator' ),
					)
				);
			} else {
				$this->add_alert(
					array(
						'type'    => 'error',
						'heading' => 'Connection error',
						'content' => __( 'There was an error connecting your Drip account: ', 'uncanny-automator' ) . $connect_status,
					)
				);
			}
		}

		include_once 'view-drip.php';

	}

}

new Drip_Settings();
