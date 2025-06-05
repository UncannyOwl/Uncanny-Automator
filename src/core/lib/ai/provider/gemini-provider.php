<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Provider;

use Uncanny_Automator\Core\Lib\AI\Adapters\Http\Integration_Http_Client;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\AI_Provider_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_Provider_Trait;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Http_Client_Interface;
use Uncanny_Automator\Core\Lib\AI\Http\Payload;
use Uncanny_Automator\Core\Lib\AI\Http\Request;
use Uncanny_Automator\Core\Lib\AI\Http\Response;
use Uncanny_Automator\Core\Lib\AI\Exception\Validation_Exception;
use Uncanny_Automator\Core\Lib\AI\Exception\AI_Service_Exception;
use Uncanny_Automator\Core\Lib\AI\Exception\Response_Exception;

/**
 * Google Gemini AI provider implementation.
 *
 * Handles communication with Google's Gemini API.
 * Supports Gemini 1.5 Pro, Flash, and 2.0 models with multimodal capabilities.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Provider
 * @since 5.6
 */
final class Gemini_Provider implements AI_Provider_Interface {

	use Base_Provider_Trait;

	/**
	 * HTTP client for making API requests.
	 *
	 * @var Integration_Http_Client
	 */
	private $http;

	/**
	 * Initialize with HTTP client.
	 *
	 * @param Http_Client_Interface $http WordPress HTTP client adapter
	 */
	public function __construct( Http_Client_Interface $http ) {
		$this->http = $http;
	}

	/**
	 * Create payload builder with Gemini authentication.
	 *
	 * @return Payload Pre-configured payload builder
	 *
	 * @throws \RuntimeException If dependencies not initialized
	 */
	public function create_builder(): Payload {
		$this->ensure_initialized();

		return ( new Payload() )
			// Gemini uses API key as a URL parameter
			->json_content();
	}

	/**
	 * Send request to Gemini API.
	 *
	 * @param Request $payload Complete request object
	 *
	 * @return array<string,mixed> Raw API response
	 */
	public function send_request( Request $payload ): array {

		$this->ensure_initialized();

		// Generate correlation ID
		$cid = uniqid( 'cid_', true );

		$url     = (string) $payload->get_endpoint();
		$body    = $payload->get_body()->to_array();
		$headers = $payload->get_headers()->to_array();

		// Add API key to URL (Gemini uses query parameter authentication)
		$api_key = $this->config->get( 'automator_gemini_api_key' );
		$url     = add_query_arg( 'key', $api_key, $url );

		// Record metrics and log request
		$this->logger->info(
			'Gemini request',
			array(
				'url' => $url,
				'cid' => $cid,
			),
		);

		// Perform HTTP POST
		$response = $this->http->post( $url, $body, $headers );

		// Log response
		$this->logger->debug(
			'Gemini response',
			array(
				'cid'      => $cid,
				'response' => $response,
			),
		);

		return $response;
	}

	/**
	 * Parse Gemini response into standardized format.
	 *
	 * @param array<string,mixed> $response Raw Gemini response
	 *
	 * @return Response Standardized response object
	 *
	 * @throws AI_Service_Exception If API returns error
	 * @throws Validation_Exception If response has no content
	 */
	public function parse_response( array $response ): Response {

		// Check for errors first.
		if ( isset( $response['error'] ) ) {
			$message = $response['error']['message'] ?? 'unknown_error';
			$code    = $response['error']['code'] ?? 0;

			$error_message = sprintf(
				/* translators: %1$s: Error message from Gemini API */
				esc_html_x(
					'Gemini error: %1$s',
					'Gemini',
					'uncanny-automator'
				),
				esc_html( $message )
			);

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new AI_Service_Exception( $error_message, esc_html( $code ) );
		}

		// Ensure content exists using PHP 7.3+ array navigation.
		$candidates = $response['candidates'] ?? array();
		if ( ! is_countable( $candidates ) || empty( $candidates ) ) {
			throw new Validation_Exception(
				esc_html_x(
					'The model predicted a completion that results in no output. Consider adjusting your prompt',
					'Gemini',
					'uncanny-automator'
				)
			);
		}

		$first_candidate = array_key_first( $candidates ) !== null ? $candidates[ array_key_first( $candidates ) ] : null;
		if ( null === $first_candidate ) {
			throw new Response_Exception(
				esc_html_x(
					'Invalid response structure from Gemini API',
					'Gemini',
					'uncanny-automator'
				)
			);
		}

		$content_parts = $first_candidate['content']['parts'] ?? array();
		if ( ! is_countable( $content_parts ) || empty( $content_parts ) ) {
			throw new Response_Exception(
				esc_html_x(
					'No content parts found in Gemini response',
					'Gemini',
					'uncanny-automator'
				)
			);
		}

		$first_part = array_key_first( $content_parts ) !== null ? $content_parts[ array_key_first( $content_parts ) ] : null;
		$content    = $first_part['text'] ?? '';

		if ( empty( $content ) ) {
			throw new Validation_Exception(
				esc_html_x(
					'Empty content returned from Gemini API',
					'Gemini',
					'uncanny-automator'
				)
			);
		}

		// Extract usage metadata if available (PHP 7.3+ trailing commas)
		$metadata = array(
			'model'          => $first_candidate['model'] ?? '',
			'safety_ratings' => $first_candidate['safetyRatings'] ?? array(),
			'finish_reason'  => $first_candidate['finishReason'] ?? '',
		);

		return new Response( $content, $metadata, $response );
	}
}
