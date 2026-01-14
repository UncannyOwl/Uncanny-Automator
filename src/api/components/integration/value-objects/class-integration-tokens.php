<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use Uncanny_Automator\Api\Components\Token\Integration\Integration_Token;
use InvalidArgumentException;

/**
 * Integration Tokens Value Object.
 *
 * Contains the integration tokens provided by this integration.
 * Tokens are stored as Integration_Token objects keyed by their token code.
 *
 * This is for integration-level tokens (tokens provided by integrations).
 *
 * @since 7.0.0
 */
class Integration_Tokens {

	/**
	 * The tokens.
	 *
	 * @var array<string, Integration_Token>
	 */
	private array $tokens;

	/**
	 * Constructor.
	 *
	 * @param array $tokens Tokens array with Integration_Token objects keyed by code.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid tokens.
	 */
	public function __construct( array $tokens = array() ) {
		$this->validate( $tokens );
		$this->tokens = $this->build_token_objects( $tokens );
	}

	/**
	 * Get tokens.
	 *
	 * @return array<string, Integration_Token> Array of Integration_Token objects keyed by code.
	 */
	public function get_tokens(): array {
		return $this->tokens;
	}

	/**
	 * Check if integration has tokens.
	 *
	 * @return bool
	 */
	public function has_tokens(): bool {
		return ! empty( $this->tokens );
	}

	/**
	 * Convert to array.
	 *
	 * @return array Array of token data keyed by code.
	 */
	public function to_array(): array {
		return $this->tokens_to_array( $this->tokens );
	}

	/**
	 * Convert to REST format.
	 *
	 * Converts empty arrays to empty objects for JavaScript compatibility.
	 *
	 * @return array|object
	 */
	public function to_rest() {
		$tokens = $this->tokens_to_array( $this->tokens );
		return empty( $tokens ) ? (object) array() : $tokens;
	}

	/**
	 * Validate tokens.
	 *
	 * @param array $tokens Tokens to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $tokens ): void {
		// Type hint enforces array - no additional validation needed.
		// Method retained for consistency and potential future validation.
	}

	/**
	 * Build Integration_Token objects from array data.
	 *
	 * @param array $tokens Array of token data keyed by code.
	 *
	 * @return array<string, Integration_Token> Array of Integration_Token objects keyed by code.
	 */
	private function build_token_objects( array $tokens ): array {
		$objects = array();

		foreach ( $tokens as $code => $token_data ) {
			// If already an Integration_Token object, use it directly
			if ( $token_data instanceof Integration_Token ) {
				$objects[ $code ] = $token_data;
				continue;
			}

			// Otherwise, create from array data
			if ( ! is_array( $token_data ) ) {
				continue;
			}

			// Ensure code is set in the token data
			if ( ! isset( $token_data['code'] ) ) {
				$token_data['code'] = $code;
			}

			$config           = \Uncanny_Automator\Api\Components\Token\Integration\Integration_Token_Config::from_array( $token_data );
			$objects[ $code ] = new Integration_Token( $config );
		}

		return $objects;
	}

	/**
	 * Convert array of Integration_Token objects to arrays.
	 *
	 * @param array<string, Integration_Token> $tokens Array of Integration_Token objects.
	 *
	 * @return array Array of token data keyed by code.
	 */
	private function tokens_to_array( array $tokens ): array {
		$arrays = array();

		foreach ( $tokens as $code => $token ) {
			$arrays[ $code ] = $token->to_array();
		}

		return $arrays;
	}
}
