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
class Zoom_Settings {

	/**
	 * This trait defines properties and methods shared across all the
	 * settings pages of Premium Integrations
	 */
	use Settings\Premium_Integrations;

	protected $helpers;

	/**
	 * Creates the settings page
	 */
	public function __construct( $helpers ) {

		$this->helpers = $helpers;

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

		$this->set_id( 'zoom-api' );

		$this->set_icon( 'ZOOM' );

		$this->set_name( 'Zoom Meetings' );

		$this->register_option( 'uap_automator_zoom_api_consumer_key' );
		$this->register_option( 'uap_automator_zoom_api_consumer_secret' );
		$this->register_option( 'uap_automator_zoom_api_settings_timestamp' );

		$this->register_option( 'uap_automator_zoom_api_account_id' );
		$this->register_option( 'uap_automator_zoom_api_client_id' );
		$this->register_option( 'uap_automator_zoom_api_client_secret' );

		$this->register_option( 'uap_automator_zoom_api_settings_version' );

		$this->api_key    = trim( get_option( 'uap_automator_zoom_api_consumer_key', '' ) );
		$this->api_secret = trim( get_option( 'uap_automator_zoom_api_consumer_secret', '' ) );
		$this->account_id = '';

		if ( '3' === get_option( 'uap_automator_zoom_api_settings_version', false ) ) {
			$this->account_id = trim( get_option( 'uap_automator_zoom_api_account_id', '' ) );
			$this->api_key    = trim( get_option( 'uap_automator_zoom_api_client_id', '' ) );
			$this->api_secret = trim( get_option( 'uap_automator_zoom_api_client_secret', '' ) );
		}

		$this->user = false;

		if ( ! empty( $this->api_key ) && ! empty( $this->api_secret ) ) {
			$this->user = get_option( 'uap_zoom_api_connected_user', array() );
		}

		$this->is_connected = false;

		if ( ! empty( $this->user['email'] ) ) {
			$this->is_connected = true;
		}

		$this->set_status( $this->is_connected ? 'success' : '' );
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$disconnect_url = $this->helpers->disconnect_url();

		if ( automator_filter_input( 'automator_zoom_jwt' ) ) {
			include_once 'view-zoom-v2.php';
			return;
		}

		// If old JWT app is connected, show old settings
		if ( $this->helpers->jwt_mode() && $this->is_connected ) {
			include_once 'view-zoom-v2.php';
			return;
		}

		include_once 'view-zoom-v3.php';

	}

	public function settings_updated() {

		try {

			delete_option( 'uap_zoom_api_connected_user' );
			delete_option( '_uncannyowl_zoom_settings' );

			$this->user = $this->helpers->api_get_user_info();

			$this->is_connected = true;
			$this->set_status( 'success' );

			$this->add_alert(
				array(
					'type'    => 'success',
					'heading' => __( 'You have successfully connected your Zoom Meetings account', 'uncanny-automator' ),
				)
			);

		} catch ( \Exception $e ) {
			$this->is_connected = false;
			$this->set_status( '' );
			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => 'Connection error',
					'content' => __( 'There was an error connecting your Zoom Meetings account: ', 'uncanny-automator' ) . $e->getMessage(),
				)
			);
			return;
		}
	}

}

