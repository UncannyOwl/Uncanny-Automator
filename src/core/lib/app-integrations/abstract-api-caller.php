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
	 * The new API client instance (null = use legacy Api_Server path).
	 *
	 * @var \Uncanny_Automator\App\Infrastructure\Api_Client\Api_Client|null
	 */
	protected $api_client = null;

	/**
	 * The credential request key.
	 * - property name for applying credentials to the request.
	 * - Added dynamically to allow extending classes to override it.
	 *
	 * @var string
	 */
	protected $credential_request_key = 'credentials';

	/**
	 * Default request timeout in seconds.
	 * - When set, all api_request calls will include this timeout.
	 * - Individual requests may still override via $args['include_timeout'].
	 *
	 * @var int|null
	 */
	protected $request_timeout = null;

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

		// Wire the credential-refresh seam used when an action is resent from the logs.
		$this->register_resend_credential_refresh();
	}

	/**
	 * Register the credential-refresh filter for log resends.
	 *
	 * A resend replays the request body that was stored in uap_api_log at
	 * original-run time — including the credential that was live back then. On
	 * replay, Api_Server::api_call() runs filter_params(), which fires
	 * automator_{integration}_api_call for any endpoint of the form
	 * "{version}/{integration}". We hook that filter so the stale credential is
	 * swapped for the current one before the request is re-fired.
	 *
	 * No-op unless the endpoint resolves to a 2-segment "{version}/{slug}" pair
	 * (the exact shape Api_Server::add_endpoint_parts() derives the integration
	 * slug from); otherwise the filter would never fire and registering it is
	 * pointless.
	 *
	 * @return void
	 */
	protected function register_resend_credential_refresh() {

		$parts = explode( '/', (string) $this->api_endpoint );

		if ( 2 !== count( $parts ) || '' === $parts[1] ) {
			return;
		}

		add_filter( 'automator_' . $parts[1] . '_api_call', array( $this, 'refresh_credentials_on_resend' ) );
	}

	/**
	 * Filter callback: replace the stored (possibly stale) credential in a
	 * resent request body with a freshly-resolved current one.
	 *
	 * Acts only on a resend replay ($params['resend']), only on this caller's
	 * own endpoint, and only when the original request actually carried a
	 * credential under our key — so a normal live call (credentials already
	 * injected upstream) and an intentional credential-less request are both
	 * left untouched. A resolution failure leaves the stored value in place so
	 * the replay surfaces the auth error rather than silently dropping it.
	 *
	 * @param array $params The api_call params being replayed.
	 *
	 * @return array
	 */
	public function refresh_credentials_on_resend( $params ) {

		if ( ! is_array( $params ) || empty( $params['resend'] ) ) {
			return $params;
		}

		if ( ! isset( $params['endpoint'], $params['body'] ) || ! is_array( $params['body'] ) ) {
			return $params;
		}

		if ( $params['endpoint'] !== $this->api_endpoint ) {
			return $params;
		}

		try {
			$params['body'] = $this->replace_resend_credentials( $params['body'] );
		} catch ( Exception $e ) {
			automator_log( $e->getMessage(), 'Api_Caller resend credential refresh failed', false );
		}

		return $params;
	}

	/**
	 * Overwrite the stored credentials in a resent request body with
	 * freshly-resolved current ones, and return the body.
	 *
	 * Default behaviour refreshes the value under credential_request_key when the
	 * original request carried it (so a credential-less request is left alone).
	 * Integrations that bake credentials under custom keys — e.g. a bare token
	 * plus a whole credentials object — override this to refresh those keys.
	 *
	 * @param array $body The stored request body being replayed.
	 *
	 * @return array
	 * @throws Exception If the current credentials cannot be resolved.
	 */
	protected function replace_resend_credentials( $body ) {

		$key = $this->get_credential_request_key();

		if ( array_key_exists( $key, $body ) ) {
			$body[ $key ] = $this->get_api_request_credentials( array() );
		}

		return $body;
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
	 * Get the default request timeout.
	 *
	 * @return int|null
	 */
	protected function get_request_timeout() {
		return $this->request_timeout;
	}

	/**
	 * Set the default request timeout in seconds.
	 *
	 * @param int|null $timeout The timeout in seconds, or null to disable.
	 *
	 * @return void
	 */
	protected function set_request_timeout( $timeout ) {
		$this->request_timeout = $timeout;
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
	 * Set the new API client for this caller.
	 * When set, api_request() will use Api_Client::send() instead of Api_Server::api_call().
	 *
	 * @param \Uncanny_Automator\App\Infrastructure\Api_Client\Api_Client $api_client The API client.
	 * @return void
	 */
	public function set_api_client( $api_client ): void {
		$this->api_client = $api_client;
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
				'include_timeout'     => $this->request_timeout,
			)
		);

		// Set args to variables.
		$check_errors        = ! wp_validate_boolean( $args['exclude_error_check'] );
		$include_timeout     = ! is_null( $args['include_timeout'] ) && 0 < absint( $args['include_timeout'] );
		$include_credentials = ! wp_validate_boolean( $args['exclude_credentials'] );

		try {
			// If credentials are not excluded, include them in the request.
			if ( $include_credentials ) {
				$body[ $this->get_credential_request_key() ] = $this->get_api_request_credentials( $args );
			}

			// Build the request parameters.
			$params = array(
				'endpoint' => $this->api_endpoint,
				'body'     => $body,
				'action'   => $action_data,
			);

			// If a timeout is included, set it.
			if ( $include_timeout ) {
				$params['timeout'] = absint( $args['include_timeout'] );
			}

			// Make the API request.
			$response = $this->make_api_call( $params );

		} catch ( Exception $e ) {
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
			// Re-throw the original exception.
			throw $e;
		}

		// If errors are not specifically skipped, check for them.
		if ( $check_errors ) {
			$this->check_for_errors( $response, $args );
		}

		return $response;
	}

	/**
	 * Make the API call — uses new Api_Client if available, falls back to legacy Api_Server.
	 *
	 * @param array $params The request parameters.
	 * @return array The response array.
	 * @throws \Exception On API errors.
	 */
	protected function make_api_call( array $params ): array {
		// Use new Api_Client if injected.
		if ( null !== $this->api_client ) {
			$request  = \Uncanny_Automator\App\Infrastructure\Api_Client\Api_Request::from_legacy_params( $params );
			$response = $this->api_client->send( $request );

			/**
			 * Fires when an API call uses the new Api_Client path.
			 *
			 * @since 7.1
			 *
			 * @param array  $params   The legacy params array.
			 * @param object $response The Api_Response object.
			 */
			do_action( 'automator_api_call_via_client', $params, $response );

			return $response->to_legacy_array();
		}

		// Legacy path — will be removed when all integrations use the new client.
		return Api_Server::api_call( $params );
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
		$status = $response['statusCode'] ?? 0;
		if ( $status >= 400 && $status < 500 ) {
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

		// Match against registered patterns first (e.g. credential errors).
		if ( $error_text ) {
			foreach ( $this->error_messages as $pattern => $error ) {
				if ( false !== strpos( $error_text, $pattern ) ) {
					throw new Exception( esc_html( $this->format_error_message( $error ) ) );
				}
			}
		}

		// Surface the real platform/vendor message. When nothing usable is
		// present we leave it to the original re-thrown exception in api_request.
		$message = $this->extract_error_message( $response );
		if ( '' !== $message ) {
			throw new Exception( esc_html( $message ), absint( $response['statusCode'] ?? 0 ) );
		}
	}

	/**
	 * Extract a human-readable message from an error response.
	 *
	 * The platform wraps every upstream failure in a consistent envelope: the
	 * actionable reason lives in error.description (with error.message as a
	 * generic summary). On api_request's exception path that message is re-passed
	 * as a plain $response['error'] string. Integrations should rely on this
	 * shared extraction rather than reinventing it (or dumping the whole
	 * response / falling back to a generic status-code string).
	 *
	 * @param array $response The response.
	 *
	 * @return string The message, or '' when nothing usable is present.
	 */
	protected function extract_error_message( $response ) {

		$message = '';

		// Vendor-native message, if the platform forwarded the upstream body.
		if ( ! empty( $response['data']['message'] ) && is_string( $response['data']['message'] ) ) {
			$message = $response['data']['message'];
		} else {
			// Platform error envelope: array { description, message } on the
			// normal path, or a plain string on api_request's exception path.
			$error = $response['error'] ?? '';

			if ( is_array( $error ) ) {
				$message = $error['description'] ?? $error['message'] ?? '';
			} elseif ( is_string( $error ) ) {
				$message = $error;
			}

			// Vendor error nested in data.
			if ( '' === $message && ! empty( $response['data']['error'] ) && is_string( $response['data']['error'] ) ) {
				$message = $response['data']['error'];
			}
		}

		// Strip the legacy Api_Server prefix so callers (and post-processors like
		// the WhatsApp caller) get the clean upstream text either way.
		$message = preg_replace( '/^API has responded with an error message:\s*/i', '', (string) $message );

		return trim( $message );
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
