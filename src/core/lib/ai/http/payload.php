<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Http;

use Uncanny_Automator\Core\Lib\AI\Http\Component\Endpoint;
use Uncanny_Automator\Core\Lib\AI\Http\Component\Headers;
use Uncanny_Automator\Core\Lib\AI\Http\Component\Body;
use Uncanny_Automator\Core\Lib\AI\Http\Request;

/**
 * Fluent builder for constructing AI HTTP request payloads.
 *
 * This class implements the Builder pattern with a fluent interface to make
 * constructing AI API requests clean and readable. It ensures type safety
 * and provides convenience methods for common AI request parameters.
 *
 * DESIGN PATTERN:
 * Uses the Builder pattern with fluent interface for request construction:
 * - Each method returns $this for method chaining
 * - Immutable value objects for headers and body components
 * - Final build() creates immutable Request object
 * - Type-safe construction with validation
 *
 * FLUENT API USAGE:
 * ```php
 * $payload = $provider->create_builder()
 *     ->endpoint('https://api.openai.com/v1/chat/completions')
 *     ->authorization('sk-...', 'Bearer')
 *     ->json_content()
 *     ->model('gpt-4')
 *     ->temperature(0.7)
 *     ->max_tokens(2048)
 *     ->messages($messages)
 *     ->build();
 * ```
 *
 * PROVIDER INTEGRATION:
 * Each AI provider's create_builder() method returns a pre-configured
 * Payload instance with authentication and provider-specific headers:
 * - OpenAI: Bearer token authorization
 * - Claude: x-api-key header with version
 * - Gemini: API key in URL parameters
 * - Cohere: Bearer token authorization
 * - etc.
 *
 * VALUE OBJECT COMPOSITION:
 * The builder manages three immutable value objects:
 * - Endpoint: Validates and stores API endpoint URL
 * - Headers: Manages HTTP headers with type safety
 * - Body: Manages request body with AI-specific convenience methods
 *
 * AI-SPECIFIC CONVENIENCES:
 * Provides convenience methods for common AI parameters:
 * - model(): Set the AI model to use
 * - temperature(): Control randomness (0.0-1.0)
 * - max_tokens(): Limit response length
 * - messages(): Set conversation messages
 * - json_content(): Set JSON content type header
 *
 * IMMUTABILITY:
 * - All value objects are immutable
 * - Each fluent method creates new instances
 * - Builder state changes don't affect previous instances
 * - Final Request object is immutable
 *
 * @package Uncanny_Automator\Core\Lib\AI\Http
 * @since 5.6
 *
 * @see AI_Provider_Interface::create_builder() For provider-specific builders
 * @see Request For the immutable request object this builds
 * @see Endpoint For URL validation and storage
 * @see Headers For HTTP header management
 * @see Body For request body management
 */
class Payload {

	/**
	 * API endpoint URL string.
	 *
	 * Stores the target URL for the AI API request. Must be set before
	 * calling build() to create a valid Request object.
	 *
	 * VALIDATION:
	 * - URL validation performed in build() via Endpoint constructor
	 * - Must be valid HTTP/HTTPS URL
	 * - Required for successful request construction
	 *
	 * @var string|null The API endpoint URL (null until set)
	 */
	private $endpoint_url;

	/**
	 * HTTP headers value object.
	 *
	 * Immutable Headers instance that manages all HTTP headers for the request.
	 * Headers are modified by returning new instances (immutable pattern).
	 *
	 * COMMON HEADERS:
	 * - Authorization: Bearer tokens, API keys
	 * - Content-Type: Usually application/json for AI APIs
	 * - User-Agent: Framework identification
	 * - Provider-specific headers (anthropic-version, etc.)
	 *
	 * @var Headers Immutable headers value object
	 */
	private $headers;

	/**
	 * Request body value object.
	 *
	 * Immutable Body instance that manages the request payload data.
	 * Provides AI-specific convenience methods for common parameters.
	 *
	 * COMMON BODY FIELDS:
	 * - model: AI model identifier
	 * - messages: Conversation messages array
	 * - temperature: Randomness control (0.0-1.0)
	 * - max_tokens: Response length limit
	 * - Provider-specific parameters
	 *
	 * @var Body Immutable body value object
	 */
	private $body;

	/**
	 * Initialize empty payload builder.
	 *
	 * Creates a new builder with empty headers and body value objects.
	 * Endpoint URL starts as null and must be set before building.
	 */
	public function __construct() {
		$this->headers = new Headers();
		$this->body    = new Body();
	}

	/**
	 * Set the API endpoint URL.
	 *
	 * Stores the target URL for the API request. URL validation is deferred
	 * to the build() method when the Endpoint value object is created.
	 *
	 * USAGE:
	 * ```php
	 * $builder->endpoint('https://api.openai.com/v1/chat/completions')
	 * ```
	 *
	 * @param string $url The API endpoint URL (must be valid HTTP/HTTPS)
	 *
	 * @return self Returns this instance for method chaining
	 */
	public function endpoint( string $url ): self {
		$this->endpoint_url = $url;
		return $this;
	}

	/**
	 * Add authorization header with token and type.
	 *
	 * Convenience method for setting authorization headers. Supports different
	 * authorization schemes used by various AI providers.
	 *
	 * COMMON USAGE:
	 * ```php
	 * // Bearer token (OpenAI, Cohere, etc.)
	 * $builder->authorization('sk-...', 'Bearer')
	 *
	 * // API key (Claude)
	 * $builder->authorization('sk-...', 'Bearer', 'x-api-key')
	 * ```
	 *
	 * HEADER FORMAT:
	 * Creates header in format: "{type} {token}"
	 * Example: "Bearer sk-1234567890abcdef"
	 *
	 * @param string $token    The authentication token/key
	 * @param string $type     The authorization type (default: 'Bearer')
	 * @param string $auth_key The header name (default: 'Authorization')
	 *
	 * @return self Returns this instance for method chaining
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public function authorization( string $token, string $type = 'Bearer', string $auth_key = 'Authorization' ): self {
		$this->headers = $this->headers->authorization( $token, $type, $auth_key );
		return $this;
	}

	/**
	 * Add arbitrary HTTP header.
	 *
	 * Generic method for setting any HTTP header. Used for provider-specific
	 * headers that don't have dedicated convenience methods.
	 *
	 * USAGE:
	 * ```php
	 * $builder->headers('anthropic-version', '2023-06-01')
	 *         ->headers('User-Agent', 'Uncanny-Automator/5.6')
	 * ```
	 *
	 * @param string $key   The header name
	 * @param string $value The header value
	 *
	 * @return self Returns this instance for method chaining
	 */
	public function headers( string $key, string $value ): self {
		$this->headers = $this->headers->with( $key, $value );
		return $this;
	}

	/**
	 * Add field to request body.
	 *
	 * Generic method for setting any request body field. Used for
	 * provider-specific parameters that don't have convenience methods.
	 *
	 * USAGE:
	 * ```php
	 * $builder->body('stream', true)
	 *         ->body('safety_mode', 'STRICT')
	 * ```
	 *
	 * @param mixed $key   The body field name
	 * @param mixed $value The body field value
	 *
	 * @return self Returns this instance for method chaining
	 */
	public function body( $key, $value ): self {
		$this->body = $this->body->with( $key, $value );
		return $this;
	}

	/**
	 * Set Content-Type header to application/json.
	 *
	 * Convenience method for setting the JSON content type header.
	 * Most AI APIs expect JSON payloads, making this a common operation.
	 *
	 * USAGE:
	 * ```php
	 * $builder->json_content()
	 * ```
	 *
	 * EQUIVALENT TO:
	 * ```php
	 * $builder->headers('Content-Type', 'application/json')
	 * ```
	 *
	 * @return self Returns this instance for method chaining
	 */
	public function json_content(): self {
		$this->headers = $this->headers->content_type( 'application/json' );
		return $this;
	}

	/**
	 * Set the AI model to use.
	 *
	 * Convenience method for setting the model field in the request body.
	 * This is a required parameter for most AI API endpoints.
	 *
	 * USAGE:
	 * ```php
	 * $builder->model('gpt-4')
	 *         ->model('claude-3-sonnet-20240229')
	 *         ->model('gemini-pro')
	 * ```
	 *
	 * @param string $model The AI model identifier
	 *
	 * @return self Returns this instance for method chaining
	 */
	public function model( string $model ): self {
		$this->body = $this->body->with_model( $model );
		return $this;
	}

	/**
	 * Set the sampling temperature for response generation.
	 *
	 * Convenience method for setting the temperature parameter that controls
	 * randomness in AI responses. Range is typically 0.0 (deterministic)
	 * to 1.0 (highly random).
	 *
	 * USAGE:
	 * ```php
	 * $builder->temperature(0.0)  // Deterministic
	 *         ->temperature(0.7)  // Balanced
	 *         ->temperature(1.0)  // Creative
	 * ```
	 *
	 * BEHAVIOR:
	 * - 0.0: Deterministic, focused responses
	 * - 0.3-0.7: Balanced creativity and focus
	 * - 1.0: Maximum creativity and randomness
	 *
	 * @param float $temp The temperature value (typically 0.0-1.0)
	 *
	 * @return self Returns this instance for method chaining
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public function temperature( float $temp ): self {
		$this->body = $this->body->with_temperature( $temp );
		return $this;
	}

	/**
	 * Set maximum number of tokens to generate (legacy).
	 *
	 * Convenience method for setting the max_tokens parameter that limits
	 * response length. Note: Some providers are moving to max_completion_tokens.
	 *
	 * USAGE:
	 * ```php
	 * $builder->max_tokens(100)   // Short response
	 *         ->max_tokens(2048)  // Standard response
	 *         ->max_tokens(4096)  // Long response
	 * ```
	 *
	 * PROVIDER DIFFERENCES:
	 * - OpenAI: Uses max_tokens (legacy) or max_completion_tokens (new)
	 * - Claude: Uses max_tokens
	 * - Others: Varies by provider
	 *
	 * @param int $max The maximum number of tokens to generate
	 *
	 * @return self Returns this instance for method chaining
	 */
	public function max_tokens( int $max ): self {
		$this->body = $this->body->with_max_tokens( $max );
		return $this;
	}

	/**
	 * Set maximum completion tokens (modern parameter).
	 *
	 * Convenience method for setting the max_completion_tokens parameter,
	 * which is the newer standard for limiting response length.
	 *
	 * USAGE:
	 * ```php
	 * $builder->max_completion_tokens(2048)
	 * ```
	 *
	 * DIFFERENCE FROM max_tokens:
	 * - max_completion_tokens: Only counts output tokens
	 * - max_tokens: May include prompt tokens in some providers
	 *
	 * @param int $max The maximum number of completion tokens
	 *
	 * @return self Returns this instance for method chaining
	 */
	public function max_completion_tokens( int $max ): self {
		$this->body = $this->body->with_max_completion_tokens( $max );
		return $this;
	}

	/**
	 * Set conversation messages array.
	 *
	 * Convenience method for setting the messages parameter that contains
	 * the conversation history for chat-based AI models.
	 *
	 * USAGE:
	 * ```php
	 * $messages = [
	 *     ['role' => 'system', 'content' => 'You are a helpful assistant.'],
	 *     ['role' => 'user', 'content' => 'Hello!']
	 * ];
	 * $builder->messages($messages)
	 * ```
	 *
	 * MESSAGE FORMAT:
	 * Standard format across most providers:
	 * - role: 'system', 'user', 'assistant'
	 * - content: The message text
	 *
	 * @param array $msgs Array of message objects with role and content
	 *
	 * @return self Returns this instance for method chaining
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public function messages( array $msgs ): self {
		$this->body = $this->body->with_messages( $msgs );
		return $this;
	}

	/**
	 * Get current builder state as array (for debugging).
	 *
	 * Returns the current state of the builder as an associative array.
	 * Useful for debugging and testing builder state before calling build().
	 *
	 * RETURN FORMAT:
	 * ```php
	 * [
	 *     'endpoint' => 'https://api.openai.com/v1/chat/completions',
	 *     'headers' => ['Authorization' => 'Bearer sk-...'],
	 *     'body' => ['model' => 'gpt-4', 'messages' => [...]]
	 * ]
	 * ```
	 *
	 * @return array<string,mixed> Current builder state
	 */
	public function to_array(): array {
		return array(
			'endpoint' => $this->endpoint_url,
			'headers'  => $this->headers->to_array(),
			'body'     => $this->body->to_array(),
		);
	}

	/**
	 * Build immutable Request object from current state.
	 *
	 * Creates the final immutable Request object from the builder's current state.
	 * Validates that required fields are set and creates value objects with
	 * validation.
	 *
	 * VALIDATION:
	 * - Endpoint URL must be set and valid
	 * - Headers and body are validated by their constructors
	 * - Throws InvalidArgumentException for invalid state
	 *
	 * IMMUTABILITY:
	 * The returned Request object is immutable and contains immutable
	 * value objects for all components.
	 *
	 * USAGE:
	 * ```php
	 * $request = $builder
	 *     ->endpoint('https://api.openai.com/v1/chat/completions')
	 *     ->authorization('sk-...')
	 *     ->model('gpt-4')
	 *     ->build();
	 * ```
	 *
	 * @return Request Immutable request object ready for sending
	 *
	 * @throws \InvalidArgumentException If endpoint URL not set or invalid
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public function build(): Request {
		if ( null === $this->endpoint_url ) {
			throw new \InvalidArgumentException( 'Endpoint URL is required' );
		}

		return new Request(
			new Endpoint( $this->endpoint_url ),
			$this->headers,
			$this->body
		);
	}
}
