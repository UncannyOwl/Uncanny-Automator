<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Core\Abstracts;

use Uncanny_Automator\Core\Lib\AI\Http\Payload;
use Uncanny_Automator\Core\Lib\AI\Http\Response;
use Uncanny_Automator\Core\Lib\AI\Exception\Validation_Exception;

/**
 * Pure abstract class for OpenAI-compatible AI providers.
 *
 * This class provides shared functionality for AI providers that use the
 * OpenAI API response format, without any trait mixing or concrete
 * implementations. It defines the structure and common logic for
 * providers that share the same response format.
 *
 * OPENAI-COMPATIBLE PROVIDERS:
 * - OpenAI: Original format, all models (GPT-3.5, GPT-4, etc.)
 * - Grok (xAI): Implements OpenAI format for easy integration
 * - Perplexity: Uses OpenAI format with additional search metadata
 *
 * SHARED RESPONSE FORMAT:
 * ```json
 * {
 *   "choices": [
 *     {
 *       "message": {
 *         "content": "Generated AI response text"
 *       }
 *     }
 *   ],
 *   "usage": {
 *     "prompt_tokens": 10,
 *     "completion_tokens": 25,
 *     "total_tokens": 35
 *   },
 *   "model": "gpt-4"
 * }
 * ```
 *
 * PURE ARCHITECTURE:
 * - No trait usage (traits handled by concrete classes)
 * - No interface implementation (handled by concrete classes)
 * - Only shared logic and structure for OpenAI-compatible APIs
 * - Abstract methods for customization points
 *
 * CUSTOMIZATION POINTS:
 * Child classes can override specific methods:
 * - extract_content(): Custom content extraction
 * - extract_usage_metadata(): Custom metadata parsing
 * - build_authentication_payload(): Custom authentication
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Abstracts
 * @since 5.6
 *
 * @see Base_AI_Provider_Abstract For base provider functionality
 */
abstract class OpenAI_Compatible_Provider_Abstract extends Base_AI_Provider_Abstract {

	/**
	 * Create payload builder with Bearer token authentication.
	 *
	 * Provides standard payload builder configuration for OpenAI-compatible
	 * providers. All use Bearer token authentication and JSON content type.
	 *
	 * @return Payload Pre-configured payload builder with authentication
	 *
	 * @throws \RuntimeException If dependencies not initialized
	 */
	protected function create_openai_builder(): Payload {

		$this->ensure_dependencies_initialized();

		$api_key = $this->config->get( $this->get_api_key_config() );

		return ( new Payload() )
			->authorization( $api_key )
			->json_content();
	}

	/**
	 * Parse OpenAI-compatible response into standardized format.
	 *
	 * Handles the common response structure used by OpenAI and compatible
	 * providers. Extracts content and metadata using overrideable methods
	 * to allow provider-specific customization.
	 *
	 * @param array<string,mixed> $response Raw provider response
	 *
	 * @return Response Standardized response object
	 *
	 * @throws Validation_Exception If response structure is invalid
	 */
	protected function parse_openai_compatible_response( array $response ): Response {

		// Extract content using overrideable method
		$content = $this->extract_content( $response );

		// Extract usage metadata using overrideable method
		$metadata = $this->extract_usage_metadata( $response );

		return new Response( $content, $metadata, $response );
	}

	/**
	 * Extract content from OpenAI-compatible response.
	 *
	 * Standard path: response.choices[0].message.content
	 * Child classes can override for provider-specific variations.
	 *
	 * @param array<string,mixed> $response Raw provider response
	 *
	 * @return string Extracted content text
	 *
	 * @throws Validation_Exception If content cannot be extracted
	 */
	protected function extract_content( array $response ): string {

		if ( ! isset( $response['choices'][0]['message']['content'] )
			|| '' === $response['choices'][0]['message']['content'] ) {
			throw new Validation_Exception(
				sprintf(
					/* translators: %s: AI provider name */
					esc_html_x(
						'The %s model predicted a completion that results in no output. Consider adjusting your prompt',
						'AI',
						'uncanny-automator'
					),
					esc_html( $this->get_provider_name() )
				)
			);
		}

		return $response['choices'][0]['message']['content'];
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

		return array(
			'prompt_tokens'     => $response['usage']['prompt_tokens'] ?? 0,
			'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
			'total_tokens'      => $response['usage']['total_tokens'] ?? 0,
			'model'             => $response['model'] ?? '',
		);
	}
}
