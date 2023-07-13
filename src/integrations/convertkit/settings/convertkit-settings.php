<?php
/**
 * ConvertKit_Settings class
 *
 * @package Uncanny_Automator
 */
namespace Uncanny_Automator;

/**
 * ConvertKit_Settings Settings
 */
class ConvertKit_Settings extends Settings\Premium_Integration_Settings {

	const OPTIONS_API_KEY = 'automator_convertkit_api_key';

	const OPTIONS_API_SECRET = 'automator_convertkit_api_secret';

	const OPTIONS_CLIENT = 'automator_convertkit_client';

	protected $is_connected = false;

	/**
	 * Sets up the properties of the settings page
	 */
	public function get_status() {

		$is_connected = null !== $this->helpers->get_client() ? true : false;

		return $is_connected ? 'success' : '';

	}

	/**
	 * set_properties
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'convertkit' );

		$this->set_icon( 'CONVERTKIT' );

		$this->set_name( 'ConvertKit' );

		$this->register_option( self::OPTIONS_API_KEY );

		$this->register_option( self::OPTIONS_API_SECRET );

		// Validates the API Key.
		add_filter( 'sanitize_option_' . self::OPTIONS_API_KEY, array( $this, 'validate_api_key' ), 10, 3 );

		// Validates the API Secret.
		add_filter( 'sanitize_option_' . self::OPTIONS_API_SECRET, array( $this, 'validate_api_secret' ), 40, 3 );

	}

	/**
	 * Reads the input from filter `sanitize_option_{field}` and validates the result.
	 *
	 * @return string|bool The sanitized input. Otherwise, false.
	 */
	public function validate_api_key( $sanitized_input, $option_name, $original_input ) {

		$cache_key = $option_name . '_validated_api_key';

		// Ensures run once per run-time.
		if ( wp_cache_get( $cache_key, 'convertkit' ) ) {

			return $sanitized_input;

		}

		// Set the run-time cache before a request is run.
		wp_cache_set( $cache_key, true, 'convertkit' );

		try {

			$this->helpers->verify_api_key( $sanitized_input );

			return $sanitized_input;

		} catch ( \Exception $e ) {

			automator_add_settings_error( 'automator_convertkit_connection_alerts', __( 'API Key verification failed.', 'uncanny-automator' ), $e->getMessage(), 'error' );

			return false;

		}

		// Set the run-time cache.
		wp_cache_set( $cache_key, true, 'convertkit' );

	}

	/**
	 * Reads the input from filter `sanitize_option_{field}` and validates the result.
	 *
	 * @return string|bool The sanitized input. Otherwise, false.
	 */
	public function validate_api_secret( $sanitized_input, $option_name, $original_input ) {

		$cache_key = $option_name . '_validated_api_secret';

		// Ensures run once per run-time.
		if ( wp_cache_get( $cache_key, 'convertkit' ) ) {

			return $sanitized_input;

		}

		// Bail early if there are connection alerts already. No need to validate further.
		if ( ! empty( get_settings_errors( 'automator_convertkit_connection_alerts' ) ) ) {

			// Set the run-time cache on failure.
			wp_cache_set( $cache_key, true, 'convertkit' );

			return $sanitized_input;

		}

		try {

			$response = $this->helpers->verify_api_secret( $sanitized_input );

			// At this point, both API Key and API Secret are good to go. Save the Client in the DB.
			update_option( self::OPTIONS_CLIENT, $response, true );

			$client = $this->helpers->get_client();

			/* translators: Settings flash message */
			$heading = sprintf( __( 'Your account "%s" has been connected successfully!', 'uncanny-automator' ), $client['primary_email_address'] );

			automator_add_settings_error( 'automator_convertkit_connection_alerts', $heading, '', 'success' );

			return $sanitized_input;

		} catch ( \Exception $e ) {

			automator_add_settings_error( 'automator_convertkit_connection_alerts', __( 'API Secret verification failed.', 'uncanny-automator' ), $e->getMessage(), 'error' );

			return false;

		}

		// Set the run-time cache.
		wp_cache_set( $cache_key, true, 'convertkit' );

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$this->load_js( '/convertkit/settings/assets/script.js' );

		$this->is_connected = null !== $this->helpers->get_client() ? true : false;

		$vars = array(
			'is_connected'   => $this->is_connected,
			'api_key'        => get_option( self::OPTIONS_API_KEY, '' ),
			'api_secret'     => get_option( self::OPTIONS_API_SECRET, '' ),
			'alerts'         => (array) get_settings_errors( 'automator_convertkit_connection_alerts' ),
			'client'         => $this->helpers->get_client(),
			'disconnect_url' => $this->helpers->get_disconnect_url(),
		);

		$vars['actions'] = array(
			__( 'Add a subscriber to a form', 'uncanny-automator' ),
			__( 'Add a subscriber to a sequence', 'uncanny-automator' ),
			__( 'Add a tag to a subscriber', 'uncanny-automator' ),
			__( 'Remove a tag from a subscriber', 'uncanny-automator' ),
		);

		include_once 'convertkit-view-settings.php';

	}

}

