<?php

namespace Uncanny_Automator\App_Integrations;

use Exception;

/**
 * Abstract class for App integration helpers.
 * - Common methods to support the App integration to normalize naming and methods.
 *
 * @package Uncanny_Automator
 */
abstract class App_Helpers {

	/**
	 * Integration.
	 *
	 * @var string
	 */
	protected $integration;

	/**
	 * Name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Settings ID.
	 *
	 * @var string
	 */
	protected $settings_id;

	/**
	 * API server endpoint.
	 *
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * Credentials option name.
	 *
	 * @var string
	 */
	protected $credentials_option_name;

	/**
	 * Account option name.
	 *
	 * @var string
	 */
	protected $account_option_name;

	/**
	 * API instance for this integration.
	 * Will be set to the specific extended API class for this integration.
	 *
	 * @var Api_Caller|null
	 */
	protected $api = null;

	/**
	 * Webhooks instance for this integration.
	 * Will be set to the specific extended webhooks class for this integration.
	 *
	 * @var App_Webhooks|null
	 */
	protected $webhooks = null;

	/**
	 * __construct
	 *
	 * @param array $config The config.
	 *
	 * @return void
	 */
	public function __construct( $config ) {

		// Set integration properties from config.
		$this->set_integration( $config['integration'] ?? '' );
		$this->set_name( $config['name'] ?? '' );
		$this->set_api_endpoint( $config['api_endpoint'] ?? '' );
		$this->set_settings_id( $config['settings_id'] ?? '' );

		// Set a generated credentials option name.
		$this->set_credentials_option_name( sprintf( 'automator_%s_credentials', $this->get_settings_id() ) );

		// Set a generated account option name.
		$this->set_account_option_name( sprintf( 'automator_%s_account', $this->get_settings_id() ) );

		// Optional method to set additional properties.
		$this->set_properties();
	}

	////////////////////////////////////////////////////////////
	// Getter / Setter methods
	////////////////////////////////////////////////////////////

	/**
	 * Set the properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Intentionally left blank to be overridden by child classes if needed.
	}

	/**
	 * Set the integration.
	 *
	 * @param string $integration The integration.
	 *
	 * @return void
	 */
	public function set_integration( $integration ) {
		if ( empty( $integration ) ) {
			throw new Exception( 'Integration is required' );
		}
		$this->integration = $integration;
	}

	/**
	 * Get the integration.
	 *
	 * @return string
	 */
	public function get_integration() {
		return $this->integration;
	}

	/**
	 * Set the name.
	 *
	 * @param string $name The name.
	 *
	 * @return void
	 */
	public function set_name( $name ) {
		if ( empty( $name ) ) {
			throw new Exception( 'Name is required' );
		}
		$this->name = $name;
	}

	/**
	 * Get the name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Set the settings ID.
	 *
	 * @param string $settings_id The settings ID.
	 *
	 * @return void
	 */
	public function set_settings_id( $settings_id ) {
		$settings_id = empty( $settings_id )
			? sanitize_title( $this->get_integration() )
			: sanitize_title( $settings_id );

		$this->settings_id = $settings_id;
	}

	/**
	 * Get the settings ID.
	 *
	 * @return string
	 */
	public function get_settings_id() {
		return $this->settings_id;
	}

	/**
	 * Set the endpoint.
	 *
	 * @param string $api_endpoint The API server endpoint.
	 *
	 * @return void
	 */
	public function set_api_endpoint( $api_endpoint ) {
		if ( empty( $api_endpoint ) ) {
			throw new Exception( 'API server endpoint is required' );
		}
		$this->api_endpoint = $api_endpoint;
	}

	/**
	 * Get the endpoint.
	 *
	 * @return string
	 */
	public function get_api_endpoint() {
		return $this->api_endpoint;
	}

	/**
	 * Set the credentials option name.
	 *
	 * @param string $credentials_option_name The credentials option name.
	 *
	 * @return void
	 */
	public function set_credentials_option_name( $credentials_option_name ) {
		if ( empty( $credentials_option_name ) ) {
			throw new Exception( 'Credentials option name is required' );
		}
		$this->credentials_option_name = $credentials_option_name;
	}

	/**
	 * Get the credentials option name.
	 *
	 * @return string
	 */
	public function get_credentials_option_name() {
		return $this->credentials_option_name;
	}

	/**
	 * Set the account option name.
	 *
	 * @param string $account_option_name The account option name.
	 *
	 * @return void
	 */
	public function set_account_option_name( $account_option_name ) {
		if ( empty( $account_option_name ) ) {
			throw new Exception( 'Account option name is required' );
		}
		$this->account_option_name = $account_option_name;
	}

	/**
	 * Get the account option name.
	 *
	 * @return string
	 */
	public function get_account_option_name() {
		return $this->account_option_name;
	}

	/**
	 * Set dependencies for this helper.
	 * This sets direct properties for clean access to API, webhooks, and other dependencies.
	 *
	 * @param stdClass $dependencies The dependencies object.
	 *
	 * @return void
	 */
	public function set_dependencies( $dependencies ) {
		$this->api      = $dependencies->api ?? null;
		$this->webhooks = $dependencies->webhooks ?? null;
	}

	/**
	 * Get the settings page URL.
	 *
	 * @param array $params The query parameters.
	 *
	 * @return string
	 */
	public function get_settings_page_url( $params = array() ) {
		// Get the settings page URL.
		$url = automator_get_premium_integrations_settings_url( $this->settings_id );

		// Add additional query parameters if provided.
		return empty( $params ) || ! is_array( $params )
			? $url
			: add_query_arg( $params, $url );
	}

	/**
	 * Get credentials.
	 *
	 * @return mixed - Array or string of credentials
	 */
	public function get_credentials() {
		$credentials = automator_get_option( $this->get_credentials_option_name(), '' );
		return $this->validate_credentials( $credentials );
	}

	/**
	 * Validate credentials.
	 * - Override in child classes to validate credentials.
	 *
	 * @param array $credentials -The credentials.
	 * @param array $args - Optional arguments.
	 *
	 * @return mixed - Array or string of credentials
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		return $credentials;
	}

	/**
	 * Store credentials.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return bool True if credentials were stored, false otherwise.
	 */
	public function store_credentials( $credentials ) {
		// Prepare credentials for storage.
		$credentials = $this->prepare_credentials_for_storage( $credentials );
		return automator_update_option( $this->get_credentials_option_name(), $credentials );
	}

	/**
	 * Prepare credentials for storage.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {
		return $credentials;
	}

	/**
	 * Delete credentials.
	 *
	 * @return bool True if credentials were deleted, false otherwise.
	 */
	public function delete_credentials() {
		return automator_delete_option( $this->get_credentials_option_name() );
	}

	/**
	 * Get account info.
	 *
	 * @return mixed - Array or string of account info
	 */
	public function get_account_info() {
		$account_info = automator_get_option( $this->get_account_option_name(), '' );
		return $this->validate_account_info( $account_info );
	}

	/**
	 * Validate account info.
	 * - Override in child classes to validate account info.
	 *
	 * @param array $account_info - The account info.
	 *
	 * @return mixed - Array or string of account info
	 */
	public function validate_account_info( $account_info ) {
		return $account_info;
	}

	/**
	 * Store account info.
	 *
	 * @param array $account_info The account info.
	 *
	 * @return bool True if account info was stored, false otherwise.
	 */
	public function store_account_info( $account_info ) {
		// Prepare account info for storage.
		$account_info = $this->prepare_account_info_for_storage( $account_info );
		return automator_update_option( $this->get_account_option_name(), $account_info );
	}

	/**
	 * Prepare account info for storage.
	 *
	 * @param array $account_info The account info.
	 *
	 * @return array
	 */
	public function prepare_account_info_for_storage( $account_info ) {
		return $account_info;
	}

	/**
	 * Delete account info.
	 *
	 * @return bool True if account info was deleted, false otherwise.
	 */
	public function delete_account_info() {
		return automator_delete_option( $this->get_account_option_name() );
	}

	/**
	 * Get class const.
	 *
	 * @param  string $const
	 *
	 * @return string
	 */
	public function get_const( $const_name ) {
		return constant( static::class . '::' . $const_name );
	}

	////////////////////////////////////////////////////////////
	// Recipe builder methods
	////////////////////////////////////////////////////////////

	/**
	 * Check if the request is an AJAX refresh.
	 * - Used for handling AJAX requests from Recipe Builder when requesting data for options.
	 * - This is a common pattern when saving the option data to uap_options
	 * - When this refresh context is detected the user is attempting to retrieve updated data for the integration.
	 *
	 * @return bool
	 */
	public function is_ajax_refresh() {
		$context = automator_filter_has_var( 'context', INPUT_POST )
			? automator_filter_input( 'context', INPUT_POST )
			: '';
		return 'refresh-button' === $context;
	}

	/**
	 * Check if the value is a custom value text.
	 *
	 * @param string $string_to_check
	 *
	 * @return bool
	 */
	public function is_token_custom_value_text( $string_to_check ) {
		return esc_attr__( 'Use a token/custom value', 'uncanny-automator' ) === $string_to_check;
	}

	////////////////////////////////////////////////////////////
	// Encryption helpers
	////////////////////////////////////////////////////////////

	/**
	 * Simple encryptions for data at rest ( emails etc )
	 *
	 * @param array  $data
	 * @param int    $id
	 * @param string $type
	 *
	 * @return string
	 */
	public function encrypt_data( $data, $id, $type ) {
		// Serialize data and generate random IV.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Safe serialization for data at rest encryption
		$serialized = serialize( $data );
		$iv         = random_bytes( 16 );

		// Create unique key using ID, salt, type, and IV
		$key = hash( 'sha256', $id . NONCE_SALT . $type . $iv );

		// XOR encrypt with repeating key (handles any data size)
		$encrypted = $serialized ^ str_repeat( $key, ceil( strlen( $serialized ) / strlen( $key ) ) );

		// Return IV + encrypted data as base64
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for data encoding, not obfuscation
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt data encrypted by encrypt_data().
	 *
	 * @param string $encrypted_data
	 * @param int    $id
	 * @param string $type
	 *
	 * @return array
	 */
	public function decrypt_data( $encrypted_data, $id, $type ) {
		// Handle empty or invalid input
		if ( empty( $encrypted_data ) ) {
			return array();
		}

		// If the data is already an array (unencrypted), return it directly
		if ( is_array( $encrypted_data ) ) {
			return $encrypted_data;
		}

		// Ensure we have a string for base64_decode
		if ( ! is_string( $encrypted_data ) ) {
			return array();
		}

		// Decode and validate minimum length (16 bytes for IV)
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Used for data decoding, not obfuscation
		$decoded = base64_decode( $encrypted_data );
		if ( false === $decoded || strlen( $decoded ) < 16 ) {
			return array();
		}

		// Extract IV and encrypted data
		$iv        = substr( $decoded, 0, 16 );
		$encrypted = substr( $decoded, 16 );

		// Recreate the same unique key used for encryption
		$key = hash( 'sha256', $id . NONCE_SALT . $type . $iv );

		// XOR decrypt with repeating key
		$decrypted = $encrypted ^ str_repeat( $key, ceil( strlen( $encrypted ) / strlen( $key ) ) );

		// Unserialize and return original data
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Safe unserialization for data at rest decryption
		$data = unserialize( $decrypted );
		return is_array( $data ) ? $data : array();
	}
}
