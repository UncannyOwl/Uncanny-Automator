<?php
// File: src/AI/Provider/BaseProviderTrait.php

declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Core\Traits;

use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Config_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Logger_Interface;
use Uncanny_Automator\Core\Lib\AI\Http\Request;

/**
 * Trait for injecting horizontal dependencies into AI providers.
 *
 * Only use this trait for dependencies that are shared across all providers.
 * Do not add provider-specific dependencies here.
 *
 * Important: Do not use this trait along with OpenAI compatible abstract if you are extending it.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Traits
 * @since 5.6
 */
trait Base_Provider_Trait {

	/**
	 * Configuration interface instance.
	 *
	 * @var Config_Interface
	 */
	private $config;

	/**
	 * Logger interface instance.
	 *
	 * @var Logger_Interface
	 */
	private $logger;

	/**
	 * Inject the configuration source.
	 *
	 * @param Config_Interface $config Configuration interface instance
	 *
	 * @return void
	 */
	public function set_config( Config_Interface $config ): void {
		$this->config = $config;
	}

	/**
	 * Inject the logger implementation.
	 *
	 * @param Logger_Interface $logger Logger interface instance
	 *
	 * @return void
	 */
	public function set_logger( Logger_Interface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * Ensure required dependencies have been injected before use.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If dependencies are not initialized
	 */
	private function ensure_initialized(): void {
		if ( ! isset( $this->config, $this->logger ) ) {
			throw new \RuntimeException( 'Provider dependencies not initialized' );
		}
	}

	/**
	 * Send request to Claude API.
	 *
	 * @param Request $payload Complete request object
	 *
	 * @return array<string,mixed> Raw API response
	 */
	protected function send_http_request( Request $payload ): array {

		$this->ensure_initialized();

		// Generate correlation ID
		$cid = uniqid( 'cid_', true );

		$url     = (string) $payload->get_endpoint();
		$body    = $payload->get_body()->to_array();
		$headers = $payload->get_headers()->to_array();

		// Record metrics and log request
		$this->logger->info(
			$this->get_provider_name() . ' request',
			array(
				'url' => $url,
				'cid' => $cid,
			)
		);

		// Perform HTTP POST.
		$response = $this->http->post( $url, $body, $headers );

		$this->logger->debug(
			$this->get_provider_name() . ' response',
			array(
				'cid'      => $cid,
				'response' => $response,
			)
		);

		return $response;
	}
}
