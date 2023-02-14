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
 * Trello_Settings
 */
class Trello_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * connected
	 *
	 * @var bool
	 */
	public $connected;

	public function get_status() {
		return $this->helpers->functions->integration_status();
	}

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'trello-api' );

		$this->set_icon( 'TRELLO' );

		$this->set_name( 'Trello' );

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$connect = automator_filter_input( 'connect' );

		if ( '1' === $connect ) {
			$this->add_alert(
				array(
					'type'    => 'success',
					'heading' => __( 'Your Trello account has been connected successfully!', 'uncanny-automator' ),
				)
			);
		} elseif ( ! empty( $connect ) ) {
			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => 'Connection error',
					'content' => __( 'There was an error connecting your Trello account: ', 'uncanny-automator' ) . $connect,
				)
			);
		}

		include_once 'view-trello.php';
	}
}
