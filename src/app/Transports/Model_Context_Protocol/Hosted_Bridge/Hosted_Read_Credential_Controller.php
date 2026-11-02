<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Resource_Identifier;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Rest_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Routes the hosted Uncanny Automator service uses to deliver, confirm and
 * remove this site's read credential.
 *
 * Only the hosted service's own WordPress sign-in may call them. Purpose alone
 * is not enough, because WordPress also mints first-party tokens for Uncanny
 * Agent chat: the token's refresh family must be a live hosted sign-in
 * (Hosted_Sign_In_Liveness) for the token's own client.
 */
final class Hosted_Read_Credential_Controller {

	public const ROUTE = '/mcp/hosted/read-credential';

	public const STATUS_ROUTE = '/mcp/hosted/read-credential/status';

	private const MAX_BODY_BYTES = 1024;

	private Token_Manager $token_manager;

	private Hosted_Read_Credential_Store $store;

	private Hosted_Sign_In_Liveness $liveness;

	/**
	 * The request whose caller was resolved last, so the permission check and the
	 * handler validate the token once. Holding the object itself, not its ID,
	 * means a later request can never match a stale entry.
	 */
	private ?WP_REST_Request $resolved_request = null;

	/** @var array{user_id:int,family_id:string}|WP_Error|null */
	private $resolved_caller = null;

	/**
	 * Constructor.
	 */
	public function __construct(
		?Token_Manager $token_manager = null,
		?Hosted_Read_Credential_Store $store = null,
		?Hosted_Sign_In_Liveness $liveness = null
	) {
		$this->token_manager = $token_manager ?? new Token_Manager();
		$this->store         = $store ?? new Hosted_Read_Credential_Store();
		$this->liveness      = $liveness ?? new Hosted_Sign_In_Liveness();
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Mcp_Rest_Controller::ROUTE_NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
		register_rest_route(
			Mcp_Rest_Controller::ROUTE_NAMESPACE,
			self::STATUS_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Allow only the hosted service's own sign-in.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		$caller = $this->resolve_caller( $request );

		return is_array( $caller ) ? true : $caller;
	}

	/**
	 * Save a delivered credential.
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {
		$caller = $this->resolve_caller( $request );
		if ( ! is_array( $caller ) ) {
			return $this->refusal( $caller );
		}

		$delivery = $this->parse_delivery( $request );
		if ( null === $delivery ) {
			return $this->reply( array( 'code' => 'read_credential_invalid' ), 400 );
		}

		$liveness = $this->liveness;
		$result   = $this->store->save(
			$caller['user_id'],
			$caller['family_id'],
			$delivery['client_id'],
			$delivery['version'],
			$delivery['client_secret'],
			static function () use ( $liveness, $caller ): bool {
				return $liveness->is_live( $caller['user_id'], $caller['family_id'] );
			}
		);
		switch ( $result['status'] ) {
			case Hosted_Read_Credential_Store::SAVED:
			case Hosted_Read_Credential_Store::UNCHANGED:
				return $this->reply( array( 'stored_version' => $result['stored_version'] ), 200 );
			case Hosted_Read_Credential_Store::CONFLICT:
				return $this->reply(
					array(
						'code'           => 'read_credential_conflict',
						'stored_version' => $result['stored_version'],
					),
					409
				);
			case Hosted_Read_Credential_Store::UNAVAILABLE:
				return $this->reply( array( 'code' => 'read_credential_storage_unavailable' ), 503 );
			case Hosted_Read_Credential_Store::REVOKED:
				return $this->reply( array( 'code' => 'read_credential_forbidden' ), 403 );
			default:
				return $this->reply( array( 'code' => 'read_credential_busy' ), 503 );
		}
	}

	/**
	 * Confirm the sign-in is live and report the stored version.
	 */
	public function status( WP_REST_Request $request ): WP_REST_Response {
		$caller = $this->resolve_caller( $request );
		if ( ! is_array( $caller ) ) {
			return $this->refusal( $caller );
		}

		return $this->reply( array( 'stored_version' => $this->store->stored_version( $caller['user_id'], $caller['family_id'] ) ), 200 );
	}

	/**
	 * Remove the caller's credential.
	 */
	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$caller = $this->resolve_caller( $request );
		if ( ! is_array( $caller ) ) {
			return $this->refusal( $caller );
		}

		$deleted = $this->store->delete( $caller['user_id'], $caller['family_id'] );

		return null === $deleted
			? $this->reply( array( 'code' => 'read_credential_busy' ), 503 )
			: $this->reply( array( 'deleted' => $deleted ), 200 );
	}

	/**
	 * Identify the hosted sign-in behind the request, or refuse it.
	 *
	 * @return array{user_id:int,family_id:string}|WP_Error
	 */
	private function resolve_caller( WP_REST_Request $request ) {
		if ( $request !== $this->resolved_request ) {
			$this->resolved_request = $request;
			$this->resolved_caller  = $this->identify_caller( $request );
		}

		return $this->resolved_caller;
	}

	/**
	 * @return array{user_id:int,family_id:string}|WP_Error
	 */
	private function identify_caller( WP_REST_Request $request ) {
		// The hosted service only reaches sites whose MCP address is HTTPS; is_ssl()
		// is not used because it is wrong behind a TLS-terminating proxy.
		if ( 0 !== strpos( ( new Mcp_Resource_Identifier() )->get(), 'https://' ) ) {
			return $this->forbidden( 403 );
		}

		$token = $this->extract_bearer_token( $request );
		$data  = null === $token ? false : $this->token_manager->validate_token( $token );
		if ( ! is_array( $data ) ) {
			return $this->forbidden( 401 );
		}

		$user_id   = (int) ( $data['user_id'] ?? 0 );
		$family_id = (string) ( $data['refresh_family'] ?? '' );
		$scopes    = is_array( $data['scopes'] ?? null ) ? $data['scopes'] : array();
		$required  = 'GET' === strtoupper( $request->get_method() ) ? Authenticated_Token_Context::SCOPE_AGENT_READ : Authenticated_Token_Context::SCOPE_AGENT_WRITE;

		if (
			Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT !== ( $data['purpose'] ?? null )
			|| ! in_array( $required, $scopes, true )
			|| ! $this->liveness->is_live( $user_id, $family_id, (string) ( $data['client_id'] ?? '' ) )
		) {
			return $this->forbidden( 403 );
		}

		return array(
			'user_id'   => $user_id,
			'family_id' => $family_id,
		);
	}

	/**
	 * Parse a delivery body with exactly the agreed keys.
	 *
	 * @return array{version:int,client_id:string,client_secret:string}|null
	 */
	private function parse_delivery( WP_REST_Request $request ): ?array {
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY_BYTES ) {
			return null;
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return null;
		}

		$keys = array_keys( $body );
		sort( $keys );
		if (
			array( 'client_id', 'client_secret', 'version' ) !== $keys
			|| ! is_int( $body['version'] ) || $body['version'] < 1
			|| ! is_string( $body['client_id'] ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{22,64}$/D', $body['client_id'] )
			|| ! is_string( $body['client_secret'] ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $body['client_secret'] )
		) {
			return null;
		}

		return array(
			'version'       => $body['version'],
			'client_id'     => $body['client_id'],
			'client_secret' => $body['client_secret'],
		);
	}

	/**
	 * The bearer token from Authorization, or from X-Automator-Creds when a server strips it.
	 */
	private function extract_bearer_token( WP_REST_Request $request ): ?string {
		$header = trim( (string) $request->get_header( 'authorization' ) );

		// Some Apache configurations remove Authorization. The hosted service sends the same bearer in this header.
		if ( false === stripos( $header, 'bearer' ) ) {
			$header = trim( (string) $request->get_header( 'x-automator-creds' ) );
		}

		return 1 === preg_match( '/^Bearer\s+([^\s]+)$/i', $header, $matches ) ? (string) $matches[1] : null;
	}

	/**
	 * A refusal without detail, so a caller learns nothing about which check failed.
	 */
	private function forbidden( int $status ): WP_Error {
		return new WP_Error( 'read_credential_forbidden', 'Forbidden.', array( 'status' => $status ) );
	}

	/**
	 * Turn a refusal into a reply.
	 */
	private function refusal( WP_Error $error ): WP_REST_Response {
		$data = $error->get_error_data();

		return $this->reply( array( 'code' => 'read_credential_forbidden' ), (int) ( $data['status'] ?? 403 ) );
	}

	/**
	 * A reply that intermediaries must not store.
	 */
	private function reply( array $data, int $status ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
