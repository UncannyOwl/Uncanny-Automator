<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Core\Interfaces;

/**
 * Interface for HTTP client implementations used by AI providers.
 *
 * This interface abstracts HTTP operations to allow different implementations
 * while maintaining consistent behavior across all AI providers. It follows
 * the Adapter pattern to isolate the framework from specific HTTP libraries.
 *
 * DESIGN RATIONALE:
 * - Abstracts HTTP complexity from provider implementations
 * - Allows easy testing with mock HTTP clients
 * - Enables different HTTP backends (WordPress, Guzzle, cURL)
 * - Provides consistent error handling across providers
 *
 * CONCRETE IMPLEMENTATIONS:
 * - Integration_Http_Client: WordPress wp_remote_post() wrapper
 * - Future: Could have GuzzleHttpClient, CurlHttpClient, etc.
 *
 * WIRING:
 * - Injected into all providers via Provider_Factory::create()
 * - WordPress implementation created in Base_AI_Provider_Trait::init_dependencies()
 * - Wraps Credit_Adapter for API usage tracking
 *
 * ERROR HANDLING:
 * All HTTP errors are converted to AI_Service_Exception with context:
 * - Network timeouts and connection failures
 * - Invalid HTTP status codes (not 200/201)
 * - Malformed JSON responses
 * - DNS resolution failures
 *
 * SECURITY CONSIDERATIONS:
 * - All requests are HTTPS only
 * - API keys passed in headers, never URLs
 * - Request/response data logged for debugging (sanitized)
 * - Timeout protection against hanging requests
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Interfaces
 * @since 5.6
 *
 * @see Integration_Http_Client For WordPress HTTP implementation
 * @see Provider_Factory For dependency injection
 * @see Credit_Adapter For API usage tracking
 */
interface Http_Client_Interface {

	/**
	 * Send a POST request with JSON body and return decoded response.
	 *
	 * This is the main method used by all AI providers to communicate with
	 * their respective APIs. It handles the complete HTTP request lifecycle
	 * including error handling, response validation, and credit tracking.
	 *
	 * REQUEST PROCESSING:
	 * 1. Validates input parameters (URL, body, headers)
	 * 2. Encodes body as JSON
	 * 3. Adds framework-specific headers (User-Agent, timeout)
	 * 4. Executes HTTP POST request
	 * 5. Validates response status code (200/201)
	 * 6. Decodes JSON response
	 * 7. Reduces API credits via Credit_Adapter
	 * 8. Returns decoded array
	 *
	 * TIMEOUT HANDLING:
	 * - Default timeout of 120 seconds for AI API calls
	 * - Configurable via 'automator_ai_providers_http_client_timeout' filter
	 * - Prevents hanging requests that could block WordPress
	 *
	 * CREDIT TRACKING:
	 * - Successful requests trigger credit reduction
	 * - Credits are managed by API server integration
	 * - Failed requests do not consume credits
	 *
	 * USAGE PATTERN:
	 * ```php
	 * $response = $http_client->post(
	 *     'https://api.openai.com/v1/chat/completions',
	 *     ['model' => 'gpt-4', 'messages' => $messages],
	 *     ['Authorization' => 'Bearer sk-...', 'Content-Type' => 'application/json']
	 * );
	 * ```
	 *
	 * @param string               $url     HTTPS endpoint URL for the API request
	 * @param array<string,mixed>  $body    Request payload to be JSON-encoded
	 * @param array<string,string> $headers HTTP headers including authentication
	 *
	 * @return array<string,mixed> Decoded JSON response from the API
	 *
	 * @throws \Uncanny_Automator\Core\Lib\AI\Exception\AI_Service_Exception On any HTTP error or invalid response
	 */
	public function post( string $url, array $body, array $headers ): array;

	/**
	 * Stream a POST request, invoking callback on each response chunk.
	 *
	 * This method enables real-time streaming of AI responses for better user experience.
	 * However, WordPress HTTP API has limitations with streaming, so the current
	 * implementation falls back to a full request with callback simulation.
	 *
	 * STREAMING RATIONALE:
	 * - AI models can take 10-30+ seconds to generate responses
	 * - Streaming allows progressive display of results
	 * - Improves perceived performance and user engagement
	 * - Enables real-time response cancellation
	 *
	 * CURRENT LIMITATION:
	 * WordPress wp_remote_post() does not support true streaming, so this
	 * implementation performs a full request and calls the callback once
	 * with the complete response. Future versions could use alternative
	 * HTTP libraries for true streaming.
	 *
	 * CALLBACK BEHAVIOR:
	 * - Called once per "chunk" of response data
	 * - In current implementation, called once with full response
	 * - Data is JSON-encoded string, not raw response
	 * - Callback should handle partial JSON parsing
	 *
	 * FUTURE IMPLEMENTATION:
	 * Could be enhanced to use:
	 * - Server-Sent Events (SSE) for browser streaming
	 * - cURL with CURLOPT_WRITEFUNCTION for true streaming
	 * - Guzzle HTTP client with streaming support
	 *
	 * USAGE PATTERN:
	 * ```php
	 * $http_client->stream(
	 *     'https://api.openai.com/v1/chat/completions',
	 *     ['model' => 'gpt-4', 'stream' => true, 'messages' => $messages],
	 *     ['Authorization' => 'Bearer sk-...'],
	 *     function($chunk) {
	 *         // Process streaming chunk
	 *         echo $chunk;
	 *         flush();
	 *     }
	 * );
	 * ```
	 *
	 * @param string                 $url      HTTPS endpoint URL for the streaming API
	 * @param array<string,mixed>    $body     Request payload with streaming enabled
	 * @param array<string,string>   $headers  HTTP headers including authentication
	 * @param callable(string): void $on_chunk Callback function to process each response chunk
	 *
	 * @return void
	 *
	 * @throws \Uncanny_Automator\Core\Lib\AI\Exception\AI_Service_Exception On any HTTP error or invalid callback
	 */
	public function stream( string $url, array $body, array $headers, callable $on_chunk ): void;
}
