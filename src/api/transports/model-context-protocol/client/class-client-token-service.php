<?php
/**
 * MCP Client token service.
 *
 * Provides thin helpers around token generation so the logic can be tested in isolation.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\OAuth\Token_Manager;

/**
 * Class Client_Token_Service
 */
class Client_Token_Service {

	/**
	 * Callable used to obtain the current user ID.
	 *
	 * @var callable
	 */
	private $current_user_id_callback;

	/**
	 * Token manager instance.
	 *
	 * @var Token_Manager
	 */
	private $token_manager;

	/**
	 * Constructor.
	 *
	 * @param callable|null $current_user_id_callback Optional current user ID callback.
	 */
	public function __construct( ?callable $current_user_id_callback = null ) {
		$this->current_user_id_callback = $current_user_id_callback ? $current_user_id_callback : 'get_current_user_id';
		$this->token_manager            = new Token_Manager();
	}

	/**
	 * Get a bearer token for the current user.
	 *
	 * @return string The token or empty string if unavailable.
	 */
	public function get_bearer_token(): string {
		$user_id = (int) call_user_func( $this->current_user_id_callback );

		if ( $user_id <= 0 ) {
			return '';
		}

		return $this->get_token_for_user( $user_id );
	}

	/**
	 * Get or create a token for a specific user.
	 *
	 * @param int $user_id User identifier.
	 * @return string Token or empty string on failure.
	 */
	public function get_token_for_user( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		// Use Token_Manager directly to generate internal tokens.
		$token = $this->token_manager->get_or_create_internal_token(
			$user_id,
			array( 'mcp:read', 'mcp:write' ),
			DAY_IN_SECONDS,
			'MCP Chat Session'
		);

		return $token ?? '';
	}
}
