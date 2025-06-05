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
 * DeepSeek AI provider implementation.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Provider
 * @since 5.6
 */
final class DeepSeek_Provider extends OpenAI_Compatible_Provider_Abstract implements AI_Provider_Interface {

	/**
	 * Initialize Perplexity provider with configuration.
	 *
	 * @param Http_Client_Interface $http WordPress HTTP client adapter
	 */
	public function __construct( Http_Client_Interface $http ) {
		parent::__construct( $http );

		// Configure provider-specific settings.
		$this->set_provider_name( 'DeepSeek' );
		$this->set_key_config( 'automator_deepseek_api_key' );
	}

	/**
	 * Create payload builder with DeepSeek authentication.
	 *
	 * Uses the parent's OpenAI-compatible builder but adds DeepSeek-specific
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
	 * Send request to DeepSeek API.
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
	 * Parse DeepSeek response into standardized format.
	 *
	 * DeepSeek uses OpenAI-compatible response format, so we can
	 * delegate to the parent's parsing logic. If DeepSeek had
	 * unique response fields, we could override extract_usage_metadata().
	 *
	 * @param array<string,mixed> $response Raw DeepSeek response
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
	 * Extract usage metadata from OpenAI-compatible response.
	 *
	 * Parses the standard usage object for token consumption information.
	 * Child classes can override to include provider-specific metadata.
	 *
	 * @param array<string,mixed> $response Raw provider response
	 *
	 * @return array<string,mixed> Standardized usage metadata
	 */
	protected function extract_usage_metadata( array $response ): array {

		$metadata = $response['usage'];

		return array(
			'prompt_tokens'                              => $metadata['prompt_tokens'] ?? '',
			'completion_tokens'                          => $metadata['completion_tokens'] ?? '',
			'total_tokens'                               => $metadata['total_tokens'] ?? '',
			'prompt_tokens_details_cached_tokens'        => $metadata['prompt_tokens_details']['cached_tokens'] ?? '',
			'completion_tokens_details_reasoning_tokens' => $metadata['completion_tokens_details']['reasoning_tokens'] ?? '',
			'prompt_cache_hit_tokens'                    => $metadata['prompt_cache_hit_tokens'] ?? '',
			'prompt_cache_miss_tokens'                   => $metadata['prompt_cache_miss_tokens'] ?? '',
		);
	}
}
