<?php
/**
 * Creates the settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Agustin B.
 */

namespace Uncanny_Automator;

class Twitter_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * @var \Uncanny_Automator\Twitter_Functions
	 */
	protected $functions;

	protected $client;
	protected $is_connected;

	/**
	 * The default connection method.
	 *
	 * @var string
	 */
	protected $default_connection_type = 'hybrid';

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->functions = new Twitter_Functions();

		// Define the ID
		// This should go first
		$this->set_id( 'twitter-api' );

		// Set the icon
		// This expects a valid <uo-icon> ID
		// Check the Design Guidelines to see the list of valid IDs
		$this->set_icon( 'TWITTER' );

		// Set the name
		// As this is the brand name, it probably shouldn't be translatable
		$this->set_name( 'X/Twitter' );

		$this->register_option( 'automator_twitter_api_key' );
		$this->register_option( 'automator_twitter_api_secret' );
		$this->register_option( 'automator_twitter_access_token' );
		$this->register_option( 'automator_twitter_access_token_secret' );

		// Set assets (optional)
		$this->set_css( '/twitter/settings/assets/style.css' );
		$this->set_js( '/twitter/settings/assets/script.js' );
	}

	public function get_status() {

		try {
			$this->client       = $this->functions->get_client();
			$this->is_connected = true;
		} catch ( \Exception $th ) {
			$this->client       = false;
			$this->is_connected = false;
		}

		// Set the status
		// This expects a valid <uo-tab> status
		// Check the Design Guidelines to see the list of valid statuses
		return $this->is_connected ? 'success' : '';
	}

	/**
	 * Creates the output of the settings page
	 */
	public function output() {
		if ( $this->functions->is_user_app_connected() ) {
			include_once 'view-twitter-user-app.php';
			return;
		}

		// Load view
		include_once 'view-twitter.php';
	}

	/**
	 * settings_updated
	 *
	 * @return void
	 */
	public function settings_updated() {

		try {
			$client = array(
				'api_key'            => get_option( 'automator_twitter_api_key', '' ),
				'api_secret'         => get_option( 'automator_twitter_api_secret', '' ),
				'oauth_token'        => get_option( 'automator_twitter_access_token', '' ),
				'oauth_token_secret' => get_option( 'automator_twitter_access_token_secret', '' ),
			);

			$user = $this->functions->verify_credentials( $client );

			update_option( '_uncannyowl_twitter_settings', $client );
			update_option( 'automator_twitter_user', $user );

			$this->add_alert(
				array(
					'type'    => 'success',
					'heading' => __( 'You have successfully connected your X/Twitter account.', 'uncanny-automator' ),
				)
			);

			$this->is_connected = true;

		} catch ( \Exception $e ) {

			$this->is_connected = false;
			$this->set_status( '' );
			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => 'Connection error',
					'content' => __( 'There was an error connecting your X/Twitter account: ', 'uncanny-automator' ) . wp_json_encode( $e->getMessage() ),
				)
			);

			delete_option( '_uncannyowl_twitter_settings' );
			delete_option( 'automator_twitter_user' );

			$this->set_default_connection_type( 'self-hosted' );

			return;
		}
	}

	/**
	 * Sets the default connection type.
	 *
	 * @param string $type "hybrid"|"self-hosted".
	 *
	 * @return void
	 */
	public function set_default_connection_type( $type ) {

		if ( ! is_string( $type ) ) {
			$this->default_connection_type = 'hybrid';
		}

		if ( ! in_array( $type, array( 'hybrid', 'self-hosted' ), true ) ) {
			$this->default_connection_type = 'hybrid';
		}

		$this->default_connection_type = $type;

	}

	/**
	 * Retrieves the default connection type.
	 *
	 * @return string
	 */
	public function get_default_connection_type() {
		return $this->default_connection_type;
	}

}


