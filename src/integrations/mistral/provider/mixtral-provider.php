<?php
declare(strict_types=1);

namespace Uncanny_Automator\Integrations\Mistral\Provider;

use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\AI_Provider_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_Provider_Trait;
use Uncanny_Automator\Core\Lib\AI\Http\Request;
use Uncanny_Automator\Core\Lib\AI\Http\Payload;
use Uncanny_Automator\Core\Lib\AI\Http\Response;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Http_Client_Interface;

/**
 * Mistral AI provider implementation.
 *
 * Handles communication with Mistral's open-source API.
 * Supports Mixtral 8x7B, 8x22B, and other Mistral models.
 *
 * @package Uncanny_Automator\Integrations\Mistral\Provider
 * @since 5.6
 */
final class Mistral_Provider implements AI_Provider_Interface {

	use Base_Provider_Trait;

	/**
	 * HTTP client for making API requests.
	 *
	 * @var Http_Client_Interface
	 */
	private $http;

	/**
	 * Constructor.
	 *
	 * @param Http_Client_Interface $http Client to perform HTTP requests.
	 */
	public function __construct( Http_Client_Interface $http ) {
		$this->http = $http;
	}

	/**
	 * Create payload builder with Mistral authentication.
	 *
	 * @return Payload Pre-configured payload builder
	 *
	 * @throws \RuntimeException If dependencies not initialized
	 */
	public function create_builder(): Payload {

		$this->ensure_initialized();

		$api_key = $this->config->get( 'automator_mistral_api_key' );

		return ( new Payload() )
			->headers( 'Authorization', 'Bearer ' . $api_key )
			->headers( 'Content-Type', 'application/json' )
			->json_content();
	}

	/**
	 * Send request to Mistral API.
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

		// Record metrics and log request
		$this->logger->info(
			'Mistral request',
			array(
				'url' => $url,
				'cid' => $cid,
			)
		);

		// Perform HTTP POST
		$response = $this->http->post( $url, $body, $headers );

		$this->logger->debug(
			'Mistral response',
			array(
				'cid'      => $cid,
				'response' => $response,
			)
		);

		return $response;
	}

	/**
	 * Parse Mistral response into standardized format.
	 *
	 * @param array<string,mixed> $response Raw Mistral response
	 *
	 * @return Response Standardized response object
	 *
	 * @throws \Exception If response format is invalid
	 */
	public function parse_response( array $response ): Response {
		if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
			throw new \Exception( 'Invalid response format from Mistral API' );
		}

		$content = $response['choices'][0]['message']['content'];

		// Extract usage metadata
		$metadata = array(
			'prompt_tokens'     => $response['usage']['prompt_tokens'] ?? 0,
			'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
			'total_tokens'      => $response['usage']['total_tokens'] ?? 0,
			'model'             => $response['model'] ?? '',
		);

		return new Response( $content, $metadata, $response );
	}
}
