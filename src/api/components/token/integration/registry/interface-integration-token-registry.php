<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Token\Integration\Registry;

use Uncanny_Automator\Api\Components\Token\Integration\Value_Objects\Integration_Token_Code;

/**
 * Integration Token Registry Interface.
 *
 * Contract for integration token registration and discovery.
 * Database-agnostic interface for integration token definitions.
 *
 * This is for integration-level tokens (tokens provided by integrations).
 *
 * @since 7.0.0
 */
interface Integration_Token_Registry {

	/**
	 * Get all available integration tokens.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 *
	 * @return array Array of integration token definitions.
	 */
	public function get_available_tokens( array $options = array() ): array;

	/**
	 * Get specific integration token definition.
	 *
	 * @param Integration_Token_Code $code Integration token code.
	 * @param array                  $options Format options: ['include_schema' => bool].
	 *
	 * @return array|null Integration token definition or null if not found.
	 */
	public function get_token_definition( Integration_Token_Code $code, array $options = array() ): ?array;

	/**
	 * Register an integration token type.
	 *
	 * @param string $code Integration token code.
	 * @param array  $definition Integration token definition.
	 *
	 * @return void
	 */
	public function register_token( string $code, array $definition ): void;

	/**
	 * Check if integration token is registered.
	 *
	 * @param Integration_Token_Code $code Integration token code.
	 *
	 * @return bool True if registered.
	 */
	public function is_registered( Integration_Token_Code $code ): bool;

	/**
	 * Get integration tokens by integration.
	 *
	 * @param string $integration Integration code.
	 *
	 * @return array Array of integration tokens for the integration.
	 */
	public function get_tokens_by_integration( string $integration ): array;
}
