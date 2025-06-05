<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Provider;

use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\AI_Provider_Interface;
use Uncanny_Automator\Core\Lib\AI\Core\Abstracts\Base_AI_Provider_Abstract;
use Uncanny_Automator\Core\Lib\AI\Exception\AI_Service_Exception;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Http_Client_Interface;
use Uncanny_Automator\Core\Lib\AI\Http\Payload;
use Uncanny_Automator\Core\Lib\AI\Http\Request;
use Uncanny_Automator\Core\Lib\AI\Http\Response;
use Uncanny_Automator\Core\Lib\AI\Exception\Validation_Exception;

/**
 * Anthropic Claude provider implementation.
 *
 * Handles communication with Anthropic's Claude API.
 * Supports Claude Opus, Sonnet, and Haiku models.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Provider
 * @since 5.6
 */
final class Claude_Provider extends Base_AI_Provider_Abstract implements AI_Provider_Interface {

	/**
	 * Initialize with HTTP client.
	 *
	 * @param Http_Client_Interface $http WordPress HTTP client adapter
	 */
	public function __construct( Http_Client_Interface $http ) {
		parent::__construct( $http );

		// Configure provider-specific settings.
		$this->set_provider_name( 'Claude' );
		$this->set_key_config( 'automator_claude_api_key' );
	}

	/**
	 * Create payload builder with Claude authentication.
	 *
	 * @return Payload Pre-configured payload builder
	 *
	 * @throws \RuntimeException If dependencies not initialized
	 */
	public function create_builder(): Payload {

		$this->ensure_dependencies_initialized();

		$api_key = $this->config->get( $this->get_api_key_config() );

		return ( new Payload() )
			->headers( 'x-api-key', $api_key )
			->headers( 'Content-Type', 'application/json' )
			->headers( 'anthropic-version', '2023-06-01' )
			->json_content();
	}
	/**
	 * Send request.
	 *
	 * @param Request $payload The payload.
	 * @return array
	 */
	public function send_request( Request $payload ): array {
		// Use the base class send_provider_request method
		return $this->send_provider_request( $payload );
	}

	/**
	 * Parse Claude response into standardized format.
	 *
	 * @param array<string,mixed> $response Raw Claude response
	 *
	 * @return Response Standardized response object
	 *
	 * @throws AI_Service_Exception If API returns error
	 * @throws Validation_Exception If response has no content
	 */
	public function parse_response( array $response ): Response {

		if ( isset( $response['error'] ) ) {

			$message = $response['error']['message'] ?? 'unknown_error';
			$type    = $response['error']['type'] ?? 'generic';

			$error_message = sprintf(
				/* translators: %1$s: Error message from Claude API */
				esc_html_x(
					'Claude error: %1$s',
					'Claude',
					'uncanny-automator'
				),
				esc_html( $message )
			);

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new AI_Service_Exception( $error_message, esc_html( $type ) );

		}

		// Ensure content exists.
		if ( ! isset( $response['content'][0]['text'] ) ) {
			throw new Validation_Exception(
				esc_html_x(
					'The model predicted a completion that results in no output. Consider adjusting your prompt',
					'Claude',
					'uncanny-automator'
				)
			);
		}

		$content = $response['content'][0]['text'];

		$input_tokens            = $response['usage']['input_tokens'] ?? 0;
		$output_tokens           = $response['usage']['output_tokens'] ?? 0;
		$cache_creation_tokens   = $response['usage']['cache_creation_input_tokens'] ?? 0;
		$cache_read_input_tokens = $response['usage']['cache_read_input_tokens'] ?? 0;

		$total_tokens = $input_tokens + $output_tokens + $cache_creation_tokens + $cache_read_input_tokens;

		// Extract usage metadata.
		$metadata = array(
			'prompt_tokens'         => $input_tokens,
			'completion_tokens'     => $output_tokens,
			'total_tokens'          => $total_tokens,
			'cache_creation_tokens' => $cache_creation_tokens,
			'cache_read_tokens'     => $cache_read_input_tokens,
			'model'                 => $response['model'] ?? '',
		);

		return new Response( $content, $metadata, $response );
	}
}
