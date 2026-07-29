<?php
/**
 * MCP Client token service.
 *
 * Provides thin helpers around token generation so the logic can be tested in isolation.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Client;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use WP_Error;

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
	 * Last token issuance error.
	 *
	 * @var WP_Error|null
	 */
	private $last_error;

	/**
	 * Constructor.
	 *
	 * @param callable|null     $current_user_id_callback Optional current user ID callback.
	 * @param Token_Manager|null $token_manager Optional token manager.
	 */
	public function __construct( ?callable $current_user_id_callback = null, ?Token_Manager $token_manager = null ) {
		$this->current_user_id_callback = $current_user_id_callback ? $current_user_id_callback : 'get_current_user_id';
		$this->token_manager            = $token_manager ?? new Token_Manager();
	}

	/**
	 * Get the last token issuance error.
	 *
	 * @return WP_Error|null Error or null after successful issuance.
	 */
	public function get_last_error(): ?WP_Error {
		return $this->last_error;
	}

	/**
	 * Get a bearer token for the current user.
	 *
	 * @return string The token or empty string if unavailable.
	 */
	public function get_bearer_token(): string {
		$user_id = (int) call_user_func( $this->current_user_id_callback );

		return $this->get_token_for_user( $user_id );
	}

	/**
	 * Get or create a token for a specific user.
	 *
	 * @param int $user_id User identifier.
	 * @return string Token or empty string on failure.
	 */
	public function get_token_for_user( int $user_id ): string {
		$this->last_error = null;

		if ( $user_id <= 0 ) {
			return '';
		}

		$configuration_error = $this->token_manager->get_internal_token_configuration_error();
		if ( $configuration_error instanceof WP_Error ) {
			$this->last_error = $configuration_error;
			return '';
		}

		// Use Token_Manager directly to generate internal tokens.
		$token = $this->token_manager->get_or_create_internal_token(
			$user_id,
			array(
				Authenticated_Token_Context::SCOPE_READ,
				Authenticated_Token_Context::SCOPE_WRITE,
				Authenticated_Token_Context::SCOPE_TOOLS,
			),
			DAY_IN_SECONDS,
			'MCP Chat Session'
		);

		if ( null === $token ) {
			$this->last_error = new WP_Error(
				'automator_mcp_internal_token_unavailable',
				esc_html_x( 'Uncanny Agent could not create its internal access token.', 'MCP internal token error', 'uncanny-automator' ),
				array( 'status' => 500 )
			);
			return '';
		}

		return $token;
	}
}
