<?php

namespace Uncanny_Automator\App_Integrations;

use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Abstract class for app integration webhooks.
 * - Common methods to handle webhooks
 * - Follows the same patterns as App_Helpers for consistency
 *
 * @package Uncanny_Automator
 */
abstract class App_Webhooks {

	/**
	 * The extended App_Helpers instance for the integration.
	 *
	 * @var App_Helpers
	 */
	protected $helpers;

	/**
	 * API instance for this integration.
	 *
	 * @var Api_Caller
	 */
	protected $api = null;

	/**
	 * Whether the integration is connected.
	 *
	 * @var bool
	 */
	protected $is_connected;

	/**
	 * Webhook endpoint.
	 *
	 * @var string
	 */
	protected $webhook_endpoint;

	/**
	 * Webhook URL.
	 *
	 * @var string
	 */
	protected $webhook_url;

	/**
	 * Webhooks enabled option name.
	 *
	 * @var string
	 */
	protected $webhooks_enabled_option_name;

	/**
	 * Webhook key option name.
	 *
	 * @var string
	 */
	protected $webhook_key_option_name;

	/**
	 * The authorization parameter name for webhook validation.
	 * Different integrations may use different parameter names (key, token, secret, etc.)
	 * Override in child classes to set the appropriate parameter name.
	 *
	 * @var string
	 */
	protected $auth_param = 'key';

	/**
	 * Whether the webhook accepts GET requests.
	 * Default is false (POST only). Some integrations (like Mailchimp) require GET
	 * for validation handshake.
	 *
	 * @var bool
	 */
	protected $accepts_get_requests = false;

	/**
	 * Sanitized integration ID for use in option names and endpoints.
	 *
	 * @var string
	 */
	protected $sanitized_id;

	/**
	 * Shutdown data for processing.
	 *
	 * @var array
	 */
	protected $shutdown_data = array();

	/**
	 * Current request object.
	 *
	 * @var WP_REST_Request
	 */
	private $current_request;

	/**
	 * __construct
	 *
	 * @param App_Helpers $helpers The extended App_Helpers instance for the integration.
	 * @param bool $is_connected Whether the integration is connected.
	 *
	 * @return void
	 */
	final public function __construct( $helpers, $is_connected = false ) {

		// Set helpers.
		$this->helpers = $helpers;

		// Set connection status.
		$this->is_connected = $is_connected;

		// Set sanitized ID for dynamic options / endpoint names etc.
		$this->set_sanitized_id( $this->helpers->get_settings_id() );

		// Set webhook properties with defaults.
		$this->set_webhook_endpoint( '' );
		$this->set_webhook_url( '' );
		$this->set_webhooks_enabled_option_name( '' );
		$this->set_webhook_key_option_name( '' );
		$this->set_auth_param( 'key' );

		// Optional method to set additional or override legacy properties.
		$this->set_properties();
	}

	/**
	 * Initialize webhooks and register required filters.
	 *
	 * @return void
	 */
	public function initialize() {
		// Register webhook via filter if enabled
		if ( $this->should_register_webhooks() ) {
			$this->register_webhook_filters();
		}
	}

	/**
	 * Register webhook filters for Action_Manager.
	 *
	 * @return void
	 */
	private function register_webhook_filters() {

		// Sanitize the endpoint path (not a full URL, so use sanitize_text_field)
		$endpoint = sanitize_text_field( $this->get_webhook_endpoint() );

		// Extract the integration name from the endpoint (remove 'webhooks/' prefix and leading slashes)
		$filtered = preg_replace( '/^webhooks\//', '', $endpoint );
		$filtered = ltrim( $filtered, '/' );

		// Register webhook handler for the extracted endpoint.
		add_filter(
			"automator_handle_webhook_request_by_endpoint_{$filtered}",
			array( $this, 'handle_webhook_request' ),
			PHP_INT_MIN // Use min int to ensure this filter is called first.
		);

		// Register webhook endpoint for route registration
		add_filter(
			'automator_app_integrations_endpoints',
			array( $this, 'register_webhook_endpoint' )
		);
	}

	/**
	 * Register webhook endpoint for route registration.
	 *
	 * @param array $endpoints Current endpoints
	 *
	 * @return array Updated endpoints
	 */
	public function register_webhook_endpoint( $endpoints ) {
		$endpoints[] = $this->get_webhook_endpoint();
		return $endpoints;
	}

	////////////////////////////////////////////////////////////
	// Getter / Setter methods
	////////////////////////////////////////////////////////////

	/**
	 * Set API dependency for this webhooks instance.
	 *
	 * @param stdClass $dependencies The dependencies object.
	 *
	 * @return void
	 */
	public function set_dependencies( $dependencies ) {
		$this->api = $dependencies->api ?? null;
	}

	/**
	 * Set the properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Intentionally left blank to be overridden by child classes if needed.
	}

	/**
	 * Set the webhook endpoint.
	 *
	 * @param string $webhook_endpoint The webhook endpoint.
	 *
	 * @return void
	 */
	public function set_webhook_endpoint( $webhook_endpoint = '' ) {
		$webhook_endpoint = empty( $webhook_endpoint )
			? 'webhooks/' . $this->sanitized_id
			: $webhook_endpoint;

		$this->webhook_endpoint = trim( $webhook_endpoint );
	}

	/**
	 * Get the webhook endpoint.
	 *
	 * @return string
	 */
	public function get_webhook_endpoint() {
		return $this->webhook_endpoint;
	}

	/**
	 * Set the webhook URL.
	 *
	 * @param string $webhook_url The webhook URL.
	 *
	 * @return void
	 */
	public function set_webhook_url( $webhook_url = '' ) {
		$this->webhook_url = empty( $webhook_url )
			? '' // Don't generate URL here
			: esc_url_raw( $webhook_url );
	}

	/**
	 * Get the webhook URL.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		// Generate URL lazily if not set
		if ( empty( $this->webhook_url ) ) {
			$this->webhook_url = get_rest_url( null, AUTOMATOR_REST_API_END_POINT . '/' . $this->get_webhook_endpoint() );
		}

		return $this->webhook_url;
	}

	/**
	 * Set the webhooks enabled option name.
	 *
	 * @param string $webhooks_enabled_option_name The webhooks enabled option name.
	 *
	 * @return void
	 */
	public function set_webhooks_enabled_option_name( $webhooks_enabled_option_name = '' ) {
		$webhooks_enabled_option_name = empty( $webhooks_enabled_option_name )
			? sprintf( 'enable_%s_webhooks', $this->sanitized_id )
			: sanitize_key( $webhooks_enabled_option_name );

		$this->webhooks_enabled_option_name = $webhooks_enabled_option_name;
	}

	/**
	 * Get the webhooks enabled option name.
	 *
	 * @return string
	 */
	public function get_webhooks_enabled_option_name() {
		return $this->webhooks_enabled_option_name;
	}

	/**
	 * Get the webhook key option name.
	 *
	 * @return string
	 */
	public function get_webhook_key_option_name() {
		return $this->webhook_key_option_name;
	}

	/**
	 * Set the webhook key option name.
	 *
	 * @param string $webhook_key_option_name The webhook key option name.
	 *
	 * @return void
	 */
	public function set_webhook_key_option_name( $webhook_key_option_name = '' ) {
		$webhook_key_option_name = empty( $webhook_key_option_name )
			? sprintf( 'webhook_key_%s', $this->sanitized_id )
			: sanitize_key( $webhook_key_option_name );

		$this->webhook_key_option_name = $webhook_key_option_name;
	}

	/**
	 * Set the authorization parameter name.
	 *
	 * @param string $auth_param The authorization parameter name.
	 *
	 * @return void
	 */
	public function set_auth_param( $auth_param = '' ) {
		$auth_param = empty( $auth_param )
			? $this->get_auth_param()
			: sanitize_key( $auth_param );

		$this->auth_param = $auth_param;
	}

	/**
	 * Get the authorization parameter name.
	 *
	 * @return string
	 */
	public function get_auth_param() {
		return $this->auth_param;
	}

	/**
	 * Set whether the webhook accepts GET requests.
	 *
	 * @param bool $accepts Whether GET requests are accepted.
	 *
	 * @return void
	 */
	public function set_accepts_get_requests( $accepts ) {
		$this->accepts_get_requests = (bool) $accepts;
	}

	/**
	 * Check if the webhook accepts GET requests.
	 *
	 * @return bool
	 */
	public function accepts_get_requests() {
		return $this->accepts_get_requests;
	}

	/**
	 * Set the sanitized integration ID.
	 *
	 * @param string $sanitized_id The sanitized integration ID.
	 *
	 * @return void
	 */
	public function set_sanitized_id( $sanitized_id ) {
		$this->sanitized_id = sanitize_key( $sanitized_id );
	}

	/**
	 * Get the sanitized integration ID.
	 *
	 * @return string
	 */
	public function get_sanitized_id() {
		return $this->sanitized_id;
	}

	////////////////////////////////////////////////////////////
	// Webhook management methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the webhooks enabled status from WordPress options.
	 * - Checks the stored option value for webhooks enabled.
	 * - Supports both 'on' and 1 values for backwards compatibility.
	 *
	 * @return bool
	 */
	public function get_webhooks_enabled_status() {
		$option_name = $this->get_webhooks_enabled_option_name();

		if ( empty( $option_name ) ) {
			return false;
		}

		$webhook_enabled_option = automator_get_option( $option_name, false );

		// Support both 'on' and 1 values for backwards compatibility.
		return filter_var( $webhook_enabled_option, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Store the webhooks enabled status.
	 *
	 * @param bool $enabled Whether webhooks are enabled.
	 *
	 * @return bool True if status was stored, false otherwise.
	 */
	public function store_webhooks_enabled_status( $enabled ) {
		$option_name = $this->get_webhooks_enabled_option_name();

		if ( empty( $option_name ) ) {
			return false;
		}

		return automator_update_option( $option_name, $enabled ? 'on' : 'off' );
	}

	/**
	 * Delete the webhooks enabled status.
	 *
	 * @return bool True if status was deleted, false otherwise.
	 */
	public function delete_webhooks_enabled_status() {
		$option_name = $this->get_webhooks_enabled_option_name();

		if ( empty( $option_name ) ) {
			return false;
		}

		return automator_delete_option( $option_name );
	}

	/**
	 * Check if webhooks should be registered.
	 * - Checks if the integration is connected.
	 * - Checks if webhooks are enabled via stored option.
	 *
	 * @return bool
	 */
	public function should_register_webhooks() {
		// Check if the integration is connected.
		if ( ! $this->is_connected ) {
			return false;
		}

		// Check if webhooks are enabled via stored option.
		return $this->get_webhooks_enabled_status();
	}

	////////////////////////////////////////////////////////////
	// Webhook key management
	////////////////////////////////////////////////////////////

	/**
	 * Generate webhook key.
	 *
	 * @return string
	 */
	public function regenerate_webhook_key() {
		$new_key = md5( uniqid( wp_rand(), true ) );
		automator_update_option( $this->get_webhook_key_option_name(), $new_key );
		return $new_key;
	}

	/**
	 * Get the webhook key.
	 *
	 * @param bool $regenerate_if_empty Whether to regenerate the key if it doesn't exist. Default true.
	 *
	 * @return string
	 */
	public function get_webhook_key( $regenerate_if_empty = true ) {
		$webhook_key = automator_get_option( $this->get_webhook_key_option_name(), false );
		return ( empty( $webhook_key ) && $regenerate_if_empty )
			? $this->regenerate_webhook_key()
			: $webhook_key;
	}

	/**
	 * Get the webhook URL with authorization parameter.
	 *
	 * @return string
	 */
	public function get_authorized_url() {
		return add_query_arg(
			$this->auth_param,
			$this->get_webhook_key(),
			$this->get_webhook_url()
		);
	}

	////////////////////////////////////////////////////////////
	// Webhook processing
	////////////////////////////////////////////////////////////

	/**
	 * Handle the webhook request.
	 * - Public entry point following the same pattern as integration actions.
	 * - Sets request, validates, and processes the webhook.
	 *
	 * @param WP_REST_Request $request The WP_REST_Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_webhook_request( $request ) {
		// Validate the request object.
		if ( ! $request instanceof WP_REST_Request ) {
			throw new Exception( 'Invalid request object' );
		}

		// Set the current request.
		$this->current_request = $request;

		// Reject GET requests unless explicitly allowed by the integration.
		if ( WP_REST_Server::READABLE === $request->get_method() && ! $this->accepts_get_requests() ) {
			throw new Exception( 'GET requests are not supported by this webhook' );
		}

		// Validate the webhook.
		$result = $this->validate_webhook( $request );

		// If validation returned a response (e.g., handshake), return it immediately.
		if ( $result instanceof WP_REST_Response ) {
			return $result;
		}

		// Process the webhook.
		return $this->process_webhook_callback( $request );
	}

	/**
	 * Validate the incoming webhook.
	 * - Validates webhook authorization.
	 * - Override in child classes for additional validation logic.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool|WP_REST_Response
	 *
	 * @throws Exception If the webhook authorization is invalid.
	 */
	protected function validate_webhook( $request ) {
		// Validate webhook authorization.
		if ( ! $this->validate_webhook_authorization( $request ) ) {
			throw new Exception( 'Invalid webhook authorization' );
		}

		// Allow child classes to add additional validation.
		return $this->validate_webhook_request( $request );
	}

	/**
	 * Validate the webhook authorization.
	 * - Checks if the authorization parameter is present and valid.
	 * - Override in child classes for custom authorization logic.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool
	 */
	protected function validate_webhook_authorization( $request ) {
		$auth_value = $request->get_param( $this->auth_param );
		return $this->is_valid_webhook_key( $auth_value );
	}

	/**
	 * Validate if a given key matches the webhook key.
	 *
	 * @param string $key The key to validate.
	 *
	 * @return bool
	 */
	protected function is_valid_webhook_key( $key ) {
		return (string) $key === (string) $this->get_webhook_key( false );
	}

	/**
	 * Validate the webhook request.
	 * - Override in child classes for additional validation logic.
	 * - This method is called after authorization validation.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool|WP_REST_Response
	 */
	protected function validate_webhook_request( $request ) {
		// Default implementation - always return true.
		// Child classes can override for additional validation.
		return true;
	}

	/**
	 * Process the webhook callback.
	 * - Protected method for internal processing.
	 *
	 * @param WP_REST_Request $request The WP_REST_Request object.
	 *
	 * @return WP_REST_Response
	 */
	protected function process_webhook_callback( $request ) {

		// Store data for shutdown processing
		$this->shutdown_data = $this->set_shutdown_data( $request );

		// Add shutdown hook for processing
		add_action( 'shutdown', array( $this, 'process_shutdown_webhook' ) );

		// Generate response
		return $this->generate_webhook_response();
	}

	/**
	 * Get the do_action name.
	 *
	 * @return string
	 */
	protected function get_do_action_name() {
		return sprintf( 'automator_%s_webhook_received', str_replace( '-', '_', $this->get_sanitized_id() ) );
	}

	/**
	 * Set the shutdown data.
	 *
	 * @param WP_REST_Request $request The WP_REST_Request object.
	 *
	 * @return array
	 */
	protected function set_shutdown_data( $request ) {
		return array(
			'action_name'   => $this->get_do_action_name(),
			'action_params' => array( $request->get_params() ), // Default: single params array
		);
	}

	/**
	 * Process webhook request during shutdown.
	 *
	 * @return void
	 */
	public function process_shutdown_webhook() {
		if ( empty( $this->shutdown_data ) ) {
			return;
		}

		// Process the webhook
		$this->process_webhook_request( $this->shutdown_data['action_name'], $this->shutdown_data['action_params'] );

		// Clear the data
		$this->shutdown_data = array();
	}

	/**
	 * Process webhook request.
	 *
	 * @param string $action_name   The action name.
	 * @param array  $action_params The action parameters array.
	 *
	 * @return void
	 */
	protected function process_webhook_request( $action_name, $action_params ) {
		/**
		 * Dynamically call do_action with the dynamic number of parameters.
		 *
		 * This uses call_user_func_array to merge the action name with the action parameters
		 * and pass them all as separate arguments to do_action.
		 *
		 * Example with GitHub webhook:
		 * - $action_name = 'automator_github_webhook_received'
		 * - $action_params = [ $github_payload, $event ]
		 *
		 * array_merge( array( $action_name ), $action_params ) creates:
		 * [ 'automator_github_webhook_received', $github_payload, $event ]
		 *
		 * call_user_func_array then calls:
		 * do_action( 'automator_github_webhook_received', $github_payload, 'issues' )
		 *
		 * This allows integrations to define custom parameter structures while maintaining
		 * a consistent interface in the abstract class.
		 */
		call_user_func_array( 'do_action', array_merge( array( $action_name ), $action_params ) );
	}

	/**
	 * Generate webhook response.
	 * - Override in child classes to customize response.
	 *
	 * @return WP_REST_Response
	 */
	protected function generate_webhook_response() {
		$response = array( 'success' => true );

		// Allow custom response filtering
		if ( method_exists( $this, 'maybe_filter_webhook_response' ) ) {
			/** @disregard P1013 Undefined method */
			$response = $this->maybe_filter_webhook_response( $response );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get the current request object.
	 *
	 * @return \WP_REST_Request
	 */
	protected function get_current_request() {
		return $this->current_request;
	}

	/**
	 * Get the raw request body.
	 *
	 * @return string
	 */
	protected function get_raw_request_body() {
		return $this->current_request->get_body();
	}

	/**
	 * Get the decoded request body (JSON).
	 *
	 * @return array
	 */
	protected function get_decoded_request_body() {
		return json_decode( $this->current_request->get_body(), true );
	}

	/**
	 * Get the request parameters.
	 *
	 * @return array
	 */
	protected function get_request_params() {
		return $this->current_request->get_params();
	}

	/**
	 * Get a specific request header.
	 *
	 * @param string $header_name The header name.
	 *
	 * @return string|null
	 */
	protected function get_request_header( $header_name ) {
		return $this->current_request->get_header( $header_name );
	}

	////////////////////////////////////////////////////////////
	// Utility methods
	////////////////////////////////////////////////////////////

	/**
	 * Get class const.
	 *
	 * @param  string $const_name
	 *
	 * @return string
	 */
	public function get_const( $const_name ) {
		return constant( static::class . '::' . $const_name );
	}

	/**
	 * Check if the webhook timestamp is within the acceptable interval.
	 * - Useful for preventing duplicate webhook processing.
	 *
	 * @param int $timestamp The webhook timestamp.
	 * @param int $interval  The acceptable interval in seconds. Default 10.
	 *
	 * @return bool
	 */
	public function is_timestamp_acceptable( $timestamp = 0, $interval = 10 ) {
		$now = current_time( 'mysql' );
		$dt  = new \DateTime( $now, new \DateTimeZone( Automator()->get_timezone_string() ) );

		automator_log(
			array(
				'current_time' => $now,
				'datetime'     => $dt,
			),
			$this->helpers->get_name() . ': Method is_timestamp_acceptable params',
			AUTOMATOR_DEBUG_MODE,
			$this->helpers->get_settings_id() . '-params'
		);

		if ( false === $dt ) {
			return false;
		}

		// Set the timezone to UTC.
		$dt->setTimezone( new \DateTimeZone( 'UTC' ) );
		// Get the timestamp.
		$utc = strtotime( $dt->format( 'Y-m-d H:i:s' ) );
		// Get the difference between the current time and the webhook timestamp.
		$diff = absint( $utc - $timestamp );

		automator_log(
			array(
				'diff'     => $diff,
				'interval' => $interval,
			),
			$this->helpers->get_name() . ': Method is_timestamp_acceptable params',
			AUTOMATOR_DEBUG_MODE,
			$this->helpers->get_settings_id() . '-processed'
		);

		// Compare if it was recently accepted.
		return $diff <= $interval;
	}

	////////////////////////////////////////////////////////////
	// Payload value extraction helper methods
	////////////////////////////////////////////////////////////

	/**
	 * Dynamic data extraction for any event - handles arrays and objects
	 *
	 * @param array $data The webhook data (headers or body)
	 * @param string $path Dot notation path (e.g., 'issue.labels.0.name')
	 *
	 * @return mixed|string The data at the path, or empty string if not found
	 */
	public function get_payload_value( $data, $path ) {
		if ( ! is_array( $data ) ) {
			return '';
		}

		// Explode path into an array.
		$path_array = empty( $path ) ? array() : explode( '.', $path );

		return $this->traverse_data_path( $data, $path_array );
	}

	/**
	 * Recursively traverse data using path array
	 *
	 * @param mixed $data The data to traverse
	 * @param array $path_array The path array (e.g., ['repository', 'config', 'name'])
	 *
	 * @return mixed|null The value if found, null otherwise
	 */
	private function traverse_data_path( $data, $path_array ) {
		// Base case: if no path remaining, return current data.
		if ( empty( $path_array ) ) {
			return $data;
		}

		// We need to traverse deeper, so data must be an array
		if ( ! is_array( $data ) ) {
			return '';
		}

		// Get the next key and remaining path
		$key = array_shift( $path_array );
		$key = is_numeric( $key ) ? (int) $key : $key;

		// Check if key exists
		if ( ! isset( $data[ $key ] ) ) {
			return '';
		}

		// Recursively traverse with the remaining path
		return $this->traverse_data_path( $data[ $key ], $path_array );
	}
}
