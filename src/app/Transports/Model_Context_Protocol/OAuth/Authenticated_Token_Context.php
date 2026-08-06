<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

use WP_User;

/**
 * Authenticated MCP bearer identity and its granted authorization scopes.
 *
 * Authentication callers must retain this context until the route or operation
 * scope has been authorized. Returning only WP_User discards the token's least-
 * privilege boundary and must remain a legacy compatibility path.
 *
 * @since 7.4.1
 */
final class Authenticated_Token_Context {

	/**
	 * Read-only MCP and Writer operations.
	 *
	 * @var string
	 */
	const SCOPE_READ = 'mcp:read';

	/**
	 * Writer mutation operations.
	 *
	 * @var string
	 */
	const SCOPE_WRITE = 'mcp:write';

	/**
	 * MCP tool invocation.
	 *
	 * @var string
	 */
	const SCOPE_TOOLS = 'mcp:tools';

	/**
	 * Uncanny Agent site-key binding proof.
	 *
	 * @var string
	 */
	const SCOPE_KEY_BINDING = 'uncanny_agent:key_binding';

	/**
	 * Legacy umbrella scope retained for already-issued integrations.
	 *
	 * @var string
	 */
	const SCOPE_LEGACY = 'mcp';

	/**
	 * Authenticated WordPress user.
	 *
	 * @var WP_User
	 */
	private WP_User $user;

	/**
	 * Normalized granted scopes.
	 *
	 * @var string[]
	 */
	private array $scopes;

	/**
	 * Constructor.
	 *
	 * @param WP_User $user Authenticated WordPress user.
	 * @param array   $scopes Granted token scopes.
	 */
	public function __construct( WP_User $user, array $scopes ) {
		$this->user   = $user;
		$this->scopes = $this->normalize_scopes( $scopes );
	}

	/**
	 * Get the authenticated WordPress user.
	 *
	 * @return WP_User
	 */
	public function get_user(): WP_User {
		return $this->user;
	}

	/**
	 * Get normalized granted scopes.
	 *
	 * @return string[]
	 */
	public function get_scopes(): array {
		return $this->scopes;
	}

	/**
	 * Determine whether the token grants a required scope.
	 *
	 * Legacy "mcp" credentials predate granular enforcement and retain their
	 * former full MCP/Writer behavior until they expire or are rotated.
	 *
	 * @param string $required_scope Required scope.
	 * @return bool Whether access is granted.
	 */
	public function has_scope( string $required_scope ): bool {
		return in_array( self::SCOPE_LEGACY, $this->scopes, true )
			|| in_array( $required_scope, $this->scopes, true );
	}

	/**
	 * Normalize scope values into a unique list of non-empty strings.
	 *
	 * @param array $scopes Candidate scopes.
	 * @return string[]
	 */
	private function normalize_scopes( array $scopes ): array {
		$normalized = array();

		foreach ( $scopes as $scope ) {
			if ( ! is_string( $scope ) ) {
				continue;
			}

			$scope = trim( $scope );
			if ( '' !== $scope ) {
				$normalized[ $scope ] = $scope;
			}
		}

		return array_values( $normalized );
	}
}
