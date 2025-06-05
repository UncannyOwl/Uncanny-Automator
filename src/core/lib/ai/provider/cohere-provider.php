<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Provider;

use Uncanny_Automator\Core\Lib\AI\Core\Abstracts\OpenAI_Compatible_Provider_Abstract;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\AI_Provider_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Http_Client_Interface;
use Uncanny_Automator\Core\Lib\AI\Exception\Response_Exception;
use Uncanny_Automator\Core\Lib\AI\Http\Payload;
use Uncanny_Automator\Core\Lib\AI\Http\Request;
use Uncanny_Automator\Core\Lib\AI\Http\Response;
use Uncanny_Automator\Core\Lib\AI\Exception\Validation_Exception;

/**
 * Cohere AI provider implementation.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Provider
 * @since 5.6
 */
final class Cohere_Provider extends OpenAI_Compatible_Provider_Abstract implements AI_Provider_Interface {

	/**
	 * Initialize Perplexity provider with configuration.
	 *
	 * @param Http_Client_Interface $http WordPress HTTP client adapter
	 */
	public function __construct( Http_Client_Interface $http ) {
		parent::__construct( $http );

		// Configure provider-specific settings.
		$this->set_provider_name( 'Cohere' );
		$this->set_key_config( 'automator_cohere_api_key' );
	}

	/**
	 * Create payload builder with Cohere authentication.
	 *
	 * Uses the parent's OpenAI-compatible builder but adds Cohere-specific
	 * headers.
	 *
	 * @return Payload Pre-configured payload builder
	 *
	 * @throws \RuntimeException If dependencies not initialized
	 */
	public function create_builder(): Payload {
		// You may add perplexity specific headers here.
		return $this->create_openai_builder()
			->headers( 'Content-Type', 'application/json' );
	}

	/**
	 * Send request to Cohere API.
	 *
	 * Implements interface method by delegating to parent abstract.
	 * All HTTP logic, logging, and error handling is handled by the
	 * pure abstract classes.
	 *
	 * @param Request $payload Complete request object
	 *
	 * @return array<string,mixed> Raw API response
	 */
	public function send_request( Request $payload ): array {
		// Do something extra if you wish.
		return $this->send_provider_request( $payload );
	}

	/**
	 * Parse Cohere response into standardized format.
	 *
	 * @param array<string,mixed> $response Raw Cohere response
	 *
	 * @return Response Standardized response object
	 *
	 * @throws Validation_Exception If response has no content
	 */
	public function parse_response( array $response ): Response {
		// Same here, you may alter the response if you wish.
		return $this->parse_openai_compatible_response( $response );
	}

	/**
	 * Overwrite the parent method to handle Cohere's response structure.
	 *
	 * @param array<string,mixed> $response Raw provider response
	 *
	 * @return Response Standardized response object
	 *
	 * @throws Response_Exception If response structure is invalid
	 */
	protected function parse_openai_compatible_response( array $response ): Response {

		// Extract content using overrideable method
		$content = $response['text'] ?? '';

		if ( empty( $content ) ) {
			throw new Response_Exception( 'The model has returned an empty response.', 400 );
		}

		// Extract usage metadata using overrideable method
		$metadata = $response['meta'] ?? array();

		return new Response( $content, $metadata, $response );
	}
}
