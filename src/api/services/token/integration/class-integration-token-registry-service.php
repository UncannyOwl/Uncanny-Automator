<?php

namespace Uncanny_Automator\Api\Services\Token\Integration;

use Uncanny_Automator\Api\Components\Token\Integration\Registry\WP_Integration_Token_Registry;
use Uncanny_Automator\Traits\Singleton;

/**
 * Integration Token Registry Service.
 *
 * Provides a clean interface to integration token registry operations.
 *
 * This is for integration-level tokens ( Universal tokens etc. ) provided by integrations.
 *
 * @since 7.0.0
 */
class Integration_Token_Registry_Service {

	use Singleton;

	/**
	 * Integration token registry.
	 *
	 * @var WP_Integration_Token_Registry
	 */
	private $registry;

	/**
	 * Initialize the service.
	 *
	 * @return void
	 */
	private function __construct() {
		$this->registry = new WP_Integration_Token_Registry();
	}

	/**
	 * Get integration tokens for an integration.
	 *
	 * @param string $integration_code Integration code.
	 *
	 * @return array Array of integration token definitions in Integration_Token format.
	 */
	public function get_tokens_for_integration( string $integration_code ): array {
		$tokens = $this->registry->get_tokens_by_integration( $integration_code );

		// Map original format to Integration_Token format
		$integration_tokens = array();
		foreach ( $tokens as $token_id => $token_data ) {
			// Map original fields to normalized format
			$integration_tokens[ $token_id ] = array(
				'code'               => $token_id,
				'name'               => $token_data['name'] ?? '',
				'data_type'          => $this->normalize_token_type( $token_data['type'] ?? 'text' ),
				'requires_user_data' => $token_data['requiresUser'] ?? false,
			);
		}

		return $integration_tokens;
	}

	/**
	 * Normalize token type.
	 *
	 * @param string $type Token type.
	 *
	 * @return string Normalized type.
	 */
	private function normalize_token_type( string $type ): string {
		$mapping = array(
			'int'      => 'integer',
			'loopable' => 'array',
		);

		return $mapping[ $type ] ?? $type;
	}
}
