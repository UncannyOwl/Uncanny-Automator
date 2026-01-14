<?php

namespace Uncanny_Automator\Core\Lib\AI\Core\Traits;

use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\AI_Provider_Interface;
use Uncanny_Automator\Core\Lib\AI\Adapters\API\Credit_Adapter;
use Uncanny_Automator\Core\Lib\AI\Adapters\Config\Integration_Config;
use Uncanny_Automator\Core\Lib\AI\Adapters\Http\Integration_Http_Client;
use Uncanny_Automator\Core\Lib\AI\Adapters\Logger\Logger;

use Uncanny_Automator\Core\Lib\AI\Factory\Provider_Factory;
use Uncanny_Automator\Core\Lib\AI\Http\Payload;

/**
 * Base trait providing AI provider functionality to action classes.
 *
 * This trait encapsulates the common patterns for working with AI providers
 * in Uncanny Automator action classes. It handles dependency initialization,
 * provider creation, and payload building in a consistent way across all
 * AI integrations.
 *
 * USAGE PATTERN:
 * Action classes include this trait and call get_provider() to obtain
 * configured AI provider instances. The trait handles all the complex
 * dependency wiring behind the scenes.
 *
 * USED BY ACTION CLASSES:
 * - OpenAI_Chat_Generate (OpenAI GPT actions)
 * - Claude_Chat_Generate (Anthropic Claude actions)
 * - Perplexity_Chat_Generate (Perplexity search actions)
 * - Grok_Chat_Generate (xAI Grok actions)
 * - Gemini_Chat_Generate (Google Gemini actions)
 * - Cohere_Chat_Generate (Cohere Command actions)
 * - All future AI action implementations
 *
 * DEPENDENCY MANAGEMENT:
 * The trait creates and manages these WordPress adapter dependencies:
 * - Credit_Adapter: Tracks API usage via Uncanny Automator credit system
 * - Integration_Http_Client: WordPress HTTP wrapper for API requests
 * - Integration_Config: WordPress options wrapper for API keys/settings
 * - Logger: WordPress logging wrapper for debugging/monitoring
 *
 * INITIALIZATION FLOW:
 * 1. Action calls get_provider($provider_code)
 * 2. Trait calls init_dependencies() to create adapters if needed
 * 3. Trait uses Provider_Factory::create() to get provider instance
 * 4. Factory injects all dependencies into provider
 * 5. Configured provider returned to action
 *
 * CACHING BEHAVIOR:
 * Dependencies are lazy-loaded and cached in instance variables:
 * - First call creates instances and stores in properties
 * - Subsequent calls reuse existing instances
 * - Prevents unnecessary object creation during action execution
 *
 * WordPress INTEGRATION:
 * All dependencies are WordPress adapters that isolate the clean
 * AI framework from WordPress-specific implementation details:
 * - Credit system integrates with Uncanny Automator's API server
 * - HTTP client uses WordPress wp_remote_post() functions
 * - Config uses WordPress automator_get_option() functions
 * - Logger uses WordPress automator_log() functions
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Traits
 * @since 5.6
 *
 * @see AI_Provider_Interface For the provider contract
 * @see Provider_Factory For provider creation and registration
 * @see Credit_Adapter For WordPress credit system integration
 * @see Integration_Http_Client For WordPress HTTP integration
 * @see Integration_Config For WordPress options integration
 * @see Logger For WordPress logging integration
 */
trait Base_AI_Provider_Trait {

	/**
	 * WordPress credit tracking adapter instance.
	 *
	 * Handles credit deduction for API usage through Uncanny Automator's
	 * credit system. Credits are only consumed for successful API requests.
	 *
	 * INTEGRATION DETAILS:
	 * - Connects to Uncanny Automator's API server via Api_Server::api_call()
	 * - Reduces credits on successful AI provider responses
	 * - Prevents credit abuse from failed or malicious requests
	 * - Lazy-loaded on first provider request
	 *
	 * @var Credit_Adapter|null Cached credit adapter instance
	 */
	protected $credit_adapter;

	/**
	 * WordPress HTTP client adapter instance.
	 *
	 * Provides HTTP functionality using WordPress's wp_remote_post() API.
	 * Handles authentication, error conversion, and timeout management
	 * for all AI provider communications.
	 *
	 * FEATURES:
	 * - WordPress HTTP API integration (wp_remote_post)
	 * - 120-second default timeout for AI operations
	 * - WP_Error to AI_Service_Exception conversion
	 * - Automatic JSON encoding/decoding
	 * - Credit tracking integration
	 * - Lazy-loaded on first provider request
	 *
	 * @var Integration_Http_Client|null Cached HTTP client instance
	 */
	protected $http_client;

	/**
	 * WordPress configuration adapter instance.
	 *
	 * Provides access to WordPress options/settings through a clean interface.
	 * Used by providers to retrieve API keys, endpoints, and other configuration
	 * values stored in WordPress database.
	 *
	 * CONFIGURATION ACCESS:
	 * - API keys (automator_openai_api_key, etc.)
	 * - Custom endpoints and settings
	 * - Provider-specific configuration values
	 * - Uses WordPress automator_get_option() functions
	 * - Lazy-loaded on first provider request
	 *
	 * @var Integration_Config|null Cached configuration adapter instance
	 */
	protected $config;

	/**
	 * WordPress logging adapter instance.
	 *
	 * Provides logging functionality using WordPress/Automator logging system.
	 * Used by providers for debugging, monitoring, and error tracking.
	 *
	 * LOGGING CAPABILITIES:
	 * - Request/response logging with correlation IDs
	 * - Error and warning messages
	 * - Debug information for troubleshooting
	 * - Uses WordPress automator_log() functions
	 * - Configurable log levels and destinations
	 * - Lazy-loaded on first provider request
	 *
	 * @var Logger|null Cached logger adapter instance
	 */
	protected $logger;

	/**
	 * Initialize WordPress adapter dependencies if not already created.
	 *
	 * This method implements lazy initialization of all WordPress adapter
	 * dependencies. It only creates instances that don't already exist,
	 * allowing for efficient reuse across multiple provider requests.
	 *
	 * LAZY LOADING PATTERN:
	 * - Checks if each dependency is already set (!isset check)
	 * - Creates missing dependencies using WordPress adapters
	 * - Stores instances in protected properties for reuse
	 * - Avoids unnecessary object creation overhead
	 *
	 * DEPENDENCY CREATION:
	 * - Credit_Adapter: No constructor dependencies
	 * - Integration_Http_Client: Requires Credit_Adapter dependency
	 * - Integration_Config: No constructor dependencies
	 * - Logger: No constructor dependencies
	 *
	 * WordPress ISOLATION:
	 * All created adapters isolate WordPress functionality:
	 * - Framework code only sees clean interfaces
	 * - WordPress coupling confined to adapter classes
	 * - Easy to test with mock implementations
	 * - Clear separation of concerns
	 *
	 * @return void
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	protected function init_dependencies(): void {

		if ( ! isset( $this->credit_adapter ) ) {
			$this->credit_adapter = new Credit_Adapter();
		}

		if ( ! isset( $this->http_client ) ) {
			$this->http_client = new Integration_Http_Client( $this->credit_adapter );
		}

		if ( ! isset( $this->config ) ) {
			$this->config = new Integration_Config();
		}

		if ( ! isset( $this->logger ) ) {
			$this->logger = new Logger();
		}
	}

	/**
	 * Get a configured AI provider instance by integration code.
	 *
	 * This is the main entry point for action classes to obtain AI provider
	 * instances. It handles the complete flow of dependency initialization,
	 * provider creation, and dependency injection.
	 *
	 * PROVIDER CREATION FLOW:
	 * 1. Initialize WordPress adapter dependencies via init_dependencies()
	 * 2. Call Provider_Factory::create() with integration code and dependencies
	 * 3. Factory looks up provider class in registry
	 * 4. Factory instantiates provider with HTTP client (constructor injection)
	 * 5. Factory injects config and logger via setters (Base_Provider_Trait)
	 * 6. Return fully configured provider ready for use
	 *
	 * INTEGRATION CODES:
	 * Standard codes registered in load.php:
	 * - 'OPENAI': OpenAI GPT models
	 * - 'CLAUDE': Anthropic Claude models
	 * - 'PERPLEXITY': Perplexity search models
	 * - 'GROK': xAI Grok models
	 * - 'GEMINI': Google Gemini models
	 * - 'COHERE': Cohere Command models
	 * - 'MISTRAL': Mistral open-source models
	 *
	 * USAGE EXAMPLE:
	 * ```php
	 * // In an action class using this trait:
	 * $provider = $this->get_provider('OPENAI');
	 * $payload_builder = $this->get_payload_builder($provider);
	 *
	 * $payload = $payload_builder
	 *     ->endpoint('https://api.openai.com/v1/chat/completions')
	 *     ->model('gpt-4')
	 *     ->temperature(0.7)
	 *     ->messages($messages)
	 *     ->build();
	 *
	 * $response = $provider->send_request($payload);
	 * $ai_response = $provider->parse_response($response);
	 * ```
	 *
	 * ERROR HANDLING:
	 * - InvalidArgumentException if provider not registered
	 * - RuntimeException if dependency injection fails
	 * - Any provider-specific initialization errors
	 *
	 * @param string $provider_code Integration code for the desired AI provider
	 *
	 * @return AI_Provider_Interface Fully configured provider instance ready for use
	 *
	 * @throws \InvalidArgumentException If provider not registered for the code
	 * @throws \RuntimeException If provider creation or dependency injection fails
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	protected function get_provider( string $provider_code ): AI_Provider_Interface {

		$this->init_dependencies();

		return Provider_Factory::create(
			$provider_code,
			$this->http_client,
			$this->logger,
			$this->config
		);
	}

	/**
	 * Get payload builder from AI provider instance.
	 *
	 * Convenience method that extracts the payload builder from a provider.
	 * The builder is pre-configured with provider-specific authentication
	 * and headers, ready for request construction.
	 *
	 * BUILDER CONFIGURATION:
	 * Provider's create_builder() method returns a Payload instance with:
	 * - Authentication headers (API keys, tokens)
	 * - Content-Type headers
	 * - Provider-specific default values
	 * - Fluent API for request building
	 *
	 * FLUENT API USAGE:
	 * The returned builder supports method chaining:
	 * ```php
	 * $payload = $builder
	 *     ->endpoint($url)
	 *     ->model($model)
	 *     ->temperature($temp)
	 *     ->messages($messages)
	 *     ->build();
	 * ```
	 *
	 * PROVIDER DELEGATION:
	 * This method simply delegates to the provider's create_builder() method.
	 * It exists for convenience and consistency in action class usage patterns.
	 *
	 * @param AI_Provider_Interface $provider Configured AI provider instance
	 *
	 * @return Payload Pre-configured payload builder with authentication
	 *
	 * @throws \RuntimeException If provider dependencies not initialized
	 * @throws \InvalidArgumentException If provider configuration invalid
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	protected function get_payload_builder( AI_Provider_Interface $provider ): Payload {
		return $provider->create_builder();
	}
}
