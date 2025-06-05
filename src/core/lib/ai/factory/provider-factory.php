<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Factory;

use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\AI_Provider_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Http_Client_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Logger_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Config_Interface;

/**
 * Central factory and registry for AI provider implementations.
 *
 * This class serves as the main entry point for the AI framework's provider system.
 * It implements the Factory and Registry patterns to manage AI provider creation
 * and dependency injection in a clean, testable way.
 *
 * REGISTRATION FLOW:
 * 1. Providers are registered during framework initialization in load.php
 * 2. Each provider class is mapped to an integration code (e.g., 'OPENAI' => OpenAI_Provider::class)
 * 3. Registration validates that the class exists and implements AI_Provider_Interface
 *
 * CREATION FLOW:
 * 1. Action classes request providers via Base_AI_Provider_Trait::get_provider()
 * 2. Factory looks up the provider class by integration code
 * 3. Factory instantiates the provider with HTTP client dependency
 * 4. Factory injects config and logger dependencies via setters
 * 5. Fully configured provider is returned to caller
 *
 * DEPENDENCY INJECTION:
 * All providers receive these dependencies:
 * - Http_Client_Interface: WordPress HTTP adapter for making API requests
 * - Logger_Interface: WordPress logging adapter for debugging/monitoring
 * - Config_Interface: WordPress options adapter for API keys/settings
 *
 * WIRING LOCATIONS:
 * - Registration: uncanny-automator/src/integrations/ai/load.php
 * - Usage: All AI action classes via Base_AI_Provider_Trait
 * - HTTP Client: Integration_Http_Client (WordPress wp_remote_post wrapper)
 * - Logger: Logger (WordPress automator_log wrapper)
 * - Config: Integration_Config (WordPress automator_get_option wrapper)
 *
 * SUPPORTED PROVIDERS:
 * - OPENAI: OpenAI GPT models (GPT-4, GPT-3.5)
 * - CLAUDE: Anthropic Claude models (Opus, Sonnet, Haiku)
 * - DEEPSEEK: DeepSeek reasoning models
 * - PERPLEXITY: Perplexity search-augmented models
 * - GROK: xAI Grok models with X/Twitter data access
 * - GEMINI: Google Gemini models with multimodal capabilities
 * - COHERE: Cohere Command models for enterprise use
 * - MISTRAL: Mistral open-source models
 *
 * @package Uncanny_Automator\Core\Lib\AI\Factory
 * @since 5.6
 *
 * @see AI_Provider_Interface For the contract all providers must implement
 * @see Base_AI_Provider_Trait For how actions obtain provider instances
 * @see Integration_Http_Client For WordPress HTTP implementation
 * @see Integration_Config For WordPress configuration implementation
 * @see Logger For WordPress logging implementation
 */
class Provider_Factory {

	/**
	 * Provider registry mapping integration codes to class names.
	 *
	 * Static registry that holds the mapping between integration codes (like 'OPENAI')
	 * and their corresponding provider class names. This allows dynamic provider
	 * creation based on action configuration.
	 *
	 * STRUCTURE:
	 * ```php
	 * [
	 *     'OPENAI' => 'Uncanny_Automator\Core\Lib\AI\Provider\OpenAI_Provider',
	 *     'CLAUDE' => 'Uncanny_Automator\Core\Lib\AI\Provider\Claude_Provider',
	 *     // ... other providers
	 * ]
	 * ```
	 *
	 * @var array<string,string> Map of uppercase integration codes to FQCN strings
	 */
	private static $registry = array();

	/**
	 * Register a provider class under a given integration code.
	 *
	 * Associates an integration code with a provider class for later instantiation.
	 * Validates that the class exists to catch configuration errors early.
	 * Integration codes are normalized to uppercase for consistency.
	 *
	 * CALLED FROM:
	 * - uncanny-automator/src/core/lib/ai/load.php during framework initialization
	 * - Each provider registration looks like:
	 *   Provider_Factory::register_provider('OPENAI', OpenAI_Provider::class);
	 *
	 * VALIDATION:
	 * - Checks that the class exists using class_exists()
	 * - Does not validate interface implementation (done at creation time)
	 * - Throws InvalidArgumentException for missing classes
	 *
	 * @param string $code       Integration code (case-insensitive, normalized to uppercase)
	 * @param string $class_name Fully qualified class name implementing AI_Provider_Interface
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the specified class does not exist
	 *
	 * @phpstan-param class-string<AI_Provider_Interface> $class_name
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public static function register_provider( string $code, string $class_name ): void {
		if ( ! class_exists( $class_name ) ) {
			throw new \InvalidArgumentException( esc_html( "Class {$class_name} does not exist" ) );
		}
		self::$registry[ strtoupper( $code ) ] = $class_name;
	}

	/**
	 * Create a fully configured provider instance with dependency injection.
	 *
	 * This is the main factory method that creates provider instances with all
	 * required dependencies injected. It handles the complete object creation
	 * and configuration flow.
	 *
	 * CREATION PROCESS:
	 * 1. Normalize integration code to uppercase
	 * 2. Look up provider class in registry
	 * 3. Instantiate provider with HTTP client (constructor injection)
	 * 4. Inject config dependency via setter (Base_Provider_Trait)
	 * 5. Inject logger dependency via setter (Base_Provider_Trait)
	 * 6. Return fully configured provider
	 *
	 * DEPENDENCY INJECTION PATTERN:
	 * - HTTP client is injected via constructor (required for core functionality)
	 * - Config and logger are injected via setters (cross-cutting concerns)
	 * - This pattern allows for easy testing and mocking
	 *
	 * USAGE PATTERN:
	 * ```php
	 * $provider = Provider_Factory::create(
	 *     'OPENAI',
	 *     $http_client,
	 *     $logger,
	 *     $config
	 * );
	 * $response = $provider->create_builder()
	 *     ->endpoint('https://api.openai.com/v1/chat/completions')
	 *     ->model('gpt-4')
	 *     ->build();
	 * ```
	 *
	 * ERROR HANDLING:
	 * - Throws InvalidArgumentException if provider not registered
	 * - Lets constructor exceptions bubble up (e.g., missing dependencies)
	 * - Lets setter exceptions bubble up (e.g., invalid config)
	 *
	 * @param string                $integration Integration code (case-insensitive)
	 * @param Http_Client_Interface $http        HTTP client for API requests (WordPress adapter)
	 * @param Logger_Interface      $logger      Logger for debugging/monitoring (WordPress adapter)
	 * @param Config_Interface      $config      Configuration source for API keys (WordPress adapter)
	 *
	 * @return AI_Provider_Interface Fully configured provider ready for use
	 *
	 * @throws \InvalidArgumentException If no provider is registered for the integration code
	 * @throws \RuntimeException If provider instantiation fails
	 * @throws \TypeError If provider doesn't implement AI_Provider_Interface
	 *
	 * @phpstan-return AI_Provider_Interface
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment
	public static function create(
		string $integration,
		Http_Client_Interface $http,
		Logger_Interface $logger,
		Config_Interface $config
	): AI_Provider_Interface {

		$code = strtoupper( $integration );

		if ( ! isset( self::$registry[ $code ] ) ) {
			throw new \InvalidArgumentException( esc_html( "No provider registered for {$code}" ) );
		}

		$class    = self::$registry[ $code ];
		$provider = new $class( $http );

		// Inject cross-cutting dependencies.
		$provider->set_config( $config );
		$provider->set_logger( $logger );

		return $provider;
	}
}
