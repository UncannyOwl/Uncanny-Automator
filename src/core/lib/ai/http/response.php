<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Http;

/**
 * Standardized AI response value object.
 *
 * This immutable value object provides a consistent interface for AI provider
 * responses across all different AI services. It normalizes the varying response
 * formats from different providers into a single, predictable structure.
 *
 * STANDARDIZATION PURPOSE:
 * Different AI providers return responses in different formats:
 * - OpenAI: choices[0].message.content
 * - Claude: content[0].text
 * - Gemini: candidates[0].content.parts[0].text
 * - Cohere: text
 * - etc.
 *
 * This class abstracts these differences into a unified interface.
 *
 * IMMUTABILITY:
 * This is an immutable value object - once created, it cannot be modified.
 * This ensures thread safety and prevents accidental mutations that could
 * cause bugs in action processing.
 *
 * USAGE IN PROVIDERS:
 * Each provider's parse_response() method creates a Response instance:
 * ```php
 * // In OpenAI_Provider::parse_response()
 * $content = $response['choices'][0]['message']['content'];
 * $metadata = [
 *     'prompt_tokens' => $response['usage']['prompt_tokens'],
 *     'completion_tokens' => $response['usage']['completion_tokens'],
 *     'model' => $response['model']
 * ];
 * return new Response($content, $metadata, $response);
 * ```
 *
 * USAGE IN ACTIONS:
 * Action classes receive standardized Response objects:
 * ```php
 * $ai_response = $provider->parse_response($raw_response);
 * $content = $ai_response->get_content();
 * $token_usage = $ai_response->get_meta_data()['total_tokens'];
 * ```
 *
 * METADATA STANDARDIZATION:
 * Common metadata fields across providers:
 * - prompt_tokens: Number of input tokens consumed
 * - completion_tokens: Number of output tokens generated
 * - total_tokens: Combined token count
 * - model: The specific model used for generation
 * - finish_reason: Why generation stopped (length, stop, etc.)
 *
 * RAW DATA PRESERVATION:
 * The original provider response is preserved for debugging and
 * provider-specific data extraction when needed.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Http
 * @since 5.6
 *
 * @see AI_Provider_Interface::parse_response() For how this is created
 * @see OpenAI_Provider For OpenAI response parsing example
 * @see Claude_Provider For Claude response parsing example
 * @see Gemini_Provider For Gemini response parsing example
 */
final class Response {

	/**
	 * The generated AI content as plain text.
	 *
	 * This is the main output from the AI model - the generated text content
	 * that was requested. All providers normalize their response format
	 * to provide this as a simple string.
	 *
	 * CONTENT EXTRACTION:
	 * Different providers store content in different places:
	 * - OpenAI: response.choices[0].message.content
	 * - Claude: response.content[0].text
	 * - Gemini: response.candidates[0].content.parts[0].text
	 * - Cohere: response.text
	 * - Perplexity: response.choices[0].message.content
	 *
	 * All are normalized to this single string field.
	 *
	 * @var string The generated AI text content
	 */
	private $content;

	/**
	 * Standardized metadata about the response.
	 *
	 * Contains normalized metadata that is common across AI providers.
	 * This allows actions to access token usage, model info, etc. in a
	 * consistent way regardless of which provider was used.
	 *
	 * COMMON METADATA FIELDS:
	 * - prompt_tokens (int): Number of input tokens
	 * - completion_tokens (int): Number of output tokens
	 * - total_tokens (int): Combined token count
	 * - model (string): Model identifier that was used
	 * - finish_reason (string): Why generation stopped
	 *
	 * PROVIDER-SPECIFIC FIELDS:
	 * Some providers include additional metadata:
	 * - Claude: cache_creation_tokens, cache_read_tokens
	 * - Gemini: safety_ratings, finish_reason
	 * - Perplexity: citations, search_results
	 *
	 * @var array<string,mixed> Normalized response metadata
	 */
	private $metadata;

	/**
	 * Raw response data for debugging and provider-specific access.
	 *
	 * Preserves the complete, unmodified response from the AI provider.
	 * This is useful for debugging, logging, and accessing provider-specific
	 * data that isn't part of the standardized interface.
	 *
	 * DEBUG USAGE:
	 * - Inspect full provider response structure
	 * - Debug parsing issues
	 * - Log complete responses for analysis
	 *
	 * PROVIDER-SPECIFIC ACCESS:
	 * - Extract non-standard fields if needed
	 * - Access experimental provider features
	 * - Handle provider-specific error details
	 *
	 * PRIVACY CONSIDERATION:
	 * Contains the complete response including all content and metadata.
	 * Should be handled carefully in logs and debugging output.
	 *
	 * @var array<string,mixed> Complete raw provider response
	 */
	private $raw;

	/**
	 * Create immutable response object.
	 *
	 * Constructs a new Response instance with the standardized content,
	 * metadata, and raw response data. All parameters are stored immutably.
	 *
	 * PARAMETER GUIDELINES:
	 * - content: Should be plain text, not HTML or markup
	 * - metadata: Should use standardized field names when possible
	 * - raw: Should be the complete, unmodified provider response
	 *
	 * CALLED BY:
	 * Provider parse_response() methods after extracting and normalizing
	 * the response from their specific API format.
	 *
	 * @param string              $content  The generated AI text content
	 * @param array<string,mixed> $metadata Standardized response metadata
	 * @param array<string,mixed> $raw      Complete raw provider response
	 */
	public function __construct( string $content, array $metadata = array(), array $raw = array() ) {
		$this->content  = $content;
		$this->metadata = $metadata;
		$this->raw      = $raw;
	}

	/**
	 * Get the generated AI content.
	 *
	 * Returns the main text content generated by the AI model. This is
	 * the primary output that actions will use for further processing
	 * or display to users.
	 *
	 * CONTENT CHARACTERISTICS:
	 * - Plain text format (no HTML/markup unless specifically requested)
	 * - May include line breaks and formatting characters
	 * - Length limited by max_tokens parameter from request
	 * - May be truncated if hit token limits
	 *
	 * USAGE IN ACTIONS:
	 * ```php
	 * $ai_response = $provider->parse_response($response);
	 * $generated_text = $ai_response->get_content();
	 *
	 * // Use in token hydration
	 * $this->hydrate_tokens(['RESPONSE' => $generated_text]);
	 * ```
	 *
	 * @return string The generated AI text content
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public function get_content(): string {
		return esc_html( $this->content );
	}

	/**
	 * Get standardized response metadata.
	 *
	 * Returns the normalized metadata array containing information about
	 * token usage, model details, and other standardized fields across
	 * all AI providers.
	 *
	 * COMMON METADATA ACCESS:
	 * ```php
	 * $metadata = $ai_response->get_meta_data();
	 * $prompt_tokens = $metadata['prompt_tokens'] ?? 0;
	 * $completion_tokens = $metadata['completion_tokens'] ?? 0;
	 * $model_used = $metadata['model'] ?? 'unknown';
	 * ```
	 *
	 * SAFE ACCESS PATTERN:
	 * Always use null coalescing operator (??) when accessing metadata
	 * fields since different providers may not support all fields.
	 *
	 * TOKEN USAGE TRACKING:
	 * Most actions use this for token reporting and usage analytics:
	 * ```php
	 * $this->hydrate_tokens([
	 *     'USAGE_PROMPT_TOKENS' => $metadata['prompt_tokens'] ?? 0,
	 *     'USAGE_COMPLETION_TOKENS' => $metadata['completion_tokens'] ?? 0,
	 *     'USAGE_TOTAL_TOKENS' => $metadata['total_tokens'] ?? 0,
	 * ]);
	 * ```
	 *
	 * @return array<string,mixed> Standardized response metadata
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public function get_meta_data(): array {
		return $this->metadata;
	}

	/**
	 * Get the complete raw provider response.
	 *
	 * Returns the unmodified response data from the AI provider. This
	 * preserves all provider-specific fields and structure for debugging
	 * and advanced use cases.
	 *
	 * DEBUG USAGE:
	 * ```php
	 * $raw = $ai_response->get_raw();
	 * error_log('Full OpenAI response: ' . json_encode($raw));
	 * ```
	 *
	 * PROVIDER-SPECIFIC ACCESS:
	 * ```php
	 * // Access Perplexity search results
	 * $raw = $ai_response->get_raw();
	 * $search_results = $raw['search_results'] ?? [];
	 *
	 * // Access Claude safety ratings
	 * $safety_data = $raw['safety'] ?? [];
	 * ```
	 *
	 * LOGGING CONSIDERATIONS:
	 * The raw response may contain sensitive data (API keys in headers,
	 * full conversation history, etc.). Use with caution in logs.
	 *
	 * STRUCTURE VARIANCE:
	 * Each provider has a different response structure, so accessing
	 * raw data requires provider-specific knowledge.
	 *
	 * @return array<string,mixed> Complete unmodified provider response
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public function get_raw(): array {
		return $this->raw;
	}
}
