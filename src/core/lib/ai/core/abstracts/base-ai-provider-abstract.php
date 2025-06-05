<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Core\Abstracts;

use Uncanny_Automator\Core\Lib\AI\Adapters\Http\Integration_Http_Client;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Http_Client_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Logger_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Config_Interface;
use Uncanny_Automator\Core\Lib\AI\Http\Request;

/**
 * Pure base abstract class for all AI provider implementations.
 *
 * This abstract class provides the structural foundation for AI providers
 * without any trait mixing or concrete implementations. It defines the
 * essential dependencies and abstract methods that all providers must have,
 * but leaves composition to concrete classes.
 *
 * PURE ARCHITECTURE PRINCIPLES:
 * - Abstract classes define structure and contracts
 * - Concrete classes handle composition via traits and interfaces
 * - No trait usage in abstract classes (keep them pure)
 * - Clear separation between structure and implementation
 *
 * DEPENDENCY STRUCTURE:
 * All AI providers need these core dependencies:
 * - HTTP client for API communication
 * - Logger for debugging and monitoring
 * - Config for retrieving API keys and settings
 *
 * TEMPLATE METHOD PATTERN:
 * Provides the send_provider_request() template method that follows
 * a consistent flow while allowing provider-specific customization
 * through abstract methods.
 *
 * CONFIGURATION PATTERN:
 * Uses setter methods for provider-specific configuration:
 * - set_provider_name(): For logging and identification
 * - set_key_config(): For configuration key lookup
 * This reduces abstract method burden on concrete classes.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Abstracts
 * @since 5.6
 *
 * @see AI_Provider_Interface For the contract concrete classes must implement
 */
abstract class Base_AI_Provider_Abstract {

	/**
	 * HTTP client for making API requests.
	 *
	 * @var Integration_Http_Client
	 */
	protected $http;

	/**
	 * Logger for debugging and monitoring.
	 *
	 * @var Logger_Interface
	 */
	protected $logger;

	/**
	 * Configuration for API keys and settings.
	 *
	 * @var Config_Interface
	 */
	protected $config;

	/**
	 * Provider name for logging and identification.
	 *
	 * @var string
	 */
	private $provider_name;

	/**
	 * Configuration key for API authentication.
	 *
	 * @var string
	 */
	private $api_key_config;

	/**
	 * Initialize with HTTP client dependency.
	 *
	 * @param Http_Client_Interface $http WordPress HTTP client adapter
	 */
	public function __construct( Http_Client_Interface $http ) {
		$this->http = $http;
	}

	/**
	 * Set logger dependency.
	 *
	 * @param Logger_Interface $logger WordPress logging adapter
	 *
	 * @return void
	 */
	public function set_logger( Logger_Interface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * Set config dependency.
	 *
	 * @param Config_Interface $config WordPress configuration adapter
	 *
	 * @return void
	 */
	public function set_config( Config_Interface $config ): void {
		$this->config = $config;
	}

	/**
	 * Set provider name for logging and identification.
	 *
	 * @param string $provider_name Human-readable provider name
	 *
	 * @return void
	 */
	protected function set_provider_name( string $provider_name ): void {
		$this->provider_name = $provider_name;
	}

	/**
	 * Set configuration key for API authentication.
	 *
	 * @param string $api_key_config WordPress option key for API authentication
	 *
	 * @return void
	 */
	protected function set_key_config( string $api_key_config ): void {
		$this->api_key_config = $api_key_config;
	}

	/**
	 * Get the provider name for logging and identification.
	 *
	 * @return string Human-readable provider name
	 */
	protected function get_provider_name(): string {
		return $this->provider_name;
	}

	/**
	 * Get the configuration key for API authentication.
	 *
	 * @return string WordPress option key for API authentication
	 */
	protected function get_api_key_config(): string {
		return $this->api_key_config;
	}

	/**
	 * Send HTTP request to AI provider using template method pattern.
	 *
	 * @param Request $payload Complete request object
	 *
	 * @return array<string,mixed> Raw response from AI provider
	 *
	 * @throws \RuntimeException If dependencies not initialized
	 */
	protected function send_provider_request( Request $payload ): array {

		$this->ensure_dependencies_initialized();

		// Generate correlation ID for request tracking
		$cid = uniqid( 'cid_', true );

		$url     = (string) $payload->get_endpoint();
		$body    = $payload->get_body()->to_array();
		$headers = $payload->get_headers()->to_array();

		// Log request start
		$this->logger->info(
			$this->get_provider_name() . ' request',
			array(
				'url' => $url,
				'cid' => $cid,
			)
		);

		// Execute HTTP request
		$response = $this->http->post( $url, $body, $headers );

		// Log response
		$this->logger->debug(
			$this->get_provider_name() . ' response',
			array(
				'cid'      => $cid,
				'response' => $response,
			)
		);

		return $response;
	}

	/**
	 * Ensure all required dependencies are initialized.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If dependencies not set
	 */
	protected function ensure_dependencies_initialized(): void {
		if ( null === $this->logger ) {
			throw new \RuntimeException( 'Logger dependency not initialized' );
		}

		if ( null === $this->config ) {
			throw new \RuntimeException( 'Config dependency not initialized' );
		}

		if ( null === $this->provider_name ) {
			throw new \RuntimeException( 'Provider name not configured' );
		}

		if ( null === $this->api_key_config ) {
			throw new \RuntimeException( 'API key config not configured' );
		}
	}
}
