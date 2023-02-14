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

	protected $functions;
	protected $client;
	protected $is_connected;

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
		$this->set_name( 'Twitter' );

		$this->register_option( 'automator_twitter_api_key' );
		$this->register_option( 'automator_twitter_api_secret' );
		$this->register_option( 'automator_twitter_access_token' );
		$this->register_option( 'automator_twitter_access_token_secret' );

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

		if ( '1' === automator_filter_input( 'allow-user-app' ) || $this->functions->is_user_app_connected() ) {

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
					'heading' => __( 'You have successfully connected your Twitter account.', 'uncanny-automator' ),
				)
			);

			$this->is_connected = true;

		} catch ( \Exception $e ) {
			$error              = $this->functions->parse_errors( $e->getMessage() );
			$this->is_connected = false;
			$this->set_status( '' );
			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => 'Connection error',
					'content' => __( 'There was an error connecting your Twitter account: ', 'uncanny-automator' ) . $error,
				)
			);

			delete_option( '_uncannyowl_twitter_settings' );
			delete_option( 'automator_twitter_user' );

			return;
		}
	}
}


