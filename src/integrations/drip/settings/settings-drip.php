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
class Drip_Settings extends Settings\Premium_Integration_Settings {

	protected $functions;
	protected $client;

	public function set_properties() {

		$this->set_id( 'drip' );

		$this->set_icon( 'DRIP' );

		$this->set_name( 'Drip' );
	}

	/**
	 * Sets up the properties of the settings page
	 */
	public function get_status() {
		return $this->helpers->functions->integration_status();
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$this->functions = new Drip_Functions();

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
