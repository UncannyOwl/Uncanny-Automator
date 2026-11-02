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

	public const PURPOSE_FIRST_PARTY_AGENT = 'first_party_agent';

	public const PURPOSE_OAUTH_MCP = 'oauth_mcp';

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

	/** First-party Agent read access outside MCP. */
	const SCOPE_AGENT_READ = 'agent:read';

	/** First-party Agent mutation access outside MCP. */
	const SCOPE_AGENT_WRITE = 'agent:write';

	/** Permission to receive an OAuth refresh token. */
	const SCOPE_OFFLINE_ACCESS = 'offline_access';

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

	private string $purpose;

	private string $resource;

	private string $client_id;

	/**
	 * Constructor.
	 *
	 * @param WP_User $user Authenticated WordPress user.
	 * @param array   $scopes Granted token scopes.
	 * @param string  $purpose Credential purpose.
	 * @param string  $resource_identifier Protected resource identifier.
	 * @param string  $client_id OAuth or first-party client identifier.
	 */
	public function __construct(
		WP_User $user,
		array $scopes,
		string $purpose = self::PURPOSE_FIRST_PARTY_AGENT,
		string $resource_identifier = '',
		string $client_id = ''
	) {
		$this->user      = $user;
		$this->scopes    = $this->normalize_scopes( $scopes );
		$this->purpose   = $purpose;
		$this->resource  = $resource_identifier;
		$this->client_id = $client_id;
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
	 * Get resource.
	 *
	 * @return string
	 */
	public function get_resource(): string {
		return $this->resource;
	}
	/**
	 * Get client id.
	 *
	 * @return string
	 */
	public function get_client_id(): string {
		return $this->client_id;
	}
	/**
	 * Is first party agent.
	 *
	 * @return bool
	 */
	public function is_first_party_agent(): bool {
		return self::PURPOSE_FIRST_PARTY_AGENT === $this->purpose;
	}
	/**
	 * Is oauth mcp.
	 *
	 * @return bool
	 */
	public function is_oauth_mcp(): bool {
		return self::PURPOSE_OAUTH_MCP === $this->purpose;
	}

	/**
	 * Determine whether the token grants a required scope.
	 *
	 * Legacy scope names exist only as migration inputs. Token_Manager replaces
	 * them before creating this runtime context, so authorization decisions are
	 * always made against the explicit read/write grants.
	 *
	 * @param string $required_scope Required scope.
	 * @return bool Whether access is granted.
	 */
	public function has_scope( string $required_scope ): bool {
		if ( self::SCOPE_READ === $required_scope && in_array( self::SCOPE_WRITE, $this->scopes, true ) ) {
			return true;
		}

		if ( self::SCOPE_AGENT_READ === $required_scope && in_array( self::SCOPE_AGENT_WRITE, $this->scopes, true ) ) {
			return true;
		}

		return in_array( $required_scope, $this->scopes, true );
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
