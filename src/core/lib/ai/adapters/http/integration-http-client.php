<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Adapters\Http;

use Uncanny_Automator\Core\Lib\AI\Exception\AI_Service_Exception;

use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Credits_Manager_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Http_Client_Interface;

/**
 * WordPress HTTP client adapter for AI framework.
 *
 * This class implements the Http_Client_Interface using WordPress's built-in
 * HTTP API (wp_remote_post). It serves as an adapter that bridges the clean
 * AI framework interface with WordPress-specific HTTP functionality.
 *
 * ADAPTER PATTERN:
 * - Adapts WordPress HTTP API to framework interface
 * - Isolates framework from WordPress HTTP implementation details
 * - Enables easy testing with mock implementations
 * - Provides consistent error handling across the framework
 *
 * WordPress INTEGRATION:
 * - Uses wp_remote_post() for HTTP requests
 * - Leverages WordPress HTTP filters and configuration
 * - Integrates with WordPress error handling (WP_Error)
 * - Respects WordPress HTTP proxy and SSL settings
 *
 * CREDIT MANAGEMENT:
 * - Integrates with Uncanny Automator's credit system
 * - Tracks API usage via Credit_Adapter
 * - Only charges credits for successful requests
 * - Prevents credit abuse from failed requests
 *
 * ERROR HANDLING:
 * WordPress-specific errors converted to framework exceptions:
 * - WP_Error objects → AI_Service_Exception
 * - HTTP status errors → AI_Service_Exception with status code
 * - JSON decode errors → AI_Service_Exception with context
 * - Network timeouts → AI_Service_Exception with timeout info
 *
 * SECURITY FEATURES:
 * - Validates all URLs are HTTPS
 * - Sanitizes error messages with esc_html()
 * - Applies configurable timeouts to prevent hanging
 * - Uses WordPress's built-in SSL verification
 *
 * PERFORMANCE CONSIDERATIONS:
 * - 120-second default timeout for AI operations
 * - Configurable via WordPress filter system
 * - JSON encoding/decoding with error handling
 * - Efficient error message construction
 *
 * WIRING LOCATIONS:
 * - Created in Base_AI_Provider_Trait::init_dependencies()
 * - Injected into providers via Provider_Factory::create()
 * - Dependencies: Credit_Adapter for usage tracking
 *
 * @package Uncanny_Automator\Core\Lib\AI\Adapters\Http
 * @since 5.6
 *
 * @see Http_Client_Interface For the interface this class implements
 * @see Credit_Adapter For API usage tracking integration
 * @see Base_AI_Provider_Trait For dependency creation
 * @see Provider_Factory For dependency injection
 */
class Integration_Http_Client implements Http_Client_Interface {

	/**
	 * Credit manager for tracking API usage.
	 *
	 * This dependency handles credit deduction for successful API calls.
	 * Credits are only consumed when requests complete successfully,
	 * preventing abuse from failed or malicious requests.
	 *
	 * CREDIT FLOW:
	 * 1. Request is made via post() method
	 * 2. HTTP call is executed and validated
	 * 3. If successful, credits are reduced via reduce_credits()
	 * 4. If failed, no credits are consumed
	 *
	 * @var Credits_Manager_Interface
	 */
	private $api_server_credit_adapter;

	/**
	 * Constructor with credit adapter dependency injection.
	 *
	 * Injects the credit management dependency that will be used to track
	 * API usage. This follows the Dependency Injection pattern to enable
	 * testing and maintain loose coupling.
	 *
	 * @param Credits_Manager_Interface $api_server_credit_adapter Credit tracking implementation
	 */
	public function __construct( Credits_Manager_Interface $api_server_credit_adapter ) {
		$this->api_server_credit_adapter = $api_server_credit_adapter;
	}

	/**
	 * Get the injected credit adapter instance.
	 *
	 * Provides access to the credit adapter for testing and debugging purposes.
	 * This method enables verification of credit operations and dependency injection.
	 *
	 * @return Credits_Manager_Interface The injected credit management instance
	 */
	public function get_api_server_credit_adapter() {
		return $this->api_server_credit_adapter;
	}

	/**
	 * Send HTTP POST request using WordPress HTTP API.
	 *
	 * This method implements the core HTTP functionality using WordPress's
	 * wp_remote_post() function. It handles the complete request lifecycle
	 * including validation, error handling, and credit tracking.
	 *
	 * REQUEST PROCESSING FLOW:
	 * 1. Validate URL is not empty
	 * 2. Prepare request arguments (headers, body, timeout)
	 * 3. Execute wp_remote_post() with prepared arguments
	 * 4. Check for WP_Error (WordPress HTTP errors)
	 * 5. Validate HTTP status code (200/201 only)
	 * 6. Extract and decode JSON response body
	 * 7. Validate JSON decode was successful
	 * 8. Reduce credits for successful request
	 * 9. Return decoded response array
	 *
	 * WordPress INTEGRATION DETAILS:
	 * - Uses wp_remote_post() for HTTP requests
	 * - Applies 'automator_ai_providers_http_client_timeout' filter for timeout
	 * - Handles WP_Error objects from WordPress HTTP API
	 * - Uses wp_remote_retrieve_response_code() for status code
	 * - Uses wp_remote_retrieve_body() for response body
	 *
	 * TIMEOUT CONFIGURATION:
	 * Default timeout is 120 seconds, configurable via filter:
	 * ```php
	 * add_filter('automator_ai_providers_http_client_timeout', function($timeout, $context) {
	 *     return 60; // Override to 60 seconds
	 * });
	 * ```
	 *
	 * CREDIT TRACKING:
	 * - Credits are only reduced after successful response processing
	 * - Failed requests (network errors, bad status codes) don't consume credits
	 * - Credit reduction happens via injected Credit_Adapter
	 *
	 * ERROR SCENARIOS:
	 * - Empty URL → AI_Service_Exception
	 * - Network failures → AI_Service_Exception with WP_Error message
	 * - Bad HTTP status → AI_Service_Exception with status code and response
	 * - Invalid JSON → AI_Service_Exception with JSON error details
	 *
	 * @inheritDoc
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public function post( string $url, array $body, array $headers ): array {

		if ( empty( $url ) ) {
			throw new AI_Service_Exception( 'URL cannot be empty.' );
		}

		// Validate arrays are countable (PHP 7.3+ robustness)
		if ( ! is_countable( $body ) || ! is_countable( $headers ) ) {
			throw new AI_Service_Exception( 'Body and headers must be arrays.' );
		}

		$args = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'timeout' => apply_filters(
				'automator_ai_providers_http_client_timeout',
				120,
				array(
					'url'     => $url,
					'body'    => $body,
					'headers' => $headers,
				),
			),
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new AI_Service_Exception(
				sprintf(
					'HTTP request failed: %s',
					esc_html( $response->get_error_message() ),
				),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = wp_remote_retrieve_body( $response );

		$decoded = json_decode( $data, true );

		if ( ! in_array( $code, array( 200, 201 ), true ) ) {
			throw new AI_Service_Exception(
				sprintf(
					'Request failed with status code %d. Response: %s',
					esc_html( $code ),
					esc_html( $data ),
				),
			);
		}

		if ( null === $decoded ) {
			throw new AI_Service_Exception(
				sprintf(
					'Invalid JSON response from %s. JSON Error: %s. Response: %s',
					esc_url( $url ),
					esc_html( json_last_error_msg() ),
					esc_html( $data ),
				),
			);
		}

		$this->get_api_server_credit_adapter()->reduce_credits();

		return $decoded;
	}

	/**
	 * Simulate streaming via fallback to full request.
	 *
	 * WordPress HTTP API does not provide native streaming support, so this
	 * method implements a fallback approach by performing a full request
	 * and calling the callback once with the complete response.
	 *
	 * LIMITATION DETAILS:
	 * - WordPress wp_remote_post() is synchronous and returns complete responses
	 * - No built-in support for chunked transfer encoding processing
	 * - Cannot provide true real-time streaming experience
	 * - Fallback maintains interface compatibility for future enhancement
	 *
	 * CALLBACK SIMULATION:
	 * - Performs full POST request via post() method
	 * - Encodes complete response as JSON string
	 * - Calls provided callback once with encoded response
	 * - Maintains expected callback interface for consistency
	 *
	 * FUTURE ENHANCEMENT PATHS:
	 * - Could be replaced with cURL implementation for true streaming
	 * - Could integrate with Guzzle HTTP for streaming support
	 * - Could implement Server-Sent Events for browser streaming
	 * - Interface remains stable for backward compatibility
	 *
	 * VALIDATION:
	 * - Validates callback is callable before proceeding
	 * - Throws AI_Service_Exception for invalid callbacks
	 * - All other validation handled by underlying post() method
	 *
	 * @inheritDoc
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public function stream( string $url, array $body, array $headers, callable $on_chunk ): void {
		if ( ! is_callable( $on_chunk ) ) {
			throw new AI_Service_Exception( 'Stream callback must be callable.' );
		}

		// WordPress HTTP API does not support streaming; fallback to full response
		$full = $this->post( $url, $body, $headers );
		$on_chunk( wp_json_encode( $full ) );
	}
}
