<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Exception;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\License_Key_Requirement;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\Platform_Dispatch_Guard;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\Refused_Before_Sending_Exception;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Http\Transient_Retry;
use Uncanny_Automator\App\Infrastructure\Http\Transport_Failure;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Class Client
 *
 * Injectable HTTP client for communicating with the Automator Platform API.
 * Replaces the static Api_Server::api_call() with a testable, composable design.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Automator_Platform
 */
class Client implements Client_Interface {

	/**
	 * Seconds a platform request may take when nothing names a timeout.
	 *
	 * 60 is a deliberate 30s BELOW the platform's 90s idempotency reservation
	 * TTL + Octane max-execution cap (see the filter in {@see send()}). It is
	 * also the default of {@see Platform_Default_Timeout} for a request that
	 * never reaches this client.
	 *
	 * @var int
	 */
	public const DEFAULT_TIMEOUT_SECONDS = 60;

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
	 * Refuses a request with no license key to send, judged by the license the
	 * signer sends, and logs an action's.
	 *
	 * @var License_Key_Requirement
	 */
	private $license_key;

	/**
	 * Constructor.
	 *
	 * @param License_Header_Injector      $signer   The request signer.
	 * @param Api_Log_Store                $logger   Logger for recording API calls.
	 * @param string                       $base_url The API base URL. Falls back to AUTOMATOR_API_URL.
	 * @param License_Key_Requirement|null $license_key Refuses a request with no license key; by default, judged by the signer's license and logged to this client's log.
	 */
	public function __construct(
		License_Header_Injector $signer,
		Api_Log_Store $logger,
		string $base_url = '',
		?License_Key_Requirement $license_key = null
	) {
		$this->signer      = $signer;
		$this->logger      = $logger;
		$this->base_url    = '' !== $base_url ? $base_url : $this->resolve_base_url();
		$this->license_key = $license_key ?? new License_Key_Requirement( new Platform_Dispatch_Guard( null, null, $signer->license() ), $logger );
	}

	/**
	 * Send an API request and return the response.
	 *
	 * @param Request $request The request to send.
	 *
	 * @return Response The parsed API response.
	 *
	 * @throws Platform_Error_Exception On an API error response: the answer, with the error code its status gives.
	 * @throws Action_Error_Exception On a transport error, with the error code its message gives.
	 * @throws Refused_Before_Sending_Exception When the site has no license key to send: nothing is sent, and an action's request is logged.
	 * @throws Exception When app integration requests are disabled.
	 */
	public function send( Request $request ): Response {

		// Bail if app integration requests have been disabled.
		if ( defined( 'AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS' )
			&& true === AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS
		) {
			throw new Exception( 'App integrations have been disabled in wp-config.php.', 500 );
		}

		$url = $request->url( $this->base_url );

		$this->license_key->enforce( $request, $url );

		/**
		 * Filter the API timeout in seconds.
		 *
		 * {@see Platform_Default_Timeout} applies the same default to a platform
		 * request that never reaches this client. Default raised
		 * from 30s to 60s in 7.6.0 — escapes the old 30s ceiling (p99 is
		 * ~5s, so 60s clears the tail with room to spare) while staying a
		 * deliberate 30s BELOW the platform's 90s idempotency reservation
		 * TTL + Octane max-execution cap. Matching the cap (90=90=90)
		 * leaves zero margin: a client giving up exactly as the
		 * reservation expires lets its retry re-execute a non-idempotent
		 * handler instead of replaying the cached final. 60s also matches
		 * the value the platform's IdempotencyKey middleware already
		 * assumes. The connect-phase timeout stays short via
		 * Connect_Timeout_Injector so a dead connection still fails fast.
		 *
		 * @param int    $timeout     The timeout in seconds.
		 * @param string $request_url The full request URL.
		 */
		$timeout = (int) Dispatcher::filter( 'automator_api_timeout', $request->timeout() ?? self::DEFAULT_TIMEOUT_SECONDS, $url );

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

		// Merge per-request headers on top of the license headers the
		// signer just injected. Used primarily by v3 flows. (The
		// connection signature is NOT a request header — the request
		// carries `connection_signature` in the body; the platform
		// returns `X-Automator-Connection-Signature` as a RESPONSE
		// header via LiftSignalHeaders.)
		$per_request_headers = $request->headers();
		if ( ! empty( $per_request_headers ) ) {
			$wp_args['headers'] = array_merge(
				isset( $wp_args['headers'] ) && is_array( $wp_args['headers'] ) ? $wp_args['headers'] : array(),
				$per_request_headers
			);
		}

		/**
		 * Filter the outbound request arguments before sending.
		 *
		 * @param array       $wp_args The WordPress HTTP API arguments.
		 * @param Request $request The original request object.
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

		// The C2 site signature is NOT applied here. Every site-signed request
		// (`/app/v1/*` and `/v3/*`) — from this client, Platform_Http, or a raw
		// wp_remote_get — is signed in one place, on `http_request_args`, by
		// Platform_Request_Signature_Injector: after every body-mutating filter,
		// and once per attempt so a retry or a replay resend is signed afresh.

		$start = microtime( true );
		// Retry only connection-stage failures: no HTTP status proves that an
		// action was not executed. On the site-signed surfaces a short
		// Retry-After on a 429 / 503 — a refusal before the platform acted — is
		// waited out, once.
		$wp_response = Transient_Retry::request( $url, $wp_args, Platform_Request_Signature_Injector::is_site_signed_url( $url ) );
		$elapsed_ms  = ( microtime( true ) - $start ) * 1000.0;

		// Transport-level failure (DNS, timeout, etc.). 500 says nothing on its
		// own, so the error code comes from the message ("timed out" → api_timeout).
		if ( is_wp_error( $wp_response ) ) {
			// The message names the failure without exception text; the error code
			// still comes from WordPress's own message ("timed out" → api_timeout).
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text API error; the action log escapes on output.
			throw new Action_Error_Exception( Transport_Failure::message( $wp_response ), Error_Code::from_http_status( 500, (string) $wp_response->get_error_message() ), 500 );
		}

		$response = Response::from_wp_response( $wp_response, $elapsed_ms );

		// Log the call when action context is available (recipe_log_id, action_log_id).
		if ( null !== $request->action_data() ) {
			$this->logger->log( $request, $response );
		}

		/**
		 * Fires after an API response has been received and parsed.
		 *
		 * @param Response $response The parsed response.
		 * @param Request  $request  The original request.
		 */
		Dispatcher::action( 'automator_platform_response', $response, $request );

		// Fire deprecated legacy hooks for backward compatibility.
		$this->fire_legacy_response_hooks( $response, $request, $wp_args );

		// Async (202 with job_id) is a success — return early.
		if ( $response->is_async() ) {
			return $response;
		}

		// An error answer throws the answer itself: the platform's description
		// as the message and the status as the code, as always, plus the
		// classified answer a /v3 caller tells the customer from. A vendor error
		// under `data` goes back to a caller that asked for it.
		if ( null !== $response->error() && ! ( $response->is_vendor_error() && $request->return_vendor_errors() ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The platform's description, kept verbatim as every existing caller receives it; the action log escapes on output.
			throw Platform_Error_Exception::from_response( $response );
		}
		if ( $response->status_code() >= 500 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Transport exceptions are plain data; escape at HTML rendering.
			throw new Exception( sprintf( 'The app integration request failed (HTTP %d). Check the affected item in the connected app before running this action again. If the failure continues, contact Automator support with this HTTP code.', $response->status_code() ), $response->status_code() );
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
	 * @param Response $response The parsed response.
	 * @param Request  $request  The original request.
	 * @param array        $wp_args  The WordPress HTTP API arguments used.
	 *
	 * @return void
	 */
	private function fire_legacy_response_hooks( Response $response, Request $request, array $wp_args ): void {

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
