<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Api_Client;

use Exception;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Client_Interface;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Class Api_Client
 *
 * Injectable HTTP client for communicating with the Automator API.
 * Replaces the static Api_Server::api_call() with a testable, composable design.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Api_Client
 */
class Api_Client implements Api_Client_Interface {

	/**
	 * The request signer that injects license headers.
	 *
	 * @var License_Header_Injector
	 */
	private $signer;

	/**
	 * Logger for recording API calls to uap_api_log.
	 *
	 * @var Api_Log_Store
	 */
	private $logger;

	/**
	 * The API base URL.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Constructor.
	 *
	 * @param License_Header_Injector $signer   The request signer.
	 * @param Api_Log_Store  $logger   Logger for recording API calls.
	 * @param string         $base_url The API base URL. Falls back to AUTOMATOR_API_URL.
	 */
	public function __construct( License_Header_Injector $signer, Api_Log_Store $logger, string $base_url = '' ) {
		$this->signer   = $signer;
		$this->logger   = $logger;
		$this->base_url = '' !== $base_url ? $base_url : $this->resolve_base_url();
	}

	/**
	 * Send an API request and return the response.
	 *
	 * @param Api_Request $request The request to send.
	 *
	 * @return Api_Response The parsed API response.
	 *
	 * @throws Exception On transport errors, disabled integrations, or API error responses.
	 */
	public function send( Api_Request $request ): Api_Response {

		// Bail if app integration requests have been disabled.
		if ( defined( 'AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS' )
			&& true === AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS
		) {
			throw new Exception( 'App integrations have been disabled in wp-config.php.', 500 );
		}

		$url = $request->url( $this->base_url );

		/**
		 * Filter the API timeout in seconds.
		 *
		 * Migrated from Api_Server::default_api_timeout().
		 *
		 * @param int    $timeout     The timeout in seconds.
		 * @param string $request_url The full request URL.
		 */
		$timeout = (int) Dispatcher::filter( 'automator_api_timeout', $request->timeout() ?? 30, $url );

		$wp_args = $this->signer->inject(
			array(
				'method'  => $request->method(),
				'body'    => array_merge(
					$request->body(),
					array( 'plugin_ver' => AUTOMATOR_PLUGIN_VERSION )
				),
				'timeout' => $timeout,
			)
		);

		/**
		 * Filter the outbound request arguments before sending.
		 *
		 * @param array       $wp_args The WordPress HTTP API arguments.
		 * @param Api_Request $request The original request object.
		 * @param string      $url     The full URL.
		 */
		$wp_args = Dispatcher::filter( 'automator_api_client_request', $wp_args, $request, $url );

		/**
		 * Filter the outbound WP HTTP request arguments.
		 *
		 * @deprecated 7.1 Use automator_api_client_request instead.
		 *
		 * @param array $wp_args The WordPress HTTP API arguments.
		 * @param array $params  Legacy-style params (endpoint + body).
		 */
		$wp_args = Dispatcher::filter(
			'automator_call',
			$wp_args,
			array(
				'url'      => $url,
				'endpoint' => $request->endpoint(),
				'body'     => $request->body(),
			)
		);

		$start       = microtime( true );
		$wp_response = wp_remote_request( $url, $wp_args );
		$elapsed_ms  = ( microtime( true ) - $start ) * 1000.0;

		// Transport-level failure (DNS, timeout, etc.).
		if ( is_wp_error( $wp_response ) ) {
			throw new Exception(
				'WordPress was not able to make a request: ' . $wp_response->get_error_message(),
				500
			);
		}

		$response = Api_Response::from_wp_response( $wp_response, $elapsed_ms );

		// Log the call when action context is available (recipe_log_id, action_log_id).
		if ( null !== $request->action_data() ) {
			$this->logger->log( $request, $response );
		}

		/**
		 * Fires after an API response has been received and parsed.
		 *
		 * @param Api_Response $response The parsed response.
		 * @param Api_Request  $request  The original request.
		 */
		Dispatcher::action( 'automator_api_client_response', $response, $request );

		// Fire deprecated legacy hooks for backward compatibility.
		$this->fire_legacy_response_hooks( $response, $request, $wp_args );

		// Async (202 with job_id) is a success — return early.
		if ( $response->is_async() ) {
			return $response;
		}

		// If the response has an error, throw an exception.
		$error = $response->error();
		if ( null !== $error ) {
			$description = isset( $error['description'] ) ? $error['description'] : '';
			if ( empty( $description ) && isset( $error['message'] ) ) {
				$description = $error['message'];
			}
			throw new Exception( (string) $description, $response->status_code() );
		}

		return $response;
	}

	/**
	 * Fire deprecated legacy response hooks for backward compatibility.
	 *
	 * These hooks are preserved so existing code listening on the original Api_Server
	 * hooks continues to work. They should be migrated to the new hooks:
	 *   - automator_api_last_response → automator_api_client_response
	 *   - automator_api_response      → automator_api_client_response
	 *   - automator_call              → automator_api_client_request
	 *
	 * @deprecated 7.1 Use automator_api_client_response instead.
	 *
	 * @param Api_Response $response The parsed response.
	 * @param Api_Request  $request  The original request.
	 * @param array        $wp_args  The WordPress HTTP API arguments used.
	 *
	 * @return void
	 */
	private function fire_legacy_response_hooks( Api_Response $response, Api_Request $request, array $wp_args ): void {

		$raw_wp_response = $response->raw_wp_response();

		// Only fire when we have a raw WP response (skipped for transport errors).
		if ( null === $raw_wp_response ) {
			return;
		}

		$legacy_params = array(
			'endpoint' => $request->endpoint(),
			'body'     => $request->body(),
		);

		/**
		 * Filter the raw WP HTTP response.
		 *
		 * @deprecated 7.1 Use automator_api_client_response action instead.
		 *
		 * @param array $raw_wp_response The raw WP HTTP response.
		 * @param array $wp_args         The request arguments.
		 * @param array $legacy_params   The legacy params array.
		 */
		Dispatcher::filter( 'automator_api_last_response', $raw_wp_response, $wp_args, $legacy_params );

		/**
		 * Fires after an API response has been received.
		 *
		 * @deprecated 7.1 Use automator_api_client_response action instead.
		 *
		 * @param array $raw_wp_response The raw WP HTTP response.
		 * @param array $wp_args         The request arguments.
		 * @param array $legacy_params   The legacy params array.
		 */
		Dispatcher::action( 'automator_api_response', $raw_wp_response, $wp_args, $legacy_params );
	}

	/**
	 * Resolve the base URL from the AUTOMATOR_API_URL constant.
	 *
	 * @return string
	 */
	private function resolve_base_url(): string {
		if ( defined( 'AUTOMATOR_API_URL' ) ) {
			return (string) AUTOMATOR_API_URL;
		}

		return '';
	}
}
