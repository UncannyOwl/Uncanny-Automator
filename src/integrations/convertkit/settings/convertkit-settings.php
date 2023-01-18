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
class ConvertKit_Settings {

	use Settings\Premium_Integrations;

	const OPTIONS_API_KEY = 'automator_convertkit_api_key';

	const OPTIONS_API_SECRET = 'automator_convertkit_api_secret';

	const OPTIONS_CLIENT = 'automator_convertkit_client';

	protected $helper = null;

	protected $is_connected = false;

	/**
	 * Creates the settings page
	 */
	public function __construct( $helper ) {

		$this->helper = $helper;

		// Registers the tab.
		$this->setup_settings();

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

			$this->helper->verify_api_key( $sanitized_input );

			return $sanitized_input;

		} catch ( \Exception $e ) {

			add_settings_error( 'automator_convertkit_connection_alerts', __( 'API Key verification failed.', 'uncanny-automator' ), $e->getMessage(), 'error' );

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

			$response = $this->helper->verify_api_secret( $sanitized_input );

			// At this point, both API Key and API Secret are good to go. Save the Client in the DB.
			update_option( self::OPTIONS_CLIENT, $response, false );

			$client = $this->helper->get_client();

			/* translators: Settings flash message */
			$heading = sprintf( __( 'Your account "%s" has been connected successfully!', 'uncanny-automator' ), $client['primary_email_address'] );

			add_settings_error( 'automator_convertkit_connection_alerts', $heading, '', 'success' );

			return $sanitized_input;

		} catch ( \Exception $e ) {

			add_settings_error( 'automator_convertkit_connection_alerts', __( 'API Secret verification failed.', 'uncanny-automator' ), $e->getMessage(), 'error' );

			return false;

		}

		// Set the run-time cache.
		wp_cache_set( $cache_key, true, 'convertkit' );

	}

	/**
	 * Sets up the properties of the settings page
	 */
	protected function set_properties() {

		$this->set_id( 'convertkit' );

		$this->set_icon( 'CONVERTKIT' );

		$this->set_name( 'ConvertKit' );

		$this->register_option( self::OPTIONS_API_KEY );

		$this->register_option( self::OPTIONS_API_SECRET );

		$this->is_connected = null !== $this->helper->get_client() ? true : false;

		$this->set_js( '/convertkit/settings/assets/script.js' );

		$this->set_status( $this->is_connected ? 'success' : '' );

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$vars = array(
			'is_connected'   => $this->is_connected,
			'api_key'        => get_option( self::OPTIONS_API_KEY, '' ),
			'api_secret'     => get_option( self::OPTIONS_API_SECRET, '' ),
			'alerts'         => (array) get_settings_errors( 'automator_convertkit_connection_alerts' ),
			'client'         => $this->helper->get_client(),
			'disconnect_url' => $this->helper->get_disconnect_url(),
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

