<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Core\Interfaces;

use Uncanny_Automator\Core\Lib\AI\Http\Payload;
use Uncanny_Automator\Core\Lib\AI\Http\Request;
use Uncanny_Automator\Core\Lib\AI\Http\Response;

/**
 * Core interface for AI provider implementations in the Uncanny Automator AI framework.
 *
 * This interface defines the contract that all AI service providers must implement to integrate
 * with the framework. It follows the Strategy pattern, allowing different AI providers to be
 * used interchangeably while maintaining consistent behavior.
 *
 * WIRING AND REGISTRATION:
 * - Concrete implementations are registered via Provider_Factory::register_provider()
 * - Factory creates instances and injects dependencies (HTTP client, logger, config)
 * - Providers are retrieved by integration code using Provider_Factory::create()
 *
 * ARCHITECTURE FLOW:
 * 1. Action classes use Base_AI_Provider_Trait::get_provider() to obtain provider instance
 * 2. Provider creates payload builder via create_builder()
 * 3. Action builds request using fluent API
 * 4. Provider sends request via send_request()
 * 5. Provider parses response via parse_response()
 *
 * CONCRETE IMPLEMENTATIONS:
 * - OpenAI_Provider (OpenAI GPT models)
 * - Claude_Provider (Anthropic Claude models)
 * - Deepseek_Provider (DeepSeek models)
 * - Perplexity_Provider (Perplexity AI models)
 * - Grok_Provider (xAI Grok models)
 * - Gemini_Provider (Google Gemini models)
 * - Cohere_Provider (Cohere Command models)
 *
 * DEPENDENCY INJECTION:
 * All providers receive these dependencies via constructor and setters:
 * - Http_Client_Interface: For making HTTP requests
 * - Logger_Interface: For logging operations
 * - Config_Interface: For accessing configuration values
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Interfaces
 * @since 5.6
 *
 * @see Provider_Factory For provider registration and instantiation
 * @see Base_AI_Provider_Trait For common provider functionality in actions
 * @see Base_Provider_Trait For dependency injection in concrete providers
 */
interface AI_Provider_Interface {

	/**
	 * Create a fluent payload builder for constructing API requests.
	 *
	 * This method returns a pre-configured Payload builder with provider-specific
	 * authentication and headers already set. The builder uses the fluent API pattern
	 * to allow chaining of configuration methods.
	 *
	 * IMPLEMENTATION NOTES:
	 * - Must set authentication headers (API keys, tokens)
	 * - Should set Content-Type and other required headers
	 * - May set provider-specific default values
	 *
	 * USAGE PATTERN:
	 * ```php
	 * $builder = $provider->create_builder()
	 *     ->endpoint('https://api.example.com/chat')
	 *     ->model('gpt-4')
	 *     ->temperature(0.7)
	 *     ->messages($messages);
	 * ```
	 *
	 * @return Payload Pre-configured payload builder with authentication
	 *
	 * @throws \RuntimeException If provider dependencies not initialized
	 * @throws \InvalidArgumentException If configuration is missing or invalid
	 */
	public function create_builder(): Payload;

	/**
	 * Send the constructed API request to the provider's endpoint.
	 *
	 * Takes a built Request object and executes the HTTP call to the AI provider.
	 * Handles provider-specific request formatting, error handling, and response
	 * validation. Logs request details for debugging and monitoring.
	 *
	 * IMPLEMENTATION RESPONSIBILITIES:
	 * - Extract URL, headers, and body from Request object
	 * - Execute HTTP POST using injected Http_Client_Interface
	 * - Log request details with correlation ID for tracing
	 * - Handle provider-specific error responses
	 * - Return raw response array for parsing
	 *
	 * ERROR HANDLING:
	 * - Network failures throw AI_Service_Exception
	 * - Invalid responses throw AI_Service_Exception
	 * - Provider-specific errors are handled in parse_response()
	 *
	 * @param Request $payload Complete request object with endpoint, headers, and body
	 *
	 * @return array<string,mixed> Raw decoded JSON response from provider
	 *
	 * @throws \Uncanny_Automator\Core\Lib\AI\Exception\AI_Service_Exception On HTTP or network errors
	 * @throws \RuntimeException If provider dependencies not initialized
	 */
	public function send_request( Request $payload ): array;

	/**
	 * Parse the raw API response into a standardized Response object.
	 *
	 * Converts the provider-specific response format into a consistent Response
	 * value object. Extracts the generated content, metadata (token usage, model info),
	 * and handles provider-specific error conditions.
	 *
	 * STANDARDIZATION:
	 * - All providers return the same Response interface
	 * - Content is extracted as plain text string
	 * - Metadata includes token usage, model name, finish reason
	 * - Raw response is preserved for debugging
	 *
	 * ERROR HANDLING:
	 * - Provider API errors throw AI_Service_Exception
	 * - Empty/invalid responses throw Validation_Exception
	 * - Malformed data throws Response_Exception
	 *
	 * METADATA EXTRACTION:
	 * Common metadata fields across providers:
	 * - prompt_tokens: Input token count
	 * - completion_tokens: Output token count
	 * - total_tokens: Combined token count
	 * - model: Model name used
	 * - finish_reason: Why generation stopped
	 *
	 * @param array<string,mixed> $response Raw decoded JSON response from provider
	 *
	 * @return Response Standardized response object with content and metadata
	 *
	 * @throws \Uncanny_Automator\Core\Lib\AI\Exception\AI_Service_Exception On provider API errors
	 * @throws \Uncanny_Automator\Core\Lib\AI\Exception\Validation_Exception On empty/invalid content
	 * @throws \Uncanny_Automator\Core\Lib\AI\Exception\Response_Exception On malformed response data
	 */
	public function parse_response( array $response ): Response;
}
