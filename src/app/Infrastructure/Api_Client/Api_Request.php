<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Api_Client;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Class Api_Request
 *
 * Immutable value object representing an outbound API request.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Api_Client
 */
final class Api_Request {

	/**
	 * The API endpoint path (e.g. 'v2/slack').
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * The request body as an associative array.
	 *
	 * @var array
	 */
	private $body;

	/**
	 * The HTTP method (e.g. 'POST', 'GET').
	 *
	 * @var string
	 */
	private $method;

	/**
	 * Optional request timeout in seconds. Null means use the default.
	 *
	 * @var int|null
	 */
	private $timeout;

	/**
	 * Optional action data used for logging purposes.
	 *
	 * Contains keys like recipe_log_id, action_log_id, trigger_log_id.
	 *
	 * @var array|null
	 */
	private $action_data;

	/**
	 * Constructor.
	 *
	 * @param string     $endpoint    The API endpoint path.
	 * @param array      $body        The request body.
	 * @param string     $method      The HTTP method. Default 'POST'.
	 * @param int|null   $timeout     Optional timeout in seconds.
	 * @param array|null $action_data Optional action data for logging.
	 */
	public function __construct(
		string $endpoint,
		array $body,
		string $method = 'POST',
		?int $timeout = null,
		?array $action_data = null
	) {
		$this->endpoint    = $endpoint;
		$this->body        = $body;
		$this->method      = $method;
		$this->timeout     = $timeout;
		$this->action_data = $action_data;
	}

	/**
	 * Get the API endpoint path.
	 *
	 * @return string
	 */
	public function endpoint(): string {
		return $this->endpoint;
	}

	/**
	 * Get the request body.
	 *
	 * @return array
	 */
	public function body(): array {
		return $this->body;
	}

	/**
	 * Get the HTTP method.
	 *
	 * @return string
	 */
	public function method(): string {
		return $this->method;
	}

	/**
	 * Get the timeout in seconds, or null for default.
	 *
	 * @return int|null
	 */
	public function timeout(): ?int {
		return $this->timeout;
	}

	/**
	 * Get the action data for logging, or null if not set.
	 *
	 * @return array|null
	 */
	public function action_data(): ?array {
		return $this->action_data;
	}

	/**
	 * Build the full URL by prepending the base URL to the endpoint.
	 *
	 * @param string $base_url The API base URL.
	 *
	 * @return string
	 */
	public function url( string $base_url ): string {
		return $base_url . $this->endpoint;
	}

	/**
	 * Create an Api_Request from the legacy $params array format used by Api_Server::api_call().
	 *
	 * Legacy format keys:
	 * - endpoint (string) — required
	 * - body (array) — required
	 * - method (string) — optional, defaults to 'POST'
	 * - action (array) — optional, maps to action_data
	 * - timeout (int) — optional
	 *
	 * @param array $params The legacy parameters array.
	 *
	 * @return self
	 */
	public static function from_legacy_params( array $params ): self {

		// Apply the legacy param filters migrated from Api_Server::filter_params().
		$params = self::apply_legacy_param_filters( $params );

		$endpoint    = isset( $params['endpoint'] ) ? (string) $params['endpoint'] : '';
		$body        = isset( $params['body'] ) ? (array) $params['body'] : array();
		$method      = isset( $params['method'] ) ? (string) $params['method'] : 'POST';
		$timeout     = isset( $params['timeout'] ) ? (int) $params['timeout'] : null;
		$action_data = isset( $params['action'] ) ? (array) $params['action'] : null;

		return new self( $endpoint, $body, $method, $timeout, $action_data );
	}

	/**
	 * Apply legacy parameter filters migrated from Api_Server::filter_params().
	 *
	 * These filters allow third-party code to modify API call parameters.
	 * Preserved for backward compatibility with existing filter callbacks.
	 *
	 * @param array $params The legacy parameters array.
	 *
	 * @return array The filtered parameters.
	 */
	private static function apply_legacy_param_filters( array $params ): array {

		/**
		 * Filter all outbound API call parameters.
		 *
		 * Migrated from Api_Server::filter_params().
		 *
		 * @param array $params The API call parameters.
		 */
		$params = Dispatcher::filter( 'automator_api_call', $params );

		if ( ! empty( $params['integration'] ) ) {

			/**
			 * Filter API call parameters for a specific integration.
			 *
			 * Dynamic filter: automator_{integration}_api_call
			 * Migrated from Api_Server::filter_params().
			 *
			 * @param array $params The API call parameters.
			 */
			$integration = sanitize_key( (string) $params['integration'] );
			$params      = Dispatcher::filter( 'automator_' . $integration . '_api_call', $params );

			if ( ! empty( $params['body']['action'] ) ) {

				/**
				 * Filter API call parameters for a specific integration action.
				 *
				 * Dynamic filter: automator_{integration}_{action}_api_call
				 * Migrated from Api_Server::filter_params().
				 *
				 * @param array $params The API call parameters.
				 */
				$action = sanitize_key( (string) $params['body']['action'] );
				$params = Dispatcher::filter( 'automator_' . $integration . '_' . $action . '_api_call', $params );
			}
		}

		return $params;
	}
}
