<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Provider;

use Uncanny_Automator\Core\Lib\AI\Core\Abstracts\OpenAI_Compatible_Provider_Abstract;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\AI_Provider_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Http_Client_Interface;
use Uncanny_Automator\Core\Lib\AI\Http\Payload;
use Uncanny_Automator\Core\Lib\AI\Http\Request;
use Uncanny_Automator\Core\Lib\AI\Http\Response;
use Uncanny_Automator\Core\Lib\AI\Exception\Validation_Exception;

/**
 * Mistral AI provider implementation with clean architecture.
 *
 * Demonstrates clean composition of pure abstracts, utility traits,
 * and interface contracts. Mistral uses OpenAI-compatible response
 * format, so most functionality is inherited from the abstract classes.
 *
 * ARCHITECTURE COMPOSITION:
 * - OpenAI_Compatible_Provider_Abstract: Pure structure and parsing logic
 * - Base_Provider_Trait: Cross-cutting concerns (dependency injection)
 * - AI_Provider_Interface: Contract promises to framework
 * - Mistral-specific: Custom headers and authentication
 *
 * MISTRAL SPECIFICS:
 * - Uses Bearer token authentication (OpenAI-compatible)
 * - Same response format as OpenAI (choices[].message.content)
 *
 * BENEFITS:
 * - Minimal code (most logic inherited)
 * - Clear separation of concerns
 * - Easy to test and maintain
 * - Shared OpenAI-compatible logic
 *
 * @package Uncanny_Automator\Core\Lib\AI\Provider
 * @since 5.6
 */
final class Mistral_Provider extends OpenAI_Compatible_Provider_Abstract implements AI_Provider_Interface {

	/**
	 * Initialize Grok provider with configuration.
	 *
	 * @param Http_Client_Interface $http WordPress HTTP client adapter
	 */
	public function __construct( Http_Client_Interface $http ) {
		parent::__construct( $http );

		// Configure provider-specific settings.
		$this->set_provider_name( 'Mistral' );
		$this->set_key_config( 'automator_mistral_api_key' );
	}

	/**
	 * Create payload builder with Mistral authentication.
	 *
	 * Uses the parent's OpenAI-compatible builder but adds Mistral-specific
	 * headers.
	 *
	 * @return Payload Pre-configured payload builder
	 *
	 * @throws \RuntimeException If dependencies not initialized
	 */
	public function create_builder(): Payload {
		// You may add Mistral specific headers here.
		return $this->create_openai_builder()
			->headers( 'Content-Type', 'application/json' )
			->headers( 'Accept', 'application/json' )
			->headers( 'User-Agent', 'Uncanny Automator' );
	}

	/**
	 * Send request to Mistral API.
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
	 * Parse Mistral response into standardized format.
	 *
	 * Mistral uses OpenAI-compatible response format, so we can
	 * delegate to the parent's parsing logic. If Mistral had
	 * unique response fields, we could override extract_usage_metadata().
	 *
	 * @param array<string,mixed> $response Raw Mistral response
	 *
	 * @return Response Standardized response object
	 *
	 * @throws Validation_Exception If response has no content
	 */
	public function parse_response( array $response ): Response {
		// Same here, you may alter the response if you wish.
		return $this->parse_openai_compatible_response( $response );
	}
}
