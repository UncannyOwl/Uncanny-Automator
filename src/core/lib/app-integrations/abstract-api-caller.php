<?php

namespace Uncanny_Automator\App_Integrations;

use Exception;
use Uncanny_Automator\Api_Server;

/**
 * Abstract class for app integration Api caller.
 * - Common methods to make API requests
 * - Common method to inject credentials as needed
 * - Common method to handle errors and register additional error messages with help links
 *
 * @package Uncanny_Automator
 */
abstract class Api_Caller {

	/**
	 * The extended App_Helpers instance for the integration.
	 *
	 * @var App_Helpers
	 */
	protected $helpers;

	/**
	 * Webhooks instance for this integration.
	 *
	 * @var App_Webhooks|null
	 */
	protected $webhooks = null;

	/**
	 * The API endpoint.
	 *
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * Common error patterns and their user-friendly messages.
	 *
	 * @var array
	 */
	protected $error_messages = array();

	/**
	 * The credential request key.
	 * - property name for applying credentials to the request.
	 * - Added dynamically to allow extending classes to override it.
	 *
	 * @var string
	 */
	protected $credential_request_key = 'credentials';

	/**
	 * __construct
	 *
	 * @param  mixed $helpers
	 *
	 * @return void
	 */
	public function __construct( $helpers ) {

		// Set helpers.
		$this->helpers = $helpers;

		// Register common error messages.
		$this->register_error_messages(
			array(
				'invalid credentials' => array(
					// translators: %s: Settings page URL
					'message'   => esc_html_x( 'Your connection has expired or is invalid. [reconnect your account](%s)', 'API error message', 'uncanny-automator' ),
					'help_link' => $this->helpers->get_settings_page_url(),
				),
			)
		);

		// Set the API endpoint ( will error out if not set )
		if ( method_exists( $this->helpers, 'get_api_endpoint' ) ) {
			$this->set_api_endpoint( $this->helpers->get_api_endpoint() );
		}

		// Give calling class a method to set properties.
		$this->set_properties();
	}

	/**
	 * Set class properties.
	 * Override this method in child classes to set the API endpoint and other properties.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function set_properties() {
		// $this->register_error_messages( array() );
	}

	/**
	 * Get the API endpoint.
	 *
	 * @return string
	 */
	public function get_api_endpoint() {
		return $this->api_endpoint;
	}

	/**
	 * Set the API endpoint.
	 *
	 * @return string
	 */
	protected function set_api_endpoint( $endpoint ) {
		$this->api_endpoint = $endpoint;
	}

	/**
	 * Get the credential request key.
	 *
	 * @return string
	 */
	protected function get_credential_request_key() {
		return $this->credential_request_key;
	}

	/**
	 * Set the credential request key.
	 *
	 * @param string $key The key.
	 *
	 * @return void
	 */
	protected function set_credential_request_key( $key ) {
		$this->credential_request_key = $key;
	}

	/**
	 * Set webhooks dependency.
	 *
	 * @param stdClass $dependencies The dependencies object.
	 *
	 * @return void
	 */
	public function set_dependencies( $dependencies ) {
		$this->webhooks = $dependencies->webhooks ?? null;
	}

	/**
	 * API request - Common method to make API requests to API server.
	 *
	 * @param mixed $body        The body of the request or the single action string
	 * @param array $action_data The action data ( required for retriggering webhooks from logs )
	 * @param array $args        Method arguments
	 *  @property exclude_credentials - boolean default false
	 *  @property exclude_error_check - boolean default false
	 *  @property include_timeout - integer default null
	 *  @property any additional parameters for handling responses.
	 *
	 * @return array
	 */
	public function api_request( $body, $action_data = null, $args = array() ) {

		// If the body is a string, convert it to an array with the action key.
		if ( is_string( $body ) ) {
			$body = array( 'action' => $body );
		}

		// Parse arguments for credentials and error check
		$args = wp_parse_args(
			$args,
			array(
				'exclude_credentials' => false,
				'exclude_error_check' => false,
				'include_timeout'     => null,
			)
		);

		// If credentials are not excluded, include them in the request.
		if ( ! wp_validate_boolean( $args['exclude_credentials'] ) ) {
			$body[ $this->get_credential_request_key() ] = $this->get_api_request_credentials( $args );
		}

		// Build the request parameters.
		$params = array(
			'endpoint' => $this->api_endpoint,
			'body'     => $body,
			'action'   => $action_data,
		);

		// If a timeout is included, set it.
		if ( ! is_null( $args['include_timeout'] ) && 0 < absint( $args['include_timeout'] ) ) {
			$params['timeout'] = absint( $args['include_timeout'] );
		}

		$check_errors = ! wp_validate_boolean( $args['exclude_error_check'] );

		// Make the API request.
		try {
			$response = Api_Server::api_call( $params );
		} catch ( \Exception $e ) {
			// If errors are not specifically skipped, check for them.
			if ( $check_errors ) {
				$this->check_for_errors(
					array(
						'error'      => $e->getMessage(),
						'statusCode' => $e->getCode(),
					),
					$args
				);
			}
			// Re-throw the original exception
			throw $e;
		}

		// If errors are not specifically skipped, check for them.
		if ( $check_errors ) {
			$this->check_for_errors( $response, $args );
		}

		return $response;
	}

	/**
	 * Get the API request credentials.
	 *
	 * @param array $args The arguments.
	 *
	 * @return mixed - Array or string of credentials
	 * @throws Exception If credentials are invalid
	 */
	public function get_api_request_credentials( $args ) {

		// Add check if get_credentials method exists.
		if ( ! method_exists( $this->helpers, 'get_credentials' ) ) {
			throw new Exception( 'Helpers class does not have a get_credentials method' );
		}

		// Get the credentials.
		$credentials = $this->helpers->get_credentials();

		// If no credentials are found, throw an error.
		if ( empty( $credentials ) ) {
			throw new Exception( 'Invalid request credentials' );
		}

		// Prepare / sanitize / format the credentials.
		return $this->prepare_request_credentials( $credentials, $args );
	}

	/**
	 * Optionally prepare credentials for use in API requests.
	 * This method allows integrations to customize, and sanitize how request credentials are prepared
	 * before they are used in API calls.
	 *
	 * @param array $credentials The credentials to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return mixed The prepared credentials, format depends on integration needs.
	 * @throws Exception If credentials are invalid
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return $credentials;
	}

	/**
	 * Check for errors.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs
	 */
	public function check_for_errors( $response, $args = array() ) {
		if ( isset( $response['statusCode'] ) && 400 === $response['statusCode'] ) {
			$this->handle_400_error( $response, $args );
		}
	}

	/**
	 * Handle 400-level errors with custom messages
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs
	 */
	protected function handle_400_error( $response, $args ) {

		$error_text = $this->get_error_text( $response );
		if ( ! $error_text ) {
			return;
		}

		// Try to match against our common error messages
		foreach ( $this->error_messages as $pattern => $error ) {
			if ( false !== strpos( $error_text, $pattern ) ) {
				throw new Exception( esc_html( $this->format_error_message( $error ) ) );
			}
		}

		// Fall back to original message if no match found
		if ( isset( $response['data']['message'] ) ) {
			throw new Exception( esc_html( $response['data']['message'] ) );
		}
	}

	/**
	 * Get error text from response.
	 *
	 * @param array $response The response.
	 *
	 * @return string|false
	 */
	protected function get_error_text( $response ) {
		// Check from common locations.
		$error = $response['data']['message']
			?? $response['data']['error']
			?? $response['error']
			?? false;

		// Return the error text if it's a string.
		return is_string( $error )
			? strtolower( $error )
			: false;
	}

	/**
	 * Format error message with help link
	 *
	 * @param array $error The error.
	 *
	 * @return string
	 */
	protected function format_error_message( $error ) {
		// If the message is not set, return an empty string.
		if ( ! isset( $error['message'] ) ) {
			return '';
		}

		// If no help link is provided, return the message as is.
		if ( empty( $error['help_link'] ) ) {
			return esc_html( $error['message'] );
		}

		// Only use sprintf if the message contains a placeholder.
		if ( false !== strpos( $error['message'], '%s' ) ) {
			return sprintf( esc_html( $error['message'] ), esc_url( $error['help_link'] ) );
		}

		// If no placeholder found, return the message as is.
		return esc_html( $error['message'] );
	}

	/**
	 * Register additional error messages
	 *
	 * @param array $messages Additional error messages to register
	 * Format: [
	 *     'pattern' => [
	 *         'message'   => 'User-friendly message with [optional help link](%s)',
	 *         'help_link' => 'settings|https://help.example.com',
	 *     ]
	 * ]
	 *
	 * @return void
	 */
	protected function register_error_messages( $messages ) {
		$this->error_messages = array_merge( $this->error_messages, $messages );
	}

	/**
	 * Validate the action context.
	 *
	 * @return void
	 * @throws Exception If not called during process_action
	 */
	protected function validate_action_context() {
		if ( ! did_action( 'automator_before_process_action' ) ) {
			throw new Exception( esc_html_x( 'This action may only be used in an Automator action context.', 'API error message', 'uncanny-automator' ) );
		}
	}
}
