<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Auth_Log;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use WP_REST_Request;
use WP_REST_Response;

/**
 * RFC 7591 dynamic client registration, the deprecated-but-universal path.
 *
 * MCP clients try CIMD first when advertised, then this endpoint, and only
 * prompt the user for credentials when neither exists. Serving it means most
 * clients never show anyone a Client ID form.
 *
 * Only public PKCE clients are registered: no secret is ever issued to an
 * anonymous caller, consent still requires an administrator in a browser,
 * and every token stays scope- and resource-bound. Unsupported requested
 * metadata is replaced rather than rejected (RFC 7591 section 3.2.1); the
 * response names what was registered.
 */
final class OAuth_Registration_Controller {

	private OAuth_Server_Configuration $configuration;

	private OAuth_Client_Registry $clients;

	private OAuth_Rate_Limiter $rate_limiter;

	private OAuth_Artifact_Store $store;

	private Token_Manager $token_manager;

	/**
	 * Constructor.
	 */
	public function __construct(
		?OAuth_Server_Configuration $configuration = null,
		?OAuth_Client_Registry $clients = null,
		?OAuth_Rate_Limiter $rate_limiter = null,
		?OAuth_Artifact_Store $store = null,
		?Token_Manager $token_manager = null
	) {
		$this->configuration = $configuration ?? new OAuth_Server_Configuration();
		$this->clients       = $clients ?? new OAuth_Client_Registry();
		$this->rate_limiter  = $rate_limiter ?? new OAuth_Rate_Limiter();
		$this->store         = $store ?? new OAuth_Artifact_Store();
		$this->token_manager = $token_manager ?? new Token_Manager();
	}

	/**
	 * Register the registration endpoint.
	 */
	public function register_routes(): void {
		register_rest_route(
			'automator/v1',
			'/oauth/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'register_client' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle one anonymous registration request.
	 */
	public function register_client( WP_REST_Request $request ): WP_REST_Response {
		OAuth_Cache_Policy::no_cache();
		if ( ! $this->configuration->can_authorize() ) {
			$reason = $this->configuration->get_unavailable_description();
			return $this->error( 'temporarily_unavailable', 'Client registration is not available on this site. ' . $reason, 503, 'UA-MCP-601' );
		}

		// Hosted clients share an IP. Bound registration bursts without an hour-long wait.
		$allowed = $this->rate_limiter->allows( 'register', '', 10, MINUTE_IN_SECONDS );
		if ( null === $allowed ) {
			return $this->error( 'temporarily_unavailable', OAuth_Rate_Limiter::STORAGE_FAILURE_MESSAGE, 503, 'UA-MCP-612' );
		}
		if ( false === $allowed ) {
			$response = $this->error( 'temporarily_unavailable', 'Too many registration requests. Try again in one minute.', 429, 'UA-MCP-602' );
			// A full window is a safe wait even when part of the current window has passed.
			$response->header( 'Retry-After', (string) MINUTE_IN_SECONDS );

			return $response;
		}

		$pending_cleanup = new OAuth_Pending_Registration_Cleanup( $this->clients, $this->store, $this->token_manager );
		// Recover abandoned attempts even without WP-Cron; leave approved-grant scans to maintenance.
		$pending_cleanup->run( 1, false );
		$cap = $this->dynamic_client_cap();
		if ( $this->active_dynamic_count() >= $cap ) {
			// Older records do not say whether they were previously approved.
			// Retain their existing cap-only cleanup behavior.
			$this->clients->prune_pending_dynamic_clients( $cap );
		}
		if ( $this->active_dynamic_count() >= $cap ) {
			$this->prune_unused_approved_clients( $cap );
		}
		if ( $this->active_dynamic_count() >= $cap ) {
			return $this->error(
				'temporarily_unavailable',
				sprintf( 'This site has reached its limit for app registrations (%d), including unfinished connection attempts. Try again after 30 minutes. If it still cannot connect, ask the site administrator to open Automator > Settings > MCP settings > Connections and disconnect apps that are no longer used.', $cap ),
				503,
				'UA-MCP-603'
			);
		}

		$metadata = $request->get_json_params();
		if ( ! is_array( $metadata ) ) {
			return $this->error( 'invalid_client_metadata', 'The registration request must be a JSON object.', 400, 'UA-MCP-604' );
		}

		// Any requested authentication method registers a public PKCE client;
		// the response's token_endpoint_auth_method "none" tells the client.
		// Keep only the supported grant types, defaulting to authorization_code.
		$grant_types = isset( $metadata['grant_types'] ) && is_array( $metadata['grant_types'] )
			? array_values( array_intersect( array( 'authorization_code', 'refresh_token' ), array_map( 'strval', array_filter( $metadata['grant_types'], 'is_scalar' ) ) ) )
			: array();
		if ( ! in_array( 'authorization_code', $grant_types, true ) ) {
			array_unshift( $grant_types, 'authorization_code' );
		}

		$redirect_uris = isset( $metadata['redirect_uris'] ) && is_array( $metadata['redirect_uris'] )
			? array_values( array_map( 'strval', $metadata['redirect_uris'] ) )
			: array();
		if ( array() === $redirect_uris ) {
			return $this->error( 'invalid_redirect_uri', 'At least one redirect_uri is required.', 400, 'UA-MCP-608' );
		}
		foreach ( $redirect_uris as $uri ) {
			if ( ! $this->clients->is_acceptable_redirect( $uri ) ) {
				return $this->error( 'invalid_redirect_uri', 'Every redirect_uri must be an https URL, or an http URL on this device.', 400, 'UA-MCP-609' );
			}
		}
		if ( ! OAuth_Client_Registry::dcr_redirect_uris_within_limits( $redirect_uris ) ) {
			return $this->error(
				'invalid_redirect_uri',
				sprintf(
					'Dynamic registrations may list at most %d redirect URIs of up to %d bytes each.',
					OAuth_Client_Registry::DCR_MAX_REDIRECT_URIS,
					OAuth_Client_Registry::DCR_MAX_REDIRECT_URI_LENGTH
				),
				400,
				'UA-MCP-611'
			);
		}

		$client_name = isset( $metadata['client_name'] ) && is_string( $metadata['client_name'] )
			? sanitize_text_field( $metadata['client_name'] )
			: '';
		$name_length = function_exists( 'mb_strlen' ) ? mb_strlen( $client_name, 'UTF-8' ) : strlen( $client_name );
		if ( '' === $client_name || $name_length > 80 ) {
			// RFC 7591 would allow rejecting here; substituting a neutral
			// name instead keeps an otherwise valid registration alive when
			// only the label is mangled.
			$client_name = 'MCP client';
		}

		$contacts = isset( $metadata['contacts'] ) && is_array( $metadata['contacts'] ) ? array_values( $metadata['contacts'] ) : array();

		$client = $this->clients->register( $client_name, 'public', $redirect_uris, 'dcr', $contacts );
		if ( ! is_array( $client ) ) {
			// Redirects were validated above, so a false here is the option
			// write failing, not a problem with the request.
			return $this->error( 'server_error', 'The registration could not be stored. Try again.', 500, 'UA-MCP-610' );
		}
		$pending_cleanup->schedule( (int) $client['pending_expires_at'] );

		OAuth_Telemetry::record( 'client_registered', 'dcr', (string) $client['client_id'] );

		return new WP_REST_Response(
			array(
				'client_id'                  => (string) $client['client_id'],
				'client_id_issued_at'        => (int) $client['created_at'],
				'client_name'                => (string) $client['name'],
				'redirect_uris'              => (array) $client['redirect_uris'],
				'token_endpoint_auth_method' => 'none',
				'grant_types'                => $grant_types,
				'response_types'             => array( 'code' ),
			),
			201,
			array(
				'Content-Type'           => 'application/json',
				'Cache-Control'          => 'no-store',
				'X-Content-Type-Options' => 'nosniff',
			)
		);
	}

	/**
	 * Retire approved dynamic clients that no longer hold any grant, oldest
	 * first, until a registration fits under the cap.
	 *
	 * A quick scan finds candidates; each is rechecked and retired under its
	 * client lifecycle lock, the same check a client's own retirement uses, so
	 * a consent, code or token issued meanwhile keeps the client.
	 */
	private function prune_unused_approved_clients( int $cap ): void {
		$approved = array();
		foreach ( $this->clients->list_clients() as $client ) {
			if ( 'dcr' === ( $client['source'] ?? '' ) && ! empty( $client['active'] ) && ! empty( $client['approved_at'] ) ) {
				$approved[ (string) $client['client_id'] ] = (int) $client['approved_at'];
			}
		}
		$families = $this->store->get_active_client_users( array_keys( $approved ) );
		$tokens   = $this->token_manager->get_active_client_users( array_keys( $approved ) );
		if ( null === $families || null === $tokens ) {
			return;
		}
		asort( $approved, SORT_NUMERIC );
		$excess = $this->active_dynamic_count() - $cap + 1;
		foreach ( array_keys( $approved ) as $client_id ) {
			if ( $excess <= 0 ) {
				return;
			}
			if ( array() !== $families[ $client_id ] || array() !== $tokens[ $client_id ] ) {
				continue;
			}
			$retired = $this->store->with_lock(
				'client',
				$client_id,
				function () use ( $client_id ): bool {
					return false === $this->store->has_live_client_artifacts( $client_id )
						&& false === $this->token_manager->has_live_client_tokens( $client_id )
						&& $this->clients->revoke( $client_id )
						&& $this->clients->delete( $client_id );
				}
			);
			if ( true === $retired ) {
				OAuth_Telemetry::record( 'client_revoked', 'unused_at_registration_cap', $client_id );
				--$excess;
			}
		}
	}

	/**
	 * How many dynamically registered clients are still active.
	 */
	private function active_dynamic_count(): int {
		$count = 0;
		foreach ( $this->clients->list_clients() as $client ) {
			if ( 'dcr' === ( $client['source'] ?? '' ) && ! empty( $client['active'] ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Ceiling on stored dynamic registrations, an anti-spam backstop.
	 */
	private function dynamic_client_cap(): int {
		/**
		 * Filter the maximum number of active dynamically registered clients.
		 *
		 * @param int $cap Default 100.
		 */
		return max( 1, (int) apply_filters( 'automator_mcp_dcr_client_cap', 100 ) );
	}

	/**
	 * RFC 7591 error response.
	 */
	private function error( string $code, string $description, int $status, string $ref = '' ): WP_REST_Response {
		Mcp_Auth_Log::record_unauthenticated_failure(
			'client_registration',
			$code,
			array(
				'detail' => $description,
				'ref'    => $ref,
			)
		);
		if ( '' !== $ref ) {
			$description .= ' (' . $ref . ')';
		}

		return new WP_REST_Response(
			array(
				'error'             => $code,
				'error_description' => $description,
			),
			$status,
			array( 'Content-Type' => 'application/json' )
		);
	}
}
