<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;

/**
 * Small explicit scope model for MCP OAuth.
 */
final class OAuth_Scope_Policy {

	/**
	 * Normalize and validate a space-delimited requested scope.
	 *
	 * Unsupported scopes are dropped (RFC 6749 section 3.3): the token response
	 * names the scope actually granted. Agent scopes are never dropped.
	 *
	 * @return string[]
	 * @throws \InvalidArgumentException When an untrusted client requests Agent scopes or Agent scopes are incomplete.
	 */
	public function parse( string $scope, bool $allow_agent = false ): array {
		$requested = preg_split( '/\s+/', trim( $scope ) );
		$requested = is_array( $requested ) ? array_values( array_unique( array_filter( $requested ) ) ) : array();
		if ( empty( $requested ) ) {
			$requested = array( Authenticated_Token_Context::SCOPE_READ );
		}

		$allowed = $this->get_supported_scopes();
		if ( $allow_agent ) {
			$allowed[] = Authenticated_Token_Context::SCOPE_AGENT_READ;
			$allowed[] = Authenticated_Token_Context::SCOPE_AGENT_WRITE;
		}
		$unsupported = array_diff( $requested, $allowed );
		if ( $unsupported ) {
			if ( ! $allow_agent && Hosted_Agent_Access::requested( $requested ) ) {
				throw new \InvalidArgumentException( 'Uncanny Automator permissions require the trusted hosted app callback (hosted_app_untrusted). Ask the site administrator to check UNCANNY_AGENT_APP_URL in wp-config.php, then connect again from the trusted app.' );
			}
			$requested = array_values( array_diff( $requested, $unsupported ) );
			if ( empty( $requested ) ) {
				$requested = array( Authenticated_Token_Context::SCOPE_READ );
			}
		}

		if ( in_array( Authenticated_Token_Context::SCOPE_WRITE, $requested, true ) ) {
			$requested[] = Authenticated_Token_Context::SCOPE_READ;
		}

		if ( Hosted_Agent_Access::requested( $requested ) ) {
			// The existing first-party credential grants both Agent permissions.
			// Require the complete grant on consent; never silently widen a token.
			foreach ( array( 'agent:read', 'agent:write', 'mcp:read', 'mcp:write', 'offline_access' ) as $required ) {
				if ( ! in_array( $required, $requested, true ) ) {
					throw new \InvalidArgumentException( 'Uncanny Automator access requires agent:read, agent:write, mcp:read, mcp:write and offline_access (oauth_scope_incomplete). Reconnect the site under Uncanny Automator > Connected sites and approve the complete permission request. A token refresh cannot add missing permissions.' );
				}
			}
		}

		$requested = array_values( array_unique( $requested ) );
		sort( $requested, SORT_STRING );

		return $requested;
	}

	/**
	 * Return every supported OAuth scope.
	 *
	 * @return string[]
	 */
	public function get_supported_scopes(): array {
		return array(
			Authenticated_Token_Context::SCOPE_READ,
			Authenticated_Token_Context::SCOPE_WRITE,
			Authenticated_Token_Context::SCOPE_OFFLINE_ACCESS,
		);
	}

	/**
	 * Serialize a normalized scope set.
	 */
	public function serialize( array $scopes ): string {
		return implode( ' ', $this->parse( implode( ' ', $scopes ), true ) );
	}
}
