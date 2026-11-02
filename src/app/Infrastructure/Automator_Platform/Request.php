<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Class Request
 *
 * Immutable value object representing an outbound API request.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Automator_Platform
 */
final class Request {

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
	 * Optional per-request HTTP headers merged into `$wp_args['headers']`
	 * after the License_Header_Injector runs — e.g. the v3 async-dispatch
	 * and idempotency headers. Note: the connection signature is NOT here.
	 * The request carries `connection_signature` in the BODY; the platform
	 * returns `X-Automator-Connection-Signature` as a RESPONSE header
	 * (LiftSignalHeaders), which the plugin reads, never sends.
	 *
	 * @var array<string, string>
	 */
	private $headers;

	/**
	 * Optional connection_id this request is acting on behalf of. Carried
	 * on the Request so the post-response observer on
	 * `automator_platform_response` can route the response through the
	 * Connection_Error_Handler without having to parse it out of URL path
	 * or request body. Null for /v2/* calls and any /v3/* call that
	 * doesn't act against a specific Connection (e.g. /v3/sites/register).
	 *
	 * @var string|null
	 */
	private $connection_id;

	/**
	 * The caller asked for a vendor error nested under `data` to come back as a response instead of an exception;
	 * envelope and transport errors still throw.
	 *
	 * @var bool
	 */
	private $return_vendor_errors;

	/**
	 * Constructor.
	 *
	 * @param string                $endpoint      The API endpoint path.
	 * @param array                 $body          The request body.
	 * @param string                $method        The HTTP method. Default 'POST'.
	 * @param int|null              $timeout       Optional timeout in seconds.
	 * @param array|null            $action_data   Optional action data for logging.
	 * @param array<string, string> $headers       Optional per-request headers.
	 * @param string|null           $connection_id Optional connection_id this call acts on.
	 * @param bool                  $return_vendor_errors Whether a vendor error under `data` comes back instead of
	 *                                                    being thrown.
	 */
	public function __construct(
		string $endpoint,
		array $body,
		string $method = 'POST',
		?int $timeout = null,
		?array $action_data = null,
		array $headers = array(),
		?string $connection_id = null,
		bool $return_vendor_errors = false
	) {
		$this->endpoint      = $endpoint;
		$this->body          = $body;
		$this->method        = $method;
		$this->timeout       = $timeout;
		$this->action_data   = $action_data;
		$this->headers       = $headers;
		$this->connection_id = $connection_id;

		$this->return_vendor_errors = $return_vendor_errors;
	}

	/**
	 * Whether the caller asked for a vendor error nested under `data` to come back as a response.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function return_vendor_errors(): bool {
		return $this->return_vendor_errors;
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
	 * Get the per-request HTTP headers (may be empty).
	 *
	 * @return array<string, string>
	 */
	public function headers(): array {
		return $this->headers;
	}

	/**
	 * Get the connection_id this request is acting against, or null if
	 * the call isn't connection-scoped.
	 *
	 * @return string|null
	 */
	public function connection_id(): ?string {
		return $this->connection_id;
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
	 * Create a Request from the legacy $params array format used by Api_Server::api_call().
	 *
	 * Legacy format keys:
	 * - endpoint (string) — required
	 * - body (array) — required
	 * - method (string) — optional, defaults to 'POST'
	 * - action (array) — optional, maps to action_data
	 * - timeout (int) — optional
	 *
	 * connection_id is derived from `body['connection_id']` — the V3 caller
	 * base merges the credential envelope (connection_id + connection_signature)
	 * into the body, so a body-carrying-connection_id call IS connection-scoped.
	 * Without this, the VO's connection_id stayed null on every integration
	 * call, and the connection-scoped observers (Connection_Error_Handler,
	 * Async_Dispatch_Tracker) that read `->connection_id()` silently no-oped —
	 * so IDENTITY_REVOKED / SCOPE_INSUFFICIENT never marked the connection
	 * broken. Body-first matches Legacy_Upgrade_Observer's own extraction.
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
		$headers     = isset( $params['headers'] ) && is_array( $params['headers'] ) ? $params['headers'] : array();

		$connection_id = isset( $body['connection_id'] ) && '' !== (string) $body['connection_id']
			? (string) $body['connection_id']
			: null;

		return new self( $endpoint, $body, $method, $timeout, $action_data, $headers, $connection_id, ! empty( $params['return_vendor_errors'] ) );
	}

	/**
	 * Convert back to the legacy $params array format -- the inverse of from_legacy_params().
	 *
	 * The API log stores this array, never the object: log rows are decoded with
	 * allowed_classes => false, so a serialized object cannot be read back.
	 *
	 * @return array
	 */
	public function to_legacy_params(): array {
		$params = array(
			'endpoint' => $this->endpoint,
			'body'     => $this->body,
			'method'   => $this->method,
		);

		if ( null !== $this->timeout ) {
			$params['timeout'] = $this->timeout;
		}

		if ( null !== $this->action_data ) {
			$params['action'] = $this->action_data;
		}

		if ( $this->return_vendor_errors ) {
			$params['return_vendor_errors'] = true;
		}

		return $params;
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
