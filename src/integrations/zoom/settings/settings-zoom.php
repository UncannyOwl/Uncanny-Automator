<?php
/**
 * Zoom settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */

namespace Uncanny_Automator;

/**
 * Zoom Settings
 */
class Zoom_Settings extends Settings\Premium_Integration_Settings {

	protected $api_key;
	protected $api_secret;
	protected $account_id;
	protected $user;
	protected $is_connected;

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'zoom-api' );

		$this->set_icon( 'ZOOM' );

		$this->set_name( 'Zoom Meetings' );

		$this->register_option( 'uap_automator_zoom_api_account_id' );
		$this->register_option( 'uap_automator_zoom_api_client_id' );
		$this->register_option( 'uap_automator_zoom_api_client_secret' );

		$this->register_option( 'uap_automator_zoom_api_settings_version' );

	}

	public function get_status() {

		if ( $this->helpers->legacy_client_connected() ) {
			$this->helpers->delete_options();
		}

		$user = automator_get_option( 'uap_zoom_api_connected_user', array() );

		return ! empty( $user['email'] ) ? 'success' : '';
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$this->account_id = trim( automator_get_option( 'uap_automator_zoom_api_account_id', '' ) );
		$this->api_key    = trim( automator_get_option( 'uap_automator_zoom_api_client_id', '' ) );
		$this->api_secret = trim( automator_get_option( 'uap_automator_zoom_api_client_secret', '' ) );

		$this->user = false;

		if ( ! empty( $this->api_key ) && ! empty( $this->api_secret ) ) {
			$this->user = automator_get_option( 'uap_zoom_api_connected_user', array() );
		}

		$this->is_connected = false;

		if ( ! empty( $this->user['email'] ) ) {
			$this->is_connected = true;
		}

		$disconnect_url = $this->helpers->disconnect_url();

		include_once 'view-zoom-v3.php';
	}

	public function settings_updated() {

		try {

			automator_delete_option( 'uap_zoom_api_connected_user' );
			automator_delete_option( '_uncannyowl_zoom_settings' );

			$this->user = $this->helpers->api_get_user_info();

			$this->add_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html__( 'You have successfully connected your Zoom Meetings account', 'uncanny-automator' ),
				)
			);

		} catch ( \Exception $e ) {

			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => 'Connection error',
					'content' => esc_html__( 'There was an error connecting your Zoom Meetings account:', 'uncanny-automator' ) . $e->getMessage(),
				)
			);
			return;
		}
	}

}

