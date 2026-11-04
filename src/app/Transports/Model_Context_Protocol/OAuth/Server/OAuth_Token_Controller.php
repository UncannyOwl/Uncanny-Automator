<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Auth_Log;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Internal_Token_Configuration;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Domain\Token_Exchange_Failure;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Domain\Token_Exchange_Policy;
use WP_REST_Request;
use WP_REST_Response;

/**
 * OAuth token exchange and per-approval cancellation.
 */
final class OAuth_Token_Controller {

	private const ACCESS_TOKEN_LIFETIME = HOUR_IN_SECONDS;

	/** Token request bodies are small; a larger one is refused, not parsed field by field. */
	private const MAX_FORM_BODY_BYTES = 65536;

	private OAuth_Server_Configuration $configuration;

	private OAuth_Client_Registry $clients;

	private OAuth_Scope_Policy $scope_policy;

	private OAuth_Artifact_Store $store;

	private OAuth_Refresh_Token_Service $refresh_tokens;

	private Token_Manager $token_manager;

	private OAuth_Rate_Limiter $rate_limiter;

	private OAuth_User_Revocation_Marker $revocation_marker;

	/**
	 * Constructor.
	 */
	public function __construct(
		?OAuth_Server_Configuration $configuration = null,
		?OAuth_Client_Registry $clients = null,
		?OAuth_Scope_Policy $scope_policy = null,
		?OAuth_Artifact_Store $store = null,
		?OAuth_Refresh_Token_Service $refresh_tokens = null,
		?Token_Manager $token_manager = null,
		?OAuth_Rate_Limiter $rate_limiter = null
	) {
		$this->configuration     = $configuration ?? new OAuth_Server_Configuration();
		$this->clients           = $clients ?? new OAuth_Client_Registry();
		$this->scope_policy      = $scope_policy ?? new OAuth_Scope_Policy();
		$this->store             = $store ?? new OAuth_Artifact_Store();
		$this->token_manager     = $token_manager ?? new Token_Manager();
		$this->refresh_tokens    = $refresh_tokens ?? new OAuth_Refresh_Token_Service( $this->store, $this->token_manager );
		$this->rate_limiter      = $rate_limiter ?? new OAuth_Rate_Limiter();
		$this->revocation_marker = new OAuth_User_Revocation_Marker( $this->store );
	}

	/**
	 * Register the token and cancellation endpoints.
	 */
	public function register_routes(): void {
		register_rest_route(
			'automator/v1',
			'/oauth/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'exchange' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'automator/v1',
			'/oauth/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'revoke' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Exchange one authorization code or refresh token.
	 */
	public function exchange( WP_REST_Request $request ): WP_REST_Response {
		OAuth_Cache_Policy::no_cache();
		if ( ! $this->configuration->can_use_existing_credentials() ) {
			// Name the real cause; the configuration words it for a site owner.
			$reason = $this->configuration->is_enabled() ? Token_Exchange_Policy::EXCHANGE_REQUIRES_HTTPS : Token_Exchange_Policy::EXCHANGE_DISABLED;
			return $this->failure_response( Token_Exchange_Policy::failure( $reason ), $this->configuration->get_unavailable_description() );
		}
		$content_type = strtolower( trim( (string) strtok( (string) $request->get_header( 'content-type' ), ';' ) ) );
		if ( 'application/x-www-form-urlencoded' !== $content_type ) {
			return $this->error( 'invalid_request', 'The token endpoint requires form-encoded parameters.', 400, false, 'UA-MCP-502' );
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_FORM_BODY_BYTES ) {
			// A token request is a few hundred bytes; nothing in a larger one is read.
			return $this->error( 'invalid_request', 'The token request body is larger than 64 KB.', 400, false, 'UA-MCP-536' );
		}

		// slow_down is borrowed from RFC 8628 on purpose: the HTTP 429 is the
		// real signal, and the code names the remedy better than any RFC 6749
		// error would. Both pre-authentication buckets are IP-only: client_id is
		// caller-controlled here and must not create one transient per value.
		$allowed = $this->rate_limiter->allows( 'token_ip', '', 30, MINUTE_IN_SECONDS );
		if ( null === $allowed ) {
			return $this->error( 'temporarily_unavailable', OAuth_Rate_Limiter::STORAGE_FAILURE_MESSAGE, 503, false, 'UA-MCP-537' );
		}
		if ( false === $allowed ) {
			return $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::TOKEN_RATE_LIMIT ) );
		}

		$grant_type = $this->form_param( $request, 'grant_type' );
		if ( 'authorization_code' === $grant_type ) {
			return $this->exchange_code( $request );
		}
		if ( 'refresh_token' === $grant_type ) {
			return $this->exchange_refresh_token( $request );
		}

		return $this->error( 'unsupported_grant_type', 'Only authorization_code and refresh_token are supported.', 400, false, 'UA-MCP-504' );
	}

	/**
	 * RFC 7009 cancellation. Authentication is checked here, not by WP cookies.
	 * Explicit retirement may remove an unused public DCR registration only.
	 */
	public function revoke( WP_REST_Request $request ): WP_REST_Response {
		OAuth_Cache_Policy::no_cache();

		$allowed = $this->rate_limiter->allows( 'revoke_ip', '', 30, MINUTE_IN_SECONDS );
		if ( null === $allowed ) {
			return $this->error( 'temporarily_unavailable', OAuth_Rate_Limiter::STORAGE_FAILURE_MESSAGE, 503, false, 'UA-MCP-537' );
		}
		if ( false === $allowed ) {
			return $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::CANCEL_RATE_LIMIT ) );
		}

		$content_type = strtolower( trim( (string) strtok( (string) $request->get_header( 'content-type' ), ';' ) ) );

		if ( 'application/x-www-form-urlencoded' !== $content_type ) {
			return $this->error( 'invalid_request', 'Use form-encoded parameters.', 400, false, 'UA-MCP-528' );
		}

		$token = $this->form_param( $request, 'token' );

		if ( '' === $token || strlen( $token ) > 8192 ) {
			return $this->error( 'invalid_request', 'A token is required.', 400, false, 'UA-MCP-529' );
		}

		$refresh       = $this->refresh_tokens->inspect( $token );
		$access        = null === $refresh ? $this->token_manager->inspect_oauth_token_for_revocation( $token ) : null;
		$record        = $refresh ?? $access;
		$basic         = $this->parse_basic_credentials( $request );
		$provided_id   = is_array( $basic ) ? $basic['client_id'] : $this->form_param( $request, 'client_id' );
		$retire        = '1' === $this->form_param( $request, 'automator_retire_client' ) && is_array( $refresh );
		$bound_id      = is_array( $record ) ? (string) $record['client_id'] : $provided_id;
		$authenticated = $this->authenticate_client( $request, $bound_id );
		$work          = function () use ( $request, $token, $record, $refresh, $access, $retire, $bound_id, $authenticated ): WP_REST_Response {
			$client = $authenticated;
			if ( is_array( $client ) && ! $this->clients->is_authenticated_client_current( $client ) ) {
				$client = $this->error( 'invalid_client', 'Client authentication failed.', 401, true, 'UA-MCP-520' );
			}
			// A public client's signed refresh proof remains inspectable after
			// retirement. This narrow retry path may finish deleting a tombstoned
			// registration; it cannot authorize a new grant or a foreign client.
			if ( ! is_array( $client ) && $retire && 'public' === ( $refresh['client_type'] ?? '' )
				&& $this->clients->is_revoked( $bound_id, true )
				&& hash_equals( $bound_id, $this->form_param( $request, 'client_id' ) )
				&& '' === $this->read_authorization_header( $request )
				&& null === $this->parse_basic_credentials( $request )
				&& '' === trim( $this->form_param( $request, 'client_secret' ) ) ) {
				$stored_client = null;
				foreach ( $this->clients->list_clients() as $candidate ) {
					if ( ( $candidate['client_id'] ?? '' ) === $bound_id ) {
						$stored_client = $candidate;
						break;
					}
				}
				if ( null === $stored_client ) {
					$done = $this->refresh_tokens->revoke_family_checked( (string) $refresh['family_id'], (int) $refresh['user_id'], $bound_id );
					return $done ? $this->success( array() ) : $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::CANCEL_SAVE_FAILED ) );
				}
				if ( 'dcr' === ( $stored_client['source'] ?? '' ) && 'public' === ( $stored_client['type'] ?? '' ) ) {
					$client = $stored_client;
				}
			}
			if ( ! is_array( $client ) ) {
				return $client;
			}
			if ( null === $record ) {
				return $this->success( array() );
			}

			// Only token-derived identifiers choose the grant to cancel.
			$family_id = (string) ( $refresh['family_id'] ?? $access['refresh_family'] ?? '' );
			if ( '' !== $family_id ) {
				$done = $this->refresh_tokens->revoke_family_checked( $family_id, (int) $record['user_id'], (string) $client['client_id'] );
			} else {
				$done = $this->token_manager->revoke_token( $token );
				$done = $done || null === $this->token_manager->inspect_oauth_token_for_revocation( $token );
			}
			if ( ! $done ) {
				return $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::CANCEL_SAVE_FAILED ) );
			}

			if ( $retire && 'dcr' === ( $client['source'] ?? '' ) && 'public' === ( $client['type'] ?? '' ) ) {
				$artifacts = $this->store->has_live_client_artifacts( $bound_id );
				$tokens    = $this->token_manager->has_live_client_tokens( $bound_id );
				if ( null === $artifacts || null === $tokens ) {
					// The grant asked for is cancelled. Whether the registration is
					// unused could not be proven, so it stays; pruning at the
					// registration limit retires it once it provably is.
					return $this->success( array() );
				}
				if ( ! $artifacts && ! $tokens && ( ! $this->clients->revoke( $bound_id ) || ! $this->clients->delete( $bound_id ) ) ) {
					return $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::CANCEL_SAVE_FAILED ) );
				}
			}
			return $this->success( array() );
		};
		// Lifecycle order is client -> family -> credential aggregate. Pending
		// consent/code creation and token issuance use the same client lock.
		if ( $retire ) {
			$result = $this->store->with_lock( 'client', $bound_id, $work );
			return $result ?? $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::CANCEL_SAVE_FAILED ) );
		}
		return $work();
	}

	/**
	 * Atomically consume one code and issue bounded credentials.
	 */
	private function exchange_code( WP_REST_Request $request ): WP_REST_Response {
		$code = $this->form_param( $request, 'code' );
		// The code record is previewed before client authentication because
		// it holds the bound client_id that authenticate_client() verifies
		// against. Cost of the ordering: an unauthenticated caller can tell
		// invalid_grant from invalid_client. Codes are 128-bit random and
		// both rate-limit buckets sit in front, so the probe buys nothing.
		$preview = '' !== $code ? $this->store->get( 'code', $code ) : null;
		if ( ! is_array( $preview ) || (int) ( $preview['expires_at'] ?? 0 ) <= time() ) {
			return $this->error( 'invalid_grant', 'The authorization code is invalid, expired, or already used.', 400, false, 'UA-MCP-505' );
		}

		$client = $this->authenticate_client( $request, (string) ( $preview['client_id'] ?? '' ) );
		if ( ! is_array( $client ) ) {
			return $client;
		}
		$result = $this->store->with_lock(
			'client',
			(string) $preview['client_id'],
			function () use ( $request, $preview, $code, $client ): WP_REST_Response {
				if ( ! $this->clients->is_authenticated_client_current( $client ) ) {
					return $this->error( 'invalid_client', 'Client authentication failed.', 401, true, 'UA-MCP-520' );
				}

				$redirect_uri = $this->form_param( $request, 'redirect_uri' );
				$resource     = $this->requested_resource( $request );
				$verifier     = $this->form_param( $request, 'code_verifier' );
				$challenge    = $this->pkce_challenge( $verifier );
				// RFC 8707: a foreign resource indicator is its own error, not a
				// code-binding failure, so the client can see what to correct.
				if ( ! hash_equals( $this->configuration->get_resource(), $resource ) ) {
					return $this->error( 'invalid_target', 'The resource parameter must name this MCP endpoint.', 400, false, 'UA-MCP-525' );
				}
				if (
				'' === $challenge
				|| ! isset( $preview['code_challenge'] )
				|| ! is_string( $preview['code_challenge'] )
				|| ! hash_equals( $preview['code_challenge'], $challenge )
				|| ! isset( $preview['redirect_uri'], $preview['resource'] )
				|| ! is_string( $preview['redirect_uri'] )
				|| ! is_string( $preview['resource'] )
				|| ! hash_equals( $preview['redirect_uri'], $redirect_uri )
				|| ! hash_equals( $preview['resource'], $resource )
				) {
					return $this->error( 'invalid_grant', 'The authorization code binding is invalid.', 400, false, 'UA-MCP-506' );
				}

				$record = $this->store->consume( 'code', $code );
				if ( ! is_array( $record ) || ! hash_equals( (string) $preview['client_id'], (string) ( $record['client_id'] ?? '' ) ) ) {
					return $this->error( 'invalid_grant', 'The authorization code is invalid, expired, or already used.', 400, false, 'UA-MCP-507' );
				}
				$issued_at = is_numeric( $record['issued_at'] ?? null ) ? (float) $record['issued_at'] : null;
				if ( $this->revocation_marker->covers( (int) ( $record['user_id'] ?? 0 ), $issued_at ) ) {
					return $this->error( 'invalid_grant', 'The authorization code is invalid, expired, or already used.', 400, false, 'UA-MCP-507' );
				}
				// WordPress reports no event when a capability is removed directly, so
				// nothing has revoked this code; a token for this user would be useless.
				$approver = get_userdata( (int) ( $record['user_id'] ?? 0 ) );
				if ( $approver instanceof \WP_User && ! user_can( $approver, 'manage_options' ) ) {
					return $this->error( 'invalid_grant', 'The authorization code is invalid, expired, or already used.', 400, false, 'UA-MCP-507' );
				}

				$scopes   = isset( $record['scopes'] ) && is_array( $record['scopes'] ) ? $record['scopes'] : array();
				$response = $this->issue_credentials( (int) ( $record['user_id'] ?? 0 ), $scopes, $client, (float) $issued_at );
				if ( 200 === $response->get_status() ) {
					OAuth_Telemetry::record( 'authorization_code_exchanged', 'success', (string) $client['client_id'], (int) $record['user_id'] );
				}
				return $response;
			}
		);
		return $result ?? $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::CLIENT_BUSY ) );
	}

	/**
	 * Rotate one refresh token; reuse revokes the whole family.
	 */
	private function exchange_refresh_token( WP_REST_Request $request ): WP_REST_Response {
		$token   = $this->form_param( $request, 'refresh_token' );
		$preview = '' !== $token ? $this->refresh_tokens->inspect( $token ) : null;
		if ( ! is_array( $preview ) ) {
			return $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::REFRESH_INVALID ) );
		}

		$client = $this->authenticate_client( $request, (string) ( $preview['client_id'] ?? '' ) );
		if ( ! is_array( $client ) ) {
			return $client;
		}
		$result = $this->store->with_lock(
			'client',
			(string) $preview['client_id'],
			function () use ( $request, $preview, $token, $client ): WP_REST_Response {
				if ( ! $this->clients->is_authenticated_client_current( $client ) ) {
					return $this->error( 'invalid_client', 'Client authentication failed.', 401, true, 'UA-MCP-520' );
				}

				if ( ! hash_equals( $this->configuration->get_resource(), $this->requested_resource( $request ) ) ) {
					return $this->error( 'invalid_target', 'The resource parameter must name this MCP endpoint.', 400, false, 'UA-MCP-509' );
				}
				if ( ! get_userdata( (int) $preview['user_id'] ) instanceof \WP_User ) {
					$this->refresh_tokens->revoke_family( (string) $preview['family_id'], (int) $preview['user_id'] );
					return $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::USER_MISSING ) );
				}

				// RFC 6749 section 6: a narrower scope applies to this access token
				// only. The refresh family keeps the scopes originally granted.
				$current_scopes = isset( $preview['scopes'] ) && is_array( $preview['scopes'] ) ? $preview['scopes'] : array();
				$requested      = trim( $this->form_param( $request, 'scope' ) );
				try {
					$scopes = '' === $requested ? $current_scopes : $this->scope_policy->parse( $requested, Hosted_Agent_Access::allows_client( $client ) );
					Hosted_Agent_Access::require_client( $scopes, $client );
				} catch ( \InvalidArgumentException $error ) {
					return $this->error( 'invalid_scope', $error->getMessage(), 400, false, 'UA-MCP-523' );
				}
				if ( array_diff( $scopes, $current_scopes ) ) {
					return $this->error( 'invalid_scope', 'A refresh request cannot increase its permissions.', 400, false, 'UA-MCP-510' );
				}

				$rotated = $this->refresh_tokens->rotate(
					$token,
					(string) $client['client_id'],
					$scopes,
					function ( array $generation ) use ( $scopes, $client ): ?array {
						$access = $this->issue_access_token(
							(int) $generation['user_id'],
							$scopes,
							$client,
							(string) $generation['family_id'],
							(int) $generation['generation'],
							(float) $generation['issued_at']
						);

						if ( is_array( $access ) ) {
							$this->token_manager->revoke_oauth_family_generations_before(
								(int) $generation['user_id'],
								(string) $generation['family_id'],
								(int) $generation['generation']
							);
						}

						return $access;
					}
				);

				$failure = Token_Exchange_Policy::rotation_failure(
					null === $rotated ? null : is_array( $rotated ),
					is_array( $rotated ) && is_array( $rotated['access'] ?? null )
				);
				if ( null !== $failure ) {
					return $this->failure_response( $failure );
				}

				$access = $rotated['access'];

				$access['refresh_token']            = (string) $rotated['token'];
				$access['refresh_token_expires_in'] = (int) $rotated['expires_in'];
				OAuth_Telemetry::record( 'refresh_token_rotated', 'success', (string) $client['client_id'], (int) $rotated['user_id'] );
				return $this->success( $access );
			}
		);
		return $result ?? $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::CLIENT_BUSY ) );
	}

	/**
	 * Issue an access token and, only when approved, a refresh token.
	 *
	 * @param float $grant_issued_at When the authorization code was issued; carried
	 *                               by the family and its access tokens so a later
	 *                               revocation marker covers them.
	 */
	private function issue_credentials( int $user_id, array $scopes, array $client, float $grant_issued_at ): WP_REST_Response {
		try {
			Hosted_Agent_Access::require_client( $scopes, $client );
		} catch ( \InvalidArgumentException $error ) {
			return $this->error( 'invalid_scope', $error->getMessage(), 400, false, 'UA-MCP-531' );
		}
		// Recheck at code exchange so an old approval cannot connect an unlicensed site.
		// RFC 6749 section 5.2: an approval that no longer holds is invalid_grant.
		$license_error = Hosted_Agent_Access::site_license_error( $scopes );
		if ( null !== $license_error ) {
			return $this->error( 'invalid_grant', $license_error->get_error_message(), 400, false, 'UA-MCP-532' );
		}
		if ( ! get_userdata( $user_id ) instanceof \WP_User ) {
			return $this->failure_response( Token_Exchange_Policy::failure( Token_Exchange_Policy::USER_MISSING ) );
		}
		$refresh = null;
		if ( in_array( Authenticated_Token_Context::SCOPE_OFFLINE_ACCESS, $scopes, true ) ) {
			$refresh = $this->refresh_tokens->issue( $user_id, $scopes, $client, $grant_issued_at );
			if ( ! is_array( $refresh ) ) {
				$message = ( new Internal_Token_Configuration() )->is_ready()
					? 'The refresh token could not be issued.'
					: 'This site needs unique security keys in wp-config.php before AI apps can stay connected.';
				return $this->error( 'server_error', $message, 500, false, 'UA-MCP-514' );
			}
		}

		$access = $this->issue_access_token(
			$user_id,
			$scopes,
			$client,
			is_array( $refresh ) ? (string) $refresh['family_id'] : '',
			0,
			$grant_issued_at
		);
		if ( ! is_array( $access ) ) {
			if ( is_array( $refresh ) ) {
				$this->refresh_tokens->revoke_family( (string) $refresh['family_id'], $user_id );
			}
			return $this->error( 'server_error', 'The access token could not be issued.', 500, false, 'UA-MCP-515' );
		}

		if ( is_array( $refresh ) ) {
			$access['refresh_token']            = (string) $refresh['token'];
			$access['refresh_token_expires_in'] = (int) $refresh['expires_in'];
		}

		return $this->success( $access );
	}

	/**
	 * Create the access token for the scopes explicitly approved by the user.
	 */
	private function issue_access_token( int $user_id, array $scopes, array $client, string $family_id, int $generation, float $grant_issued_at ): ?array {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return null;
		}

		$token = $this->token_manager->generate_token(
			$user_id,
			$scopes,
			self::ACCESS_TOKEN_LIFETIME,
			'MCP OAuth: ' . (string) $client['name'],
			array(
				'purpose'            => Hosted_Agent_Access::requested( $scopes )
					? Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT
					: Authenticated_Token_Context::PURPOSE_OAUTH_MCP,
				'resource'           => $this->configuration->get_resource(),
				'client_id'          => (string) $client['client_id'],
				'refresh_family'     => $family_id,
				'refresh_generation' => $generation,
				'grant_issued_at'    => $grant_issued_at,
			)
		);
		if ( ! is_array( $token ) ) {
			return null;
		}

		$external = null;
		if ( Hosted_Agent_Access::requested( $scopes ) ) {
			// One hosted approval supplies both consumers. The companion bearer
			// remains oauth_mcp, so existing tool switches and REST restrictions
			// still apply. Both rotate and revoke with the same refresh family.
			$external = $this->token_manager->generate_token(
				$user_id,
				array_values( array_intersect( $scopes, array( 'mcp:read', 'mcp:write' ) ) ),
				self::ACCESS_TOKEN_LIFETIME,
				'MCP OAuth: ' . (string) $client['name'],
				array(
					'purpose'            => Authenticated_Token_Context::PURPOSE_OAUTH_MCP,
					'resource'           => $this->configuration->get_resource(),
					'client_id'          => (string) $client['client_id'],
					'refresh_family'     => $family_id,
					'refresh_generation' => $generation,
					'grant_issued_at'    => $grant_issued_at,
				)
			);
			if ( ! is_array( $external ) ) {
				$this->token_manager->revoke_token( (string) $token['token'] );
				return null;
			}
		}

		Mcp_Auth_Log::record(
			'token_exchange',
			'success',
			array(
				'subject' => (string) $client['name'] . ' (' . (string) $client['client_id'] . ')',
				'user_id' => (int) $user_id,
			)
		);

		$response = array(
			'access_token'                 => (string) $token['token'],
			'token_type'                   => 'Bearer',
			'automator_credential_purpose' => Hosted_Agent_Access::requested( $scopes ) ? 'first_party_agent' : 'oauth_mcp',
			'expires_in'                   => self::ACCESS_TOKEN_LIFETIME,
			'scope'                        => $this->scope_policy->serialize( $scopes ),
			'resource'                     => $this->configuration->get_resource(),
			// The code exchange identifies the approving user to the hosted client.
			// Keep this beside the token: browser callback fields are not identity proof.
			'automator_site'               => array(
				'site_name'           => (string) get_bloginfo( 'name', 'raw' ),
				'wordpress_user_id'   => (int) $user->ID,
				'wordpress_user_name' => '' !== $user->display_name ? $user->display_name : $user->user_login,
			),
		);
		if ( is_array( $external ) ) {
			$response['automator_external_access_token'] = (string) $external['token'];
		}
		return $response;
	}

	/**
	 * Resolve and authenticate the exact client bound to an artifact.
	 *
	 * @since 8.0.1 A known client using the wrong method gets UA-MCP-521 or UA-MCP-522, not UA-MCP-520.
	 *
	 * @return array|WP_REST_Response
	 */
	private function authenticate_client( WP_REST_Request $request, string $bound_client_id ) {
		$basic         = $this->parse_basic_credentials( $request );
		$form_id       = $this->form_param( $request, 'client_id' );
		$authorization = $this->read_authorization_header( $request );
		if ( '' !== $authorization && ! is_array( $basic ) ) {
			return $this->error( 'invalid_client', 'Client authentication failed.', 401, true, 'UA-MCP-516' );
		}
		// Several clients append an empty client_secret parameter for public
		// PKCE clients, Grok among them. An empty value is not an attempt to
		// authenticate, so only a non-empty form secret is rejected.
		if ( '' !== trim( (string) $this->form_param( $request, 'client_secret' ) ) ) {
			return $this->error( 'invalid_client', 'Client secrets must use HTTP Basic authentication.', 401, true, 'UA-MCP-517' );
		}
		if ( is_array( $basic ) && '' !== $form_id && ! hash_equals( $basic['client_id'], $form_id ) ) {
			return $this->error( 'invalid_client', 'Client identifiers do not match.', 401, true, 'UA-MCP-518' );
		}
		$provided_id = is_array( $basic ) ? $basic['client_id'] : $form_id;
		$secret      = is_array( $basic ) ? $basic['secret'] : null;
		if ( '' === $bound_client_id || '' === $provided_id || ! hash_equals( $bound_client_id, $provided_id ) ) {
			return $this->error( 'invalid_client', 'Client authentication failed.', 401, true, 'UA-MCP-519' );
		}

		$client = $this->clients->resolve( $provided_id );
		if ( ! is_array( $client ) ) {
			return $this->error( 'invalid_client', 'Client authentication failed.', 401, true, 'UA-MCP-520', 'Client authentication failed: ' . $this->clients->get_last_resolution_failure() . '.' );
		}

		// The method checks run before the secret check so their own codes reach
		// the client. OAuth_Client_Registry::authenticate() refuses both cases as
		// well, and checked first it answered both as UA-MCP-520. A revoked or
		// unknown client was refused above, so these name only a known client's
		// method; nothing passes here without the secret check below.
		if ( 'confidential' === ( $client['type'] ?? '' ) && ! is_array( $basic ) ) {
			return $this->error( 'invalid_client', 'Confidential clients must use HTTP Basic authentication.', 401, true, 'UA-MCP-521' );
		}
		if ( 'confidential' !== ( $client['type'] ?? '' ) && is_array( $basic ) ) {
			// Basic with an EMPTY secret (base64 of "client_id:"), which some
			// PKCE libraries send, is refused here too; only the empty FORM
			// client_secret is tolerated. If support sees UA-MCP-522 from a
			// stock client, this is the line to revisit.
			return $this->error( 'invalid_client', 'Public clients must not send a client secret.', 401, true, 'UA-MCP-522' );
		}

		if ( ! $this->clients->authenticate( $client, $provided_id, $secret ) ) {
			return $this->error( 'invalid_client', 'Client authentication failed.', 401, true, 'UA-MCP-520', 'Client authentication failed: authentication.' );
		}

		return $client;
	}

	/**
	 * Read Basic auth using the same proxy/Apache fallbacks as MCP bearer auth.
	 */
	private function parse_basic_credentials( WP_REST_Request $request ): ?array {
		$authorization = $this->read_authorization_header( $request );
		if ( '' === $authorization ) {
			// Apache with mod_php strips the Authorization header from the
			// environment but still decodes a Basic credential into PHP_AUTH_*,
			// the same fallback WordPress uses for application passwords.
			if (
				isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] )
				&& is_string( $_SERVER['PHP_AUTH_USER'] )
				&& is_string( $_SERVER['PHP_AUTH_PW'] )
				&& '' !== $_SERVER['PHP_AUTH_USER']
			) {
				return array(
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque client credential compared byte-for-byte.
					'client_id' => wp_unslash( $_SERVER['PHP_AUTH_USER'] ),
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque client credential verified against a hash.
					'secret'    => wp_unslash( $_SERVER['PHP_AUTH_PW'] ),
				);
			}

			return null;
		}
		if ( 1 !== preg_match( '/^Basic\s+([A-Za-z0-9+\/=]+)$/iD', $authorization, $matches ) ) {
			return null;
		}

		$decoded = base64_decode( $matches[1], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- OAuth HTTP Basic requires base64.
		if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
			return null;
		}
		list( $client_id, $secret ) = explode( ':', $decoded, 2 );

		return array(
			'client_id' => rawurldecode( $client_id ),
			'secret'    => rawurldecode( $secret ),
		);
	}

	/**
	 * Read Authorization through WordPress, PHP, and common proxy fallbacks.
	 */
	private function read_authorization_header( WP_REST_Request $request ): string {
		$authorization = trim( (string) $request->get_header( 'authorization' ) );
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $server_key ) {
			if ( '' === $authorization && isset( $_SERVER[ $server_key ] ) && is_string( $_SERVER[ $server_key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HTTP Authorization is an opaque credential header.
				$authorization = trim( wp_unslash( $_SERVER[ $server_key ] ) );
			}
		}

		return $authorization;
	}

	/**
	 * Compute the S256 PKCE challenge for a syntactically valid verifier.
	 */
	private function pkce_challenge( string $verifier ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9._~-]{43,128}$/D', $verifier ) ) {
			return '';
		}

		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- PKCE specifies base64url.
	}

	/**
	 * The resource the client named, or this site's MCP endpoint when it named
	 * none (RFC 8707 makes the token request's indicator optional).
	 *
	 * As at the authorization endpoint, only an absent or empty parameter
	 * (RFC 6749 section 3.1) means none. A present value is compared as sent:
	 * a list, a repeat, or text that trims to nothing never names this site.
	 */
	private function requested_resource( WP_REST_Request $request ): string {
		$named  = $this->named_copies( $request, 'resource' );
		$params = $request->get_body_params();
		if ( ! isset( $params['resource'] ) || '' === $params['resource'] ) {
			// Omitted, or sent only empty. An empty copy after one that named
			// something does not take that value back.
			return 0 === $named ? $this->configuration->get_resource() : '';
		}
		if ( $named > 1 ) {
			return '';
		}

		return is_string( $params['resource'] ) ? untrailingslashit( $params['resource'] ) : '';
	}

	/**
	 * How many copies of a form field name something. PHP keeps only the last
	 * of a repeated field, so count the raw body; RFC 6749 section 3.1 says a
	 * parameter must not be sent more than once, and that an empty one counts
	 * as omitted.
	 *
	 * PHP splits a form body on "&" and then normalizes each name ("+name",
	 * "%20name", "%72ame" and "name[]" all become name). Parse one field at a
	 * time with PHP's own parser so the count agrees with the $_POST it built.
	 * The endpoint has already refused a body too large to parse here.
	 */
	private function named_copies( WP_REST_Request $request, string $name ): int {
		$count = 0;
		foreach ( explode( '&', (string) $request->get_body() ) as $pair ) {
			parse_str( $pair, $field );
			if ( array_key_exists( $name, $field ) && '' !== $field[ $name ] ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Read token parameters only from the form body, never the query string.
	 */
	private function form_param( WP_REST_Request $request, string $name ): string {
		$params = $request->get_body_params();

		return isset( $params[ $name ] ) && is_scalar( $params[ $name ] ) ? (string) $params[ $name ] : '';
	}

	/**
	 * Build a successful no-store token response.
	 */
	private function success( array $body ): WP_REST_Response {
		return new WP_REST_Response(
			$body,
			200,
			array(
				'Cache-Control' => 'no-store',
				'Pragma'        => 'no-cache',
			)
		);
	}

	/** Map a domain failure through the shared logging and no-cache response adapter. */
	private function failure_response( Token_Exchange_Failure $failure, string $description = '' ): WP_REST_Response {
		return $this->error(
			$failure->get_error(),
			'' !== $description ? $description : $failure->get_description(),
			$failure->get_status(),
			false,
			$failure->get_ref(),
			'',
			$failure->get_headers()
		);
	}

	/**
	 * Build a standards-shaped OAuth error without exposing secrets.
	 *
	 * The UA-MCP ref rides the client-visible error_description on purpose:
	 * unlike bearer authentication, a token-endpoint protocol error is not a
	 * credential oracle, and a visible ref is what lets a customer quote the
	 * failure to support without touching the log. Do not "fix" this to
	 * match the bearer path, which strips the ref from responses.
	 */
	private function error( string $error, string $description, int $status, bool $basic_challenge = false, string $ref = '', string $log_detail = '', array $extra_headers = array() ): WP_REST_Response {
		Mcp_Auth_Log::record_unauthenticated_failure(
			'token_exchange',
			$error,
			array(
				'detail' => '' !== $log_detail ? $log_detail : $description,
				'ref'    => $ref,
			)
		);
		if ( '' !== $ref ) {
			$description .= ' (' . $ref . ')';
		}

		$headers = array_merge(
			array(
				'Cache-Control' => 'no-store',
				'Pragma'        => 'no-cache',
			),
			$extra_headers
		);
		if ( $basic_challenge ) {
			$headers['WWW-Authenticate'] = 'Basic realm="Uncanny Automator MCP OAuth"';
		}

		return new WP_REST_Response(
			array(
				'error'             => $error,
				'error_description' => $description,
			),
			$status,
			$headers
		);
	}
}
