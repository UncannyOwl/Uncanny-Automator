<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Context_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Client_Registry;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Server_Configuration;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Site_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Authorization_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Header_Map;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Media_Upload_Transfer;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Rate_Limiter;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Stateless MCP 2026-07-28 Streamable HTTP endpoint.
 */
final class Mcp_Rest_Controller extends WP_REST_Controller {

	public const ROUTE_NAMESPACE = 'automator/v1';

	public const ROUTE_BASE = 'mcp';

	protected $namespace = self::ROUTE_NAMESPACE;

	protected $rest_base = self::ROUTE_BASE;

	private Message_Router $message_router;

	private Legacy_Protocol_Adapter $legacy_adapter;

	private Mcp_Protocol_Request_Classifier $protocol_classifier;

	private Token_Manager $token_manager;

	private Client_Context_Service $context_service;

	private Mcp_Origin_Policy $origin_policy;

	private OAuth_Server_Configuration $oauth_configuration;

	private OAuth_Client_Registry $oauth_clients;

	/**
	 * Credential purpose proven for the current request.
	 *
	 * @var Authenticated_Token_Context|null
	 */
	private ?Authenticated_Token_Context $authenticated_context = null;

	/**
	 * Build the stateless MCP REST endpoint.
	 */
	public function __construct(
		?Message_Router $message_router = null,
		?Token_Manager $token_manager = null,
		?Client_Context_Service $context_service = null,
		?Mcp_Origin_Policy $origin_policy = null,
		?OAuth_Server_Configuration $oauth_configuration = null,
		?OAuth_Client_Registry $oauth_clients = null,
		?Legacy_Protocol_Adapter $legacy_adapter = null,
		?Mcp_Protocol_Request_Classifier $protocol_classifier = null
	) {
		$this->message_router      = $message_router ?? new Message_Router();
		$this->legacy_adapter      = $legacy_adapter ?? new Legacy_Protocol_Adapter( $this->message_router );
		$this->protocol_classifier = $protocol_classifier ?? new Mcp_Protocol_Request_Classifier();
		$this->token_manager       = $token_manager ?? new Token_Manager();
		$this->context_service     = $context_service ?? new Client_Context_Service();
		$this->origin_policy       = $origin_policy ?? new Mcp_Origin_Policy();
		$this->oauth_configuration = $oauth_configuration ?? new OAuth_Server_Configuration();
		$this->oauth_clients       = $oauth_clients ?? new OAuth_Client_Registry();
	}

	/**
	 * Determine whether a raw URI targets the MCP endpoint or its subroutes.
	 */
	public static function is_mcp_route( string $request_uri ): bool {
		return self::is_namespace_route( $request_uri, self::ROUTE_BASE );
	}

	/**
	 * Determine whether a raw URI targets one automator/v1 route base or its subroutes.
	 *
	 * Only the REST path or the rest_route value counts, on a route boundary, so a
	 * mention elsewhere in the query string or a longer base never matches.
	 */
	public static function is_namespace_route( string $request_uri, string $base ): bool {
		$rest_prefix = trim( (string) rest_get_url_prefix(), '/' );
		$route       = strtolower( self::ROUTE_NAMESPACE . '/' . $base );
		$parts       = wp_parse_url( $request_uri );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$path = isset( $parts['path'] ) && is_string( $parts['path'] ) ? strtolower( $parts['path'] ) : '';
		if ( '' !== $path && '' !== $rest_prefix ) {
			$pattern = '#/' . preg_quote( strtolower( $rest_prefix ), '#' ) . '/' . preg_quote( $route, '#' ) . '(?:/|$)#';
			if ( 1 === preg_match( $pattern, $path ) ) {
				return true;
			}
		}

		$query = isset( $parts['query'] ) && is_string( $parts['query'] ) ? $parts['query'] : '';
		if ( '' === $query ) {
			return false;
		}

		parse_str( $query, $query_params );
		$rest_route = $query_params['rest_route'] ?? '';

		return is_string( $rest_route )
			&& 1 === preg_match( '#^/?' . preg_quote( $route, '#' ) . '(?:/|$)#', strtolower( $rest_route ) );
	}
	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_post_request' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_get_request' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'handle_delete_request' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/media-transfer',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'transfer_media' ),
				'permission_callback' => array( $this, 'check_media_transfer_permissions' ),
				'show_in_index'       => false,
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/settings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'open_settings' ),
				'permission_callback' => '__return_true',
			)
		);

		// Before any other listener reads WordPress's verdict on the body.
		add_filter( 'rest_request_before_callbacks', array( $this, 'answer_invalid_json_as_parse_error' ), 0, 2 );
	}

	/**
	 * Let the endpoint answer a body WordPress could not parse as JSON.
	 *
	 * WordPress decodes a body labelled as JSON before the route runs, and when
	 * it does not decode it answers with its own rest_invalid_json object. The
	 * endpoint decodes the same body with the same json_decode() call and
	 * answers JSON-RPC -32700 Parse error, as it does for any other content
	 * type. So only that one WordPress error, on this controller's POST
	 * handler, is set aside: the endpoint still refuses the body, in the
	 * JSON-RPC envelope its other errors use.
	 *
	 * @since 8.0.1
	 *
	 * @param mixed $response Result so far: null, or the error WordPress found.
	 * @param mixed $handler  Matched route handler.
	 * @return mixed
	 */
	public function answer_invalid_json_as_parse_error( $response, $handler ) {
		if (
			! $response instanceof WP_Error
			|| array( 'rest_invalid_json' ) !== $response->get_error_codes()
			|| ! is_array( $handler )
			|| array( $this, 'handle_post_request' ) !== ( $handler['callback'] ?? null )
		) {
			return $response;
		}

		return null;
	}

	/**
	 * Open this installation's settings without making clients infer wp-admin
	 * from a custom REST prefix. The admin page still checks login and access.
	 */
	public function open_settings(): WP_REST_Response {
		return new WP_REST_Response(
			null,
			302,
			array(
				'Location'      => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=model-context-protocol&model-context-protocol=general', 'https' ),
				'Cache-Control' => 'no-store',
			)
		);
	}

	/** Keep bearer failures uniform while giving the user a recovery action. */
	public function check_media_transfer_permissions( WP_REST_Request $request ) {
		$error = $this->check_permissions( $request );
		if ( ! is_wp_error( $error ) ) {
			return true;
		}
		$status = $error->get_error_data()['status'] ?? 401;
		return Media_Upload_Transfer::error( 403 === $status ? 'upload_write_scope_required' : 'upload_connection_unavailable', $status );
	}

	/** Transfer bytes under the same bearer checks; this does not create media. */
	public function transfer_media( WP_REST_Request $request ) {
		if ( ! $this->origin_policy->allows( (string) $request->get_header( 'origin' ) ) ) {
			return Media_Upload_Transfer::error( 'upload_origin_denied', 403 );
		}
		// Recheck instead of depending on a previous permission callback's state.
		$permission = $this->check_media_transfer_permissions( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$token   = $this->authenticated_context;
		$context = new User_Context( $token->get_user()->ID, null, $token->is_first_party_agent(), $token->has_scope( Authenticated_Token_Context::SCOPE_WRITE ), $token->get_client_id() );
		if ( ! ( new Tool_Rate_Limiter() )->allows( $context->get_executor(), 'media-transfer' ) ) {
			return Media_Upload_Transfer::error( 'upload_rate_limited', 429 );
		}
		$result = ( new Media_Upload_Transfer() )->stage( $context, $request );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 201, array( 'Cache-Control' => 'no-store' ) );
	}

	/** Upper bound on members in one MCP 2025-03-26 batch. */
	private const MAX_BATCH_MEMBERS = 20;

	/**
	 * Process one independent JSON-RPC request, or one batch of them.
	 */
	public function handle_post_request( $request ) {
		if ( ! $this->origin_policy->allows( (string) $request->get_header( 'origin' ) ) ) {
			return $this->error_response( null, -32600, 'Invalid Origin header.', 403 );
		}

		$raw_body = $request instanceof WP_REST_Request ? (string) $request->get_body() : '';
		if ( '' === trim( $raw_body ) || strlen( $raw_body ) > 1048576 ) {
			return $this->error_response( null, -32700, 'Request body must contain one bounded JSON-RPC object.', 400 );
		}

		// A body of any content type gets this answer: for one labelled as JSON,
		// answer_invalid_json_as_parse_error() set WordPress's own error aside.
		$message = json_decode( $raw_body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return $this->error_response( null, -32700, 'Request body contains invalid JSON.', 400 );
		}

		$is_batch = '[' === substr( ltrim( $raw_body ), 0, 1 );
		$id       = ! $is_batch && is_array( $message ) && ( is_string( $message['id'] ?? null ) || is_int( $message['id'] ?? null ) )
			? $message['id']
			: null;

		if ( ! $this->is_valid_accept_header( (string) $request->get_header( 'accept' ) ) ) {
			return $this->error_response( $id, -32602, 'Accept must include application/json and text/event-stream.', 400 );
		}
		$content_type = $request->get_content_type();
		if ( ! is_array( $content_type ) || 'application/json' !== ( $content_type['value'] ?? '' ) ) {
			return $this->error_response( $id, -32600, 'Content-Type must be application/json.', 415 );
		}

		if ( $is_batch ) {
			return $this->handle_batch( $request, is_array( $message ) ? $message : array() );
		}

		if ( ! is_array( $message ) ) {
			return $this->error_response( null, -32600, 'Request body must contain one JSON-RPC object.', 400 );
		}

		return $this->handle_single_message( $request, $message );
	}

	/**
	 * Process a JSON-RPC batch for clients on MCP 2025-03-26.
	 *
	 * That revision is the last one with batching, and the only one without a
	 * protocol version header, so a batch is accepted when the header is absent
	 * or names it. MCP 2025-06-18 removed batches, and a client that sends a
	 * later version with an array body gets the same refusal as before.
	 *
	 * Reject an invalid bearer before dispatching any member. Once dispatch
	 * starts, each result or error belongs to its member, including permission
	 * failures caused by expiry, revocation, or different required scopes.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param array           $members Decoded batch members.
	 * @return WP_REST_Response
	 */
	private function handle_batch( $request, array $members ): WP_REST_Response {
		$version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		if ( '' !== $version && Mcp_Version_Manager::get_legacy_default_version() !== $version ) {
			return $this->error_response( null, -32600, 'JSON-RPC batches were removed in MCP 2025-06-18; send one request per POST.', 400 );
		}

		if ( array() === $members ) {
			return $this->error_response( null, -32600, 'A JSON-RPC batch must contain at least one message.', 400 );
		}

		if ( count( $members ) > self::MAX_BATCH_MEMBERS ) {
			return $this->error_response( null, -32600, sprintf( 'A JSON-RPC batch may contain at most %d messages.', self::MAX_BATCH_MEMBERS ), 400 );
		}

		$permission = $this->authenticate_request( $request );
		if ( is_wp_error( $permission ) ) {
			return $this->permission_error_response( $permission, null, Authenticated_Token_Context::SCOPE_READ );
		}

		$responses = array();
		foreach ( $members as $member ) {
			if ( ! is_array( $member ) ) {
				$responses[] = $this->error_response( null, -32600, 'Each batch member must be a JSON-RPC object.', 400 )->get_data();
				continue;
			}

			$response = $this->handle_single_message( $request, $member );
			if ( ! $response instanceof WP_REST_Response ) {
				continue;
			}

			$status = (int) $response->get_status();
			// A later bearer failure must not turn a completed write into an HTTP
			// auth challenge: clients may refresh and replay the whole POST. Keep
			// per-member errors and prior results, while rechecking every member.
			if ( in_array( $status, array( 401, 403 ), true ) && ! array_key_exists( 'id', $member ) ) {
				continue; // JSON-RPC notifications never receive a member response.
			}

			if ( 202 === $status ) {
				continue;
			}

			$data = $response->get_data();
			if ( is_array( $data ) ) {
				$responses[] = $data;
			}
		}

		if ( array() === $responses ) {
			return new WP_REST_Response( null, 202, array( 'X-Automator-MCP' => '1' ) );
		}

		return new WP_REST_Response(
			$responses,
			200,
			array(
				'Content-Type'    => 'application/json',
				'X-Automator-MCP' => '1',
			)
		);
	}

	/**
	 * Process one decoded JSON-RPC message.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param array           $message Decoded message.
	 * @return WP_REST_Response|Mcp_Sse_Response
	 */
	private function handle_single_message( $request, array $message ) {
		$id = ( is_string( $message['id'] ?? null ) || is_int( $message['id'] ?? null ) ) ? $message['id'] : null;

		if ( isset( $message['result'] ) || isset( $message['error'] ) || ! isset( $message['method'] ) ) {
			return $this->error_response( $id, -32600, 'Clients must send requests, not JSON-RPC responses.', 400 );
		}

		$era = $this->protocol_classifier->classify( $request, $message );
		if ( is_wp_error( $era ) ) {
			return $this->protocol_error_response( $era, $id );
		}

		if ( Mcp_Protocol_Request_Classifier::ERA_MODERN === $era ) {
			$transport_error = $this->validate_transport_metadata(
				$request,
				$message,
				$id,
				array_key_exists( 'id', $message )
			);
			if ( $transport_error instanceof WP_REST_Response ) {
				return $transport_error;
			}

			if ( array_key_exists( 'id', $message ) ) {
				$metadata_error = $this->validate_request_metadata( $message, $id );
				if ( $metadata_error instanceof WP_REST_Response ) {
					return $metadata_error;
				}
			}
		}

		// Authorization and execution must use this exact decoded message. Asking
		// WP_REST_Request to parse the body again creates a second interpretation
		// boundary when a malformed Content-Type is handled differently by core.
		// Prove the bearer before a tools/call can reach extension discovery. Core
		// tool scopes come from an immutable name map; unknown names fail closed to
		// write in the challenge and are resolved from the registry only after this
		// authentication boundary succeeds.
		$required_scope = $this->get_pre_auth_required_scope( $message );
		$permission     = $this->authenticate_request( $request );
		if ( is_wp_error( $permission ) ) {
			return $this->permission_error_response( $permission, $id, $required_scope );
		}

		// Tool-specific routing annotations come from the validated registry. Keep
		// that registry behind bearer authentication just like scope resolution;
		// otherwise an anonymous tools/call can construct every tool and schema.
		if ( Mcp_Protocol_Request_Classifier::ERA_MODERN === $era && 'tools/call' === $message['method'] ) {
			// Notifications do not pass request-metadata validation above. Keep this
			// boundary defensive so a malformed notification reaches the router's
			// normal invalid-params response without raising a PHP warning.
			$tool_name    = isset( $message['params']['name'] ) && is_string( $message['params']['name'] ) ? $message['params']['name'] : '';
			$header_error = $this->validate_tool_routing_headers( $request, $message, $tool_name, $id );
			if ( $header_error instanceof WP_REST_Response ) {
				return $header_error;
			}
		}

		$required_scope = $this->get_required_scope( $message );
		$permission     = $this->authorize_authenticated_scope( $required_scope );
		if ( is_wp_error( $permission ) ) {
			return $this->permission_error_response( $permission, $id, $required_scope );
		}

		$response = Mcp_Protocol_Request_Classifier::ERA_LEGACY === $era
			? $this->legacy_adapter->route_message( $message, $request, $this->authenticated_context )
			: $this->message_router->route_message( $message, $request, $this->authenticated_context );
		if ( $response instanceof WP_Error ) {
			$data   = $response->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			return $this->error_response( $id, $response->get_error_code(), $response->get_error_message(), $status );
		}

		if ( $response instanceof Mcp_Sse_Response ) {
			return $response;
		}

		if ( ! array_key_exists( 'id', $message ) ) {
			return new WP_REST_Response( null, 202, array( 'X-Automator-MCP' => '1' ) );
		}

		// MCP 2026-07-28 requires HTTP 404 for method-not-found. Older clients
		// keep their HTTP 200 envelope; tool failures remain JSON-RPC results.
		$status = Mcp_Protocol_Request_Classifier::ERA_MODERN === $era && -32601 === ( $response['error']['code'] ?? null ) ? 404 : 200;
		return new WP_REST_Response(
			$response,
			$status,
			array(
				'Content-Type'    => 'application/json',
				'X-Automator-MCP' => '1',
			)
		);
	}
	/**
	 * Handle get request.
	 *
	 * @param mixed $request The request.
	 * @return WP_REST_Response
	 */
	public function handle_get_request( $request ): WP_REST_Response {
		if ( $request instanceof WP_REST_Request && ! $this->origin_policy->allows( (string) $request->get_header( 'origin' ) ) ) {
			return $this->error_response( null, -32600, 'Invalid Origin header.', 403 );
		}
		$response = $this->error_response( null, -32601, 'MCP 2026-07-28 accepts POST only.', 405 );
		$response->header( 'Allow', 'POST' );
		return $response;
	}
	/**
	 * Handle delete request.
	 *
	 * @param mixed $request The request.
	 * @return WP_REST_Response
	 */
	public function handle_delete_request( $request ): WP_REST_Response {
		return $this->handle_get_request( $request );
	}

	/**
	 * Authenticate and authorize an MCP request.
	 *
	 * @param mixed  $request        REST request.
	 * @param string $required_scope Scope derived from the decoded message.
	 * @return true|WP_Error
	 */
	public function check_permissions( $request, string $required_scope = Authenticated_Token_Context::SCOPE_WRITE ) {
		$authenticated = $this->authenticate_request( $request );
		if ( is_wp_error( $authenticated ) ) {
			return $authenticated;
		}

		return $this->authorize_authenticated_scope( $required_scope );
	}

	/**
	 * Authenticate one bearer without performing tool-dependent work.
	 *
	 * @param mixed $request REST request.
	 * @return true|WP_Error
	 */
	private function authenticate_request( $request ) {
		// Never retain a successful credential purpose across REST dispatches in
		// long-running test processes or nested requests.
		$this->authenticated_context = null;

		$authorization_header = $request->get_header( 'authorization' );
		if ( null === $authorization_header ) {
			foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $key ) {
				if ( isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) && '' !== $_SERVER[ $key ] ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bearer credentials are opaque.
					$authorization_header = wp_unslash( $_SERVER[ $key ] );
					break;
				}
			}
		}
		$authorization = trim( (string) $authorization_header );
		$fallback      = trim( (string) $request->get_header( 'x-automator-creds' ) );
		$used_fallback = false;

		if ( 1 !== preg_match( '/^Bearer\s+(.+)$/i', $authorization, $matches ) ) {
			$used_fallback = true;
			if ( 1 !== preg_match( '/^Bearer\s+(.+)$/i', $fallback, $matches ) ) {
				return new WP_Error(
					'mcp_missing_token',
					'Bearer token required.',
					array(
						'status' => 401,
						'ref'    => 'UA-MCP-201',
					)
				);
			}
		}

		$context = $this->token_manager->get_context_from_token( trim( (string) $matches[1] ) );
		if ( ! $context instanceof Authenticated_Token_Context ) {
			return new WP_Error(
				'mcp_invalid_token',
				'Invalid or expired Bearer token.',
				array(
					'status' => 401,
					'ref'    => 'UA-MCP-202',
				)
			);
		}

		if ( ! $context->is_first_party_agent() && ! $context->is_oauth_mcp() ) {
			return new WP_Error(
				'mcp_invalid_token',
				'Invalid or expired Bearer token.',
				array(
					'status' => 401,
					'ref'    => 'UA-MCP-203',
				)
			);
		}

		// The external token's fallback is restricted to the MCP resource itself.
		// An Authorization header, even an invalid one, remains authoritative.
		if (
			$used_fallback
			&& $context->is_oauth_mcp()
			&& ( null !== $authorization_header || '/automator/v1/mcp' !== untrailingslashit( (string) $request->get_route() ) )
		) {
			return new WP_Error(
				'mcp_invalid_token',
				'Invalid or expired Bearer token.',
				array(
					'status' => 401,
					'ref'    => 'UA-MCP-204',
				)
			);
		}

		if ( $context->is_oauth_mcp() ) {
			// This is reachable only after the credential proved valid:
			// naming the site's OAuth state reveals nothing about the token.
			if ( ! OAuth_Server_Configuration::is_browser_flow_available() || ! $this->oauth_configuration->can_use_existing_credentials() ) {
				$reason = $this->oauth_configuration->get_unavailable_description();
				return new WP_Error(
					'mcp_oauth_unavailable',
					trim( 'OAuth connections cannot be used on this site right now. ' . ( '' !== $reason ? $reason : 'Ask the site administrator to check Uncanny Automator > Settings > MCP.' ) ),
					array(
						'status' => 401,
						'ref'    => 'UA-MCP-207',
					)
				);
			}
			if (
				$this->oauth_clients->is_revoked( $context->get_client_id() )
				|| ! ( new Token_Site_Policy() )->is_resource_for_current_site( $context->get_resource() )
			) {
				return new WP_Error(
					'mcp_invalid_token',
					'Invalid or expired Bearer token.',
					array(
						'status' => 401,
						'ref'    => 'UA-MCP-205',
					)
				);
			}
		}

		$capability = $this->context_service->get_client_access_capability();
		$user       = $context->get_user();
		if ( ! user_can( $user, $capability ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Product capability comes from the first-party context service.
			return new WP_Error(
				'mcp_permission_denied',
				'The connected user no longer has permission to use Uncanny Automator. Ask the site administrator to check this user under WordPress > Users. Refreshing the token will not restore permission.',
				array(
					'status' => 403,
					'ref'    => 'UA-MCP-206',
				)
			);
		}

		$this->authenticated_context = $context;
		return true;
	}

	/**
	 * Enforce a scope after bearer authentication has completed.
	 *
	 * @return true|WP_Error
	 */
	private function authorize_authenticated_scope( string $required_scope ) {
		$context = $this->authenticated_context;
		if ( ! $context instanceof Authenticated_Token_Context ) {
			return new WP_Error(
				'mcp_invalid_token',
				'Invalid or expired Bearer token.',
				array(
					'status' => 401,
					'ref'    => 'UA-MCP-202',
				)
			);
		}

		if ( ! $context->has_scope( $required_scope ) ) {
			return new WP_Error(
				'mcp_insufficient_scope',
				'Bearer token does not grant the required MCP scope.',
				array(
					'status'         => 403,
					'required_scope' => $required_scope,
				)
			);
		}

		$user = $context->get_user();
		wp_set_current_user( $user->ID );
		return true;
	}

	/**
	 * Serve request-scoped SSE responses without WordPress JSON encoding.
	 */
	public function serve_sse_response( $served, $result, $request, $server ): bool {
		if ( ! $result instanceof Mcp_Sse_Response ) {
			return (bool) $served;
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/event-stream' );
			header( 'Cache-Control: no-store' );
			header( 'X-Accel-Buffering: no' );
			header( 'X-Automator-MCP: 1' );
		}

		echo $result->get_sse_payload(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded protocol stream.
		return true;
	}
	/**
	 * Validate transport metadata.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param array $message The message.
	 * @param mixed $id The ID.
	 * @return ?
	 */
	private function validate_transport_metadata( WP_REST_Request $request, array $message, $id, bool $headers_required ): ?WP_REST_Response {
		$version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		$method  = trim( (string) $request->get_header( 'mcp-method' ) );
		if ( $headers_required && ( '' === $version || '' === $method ) ) {
			return $this->error_response( $id, -32020, 'Required MCP transport headers are missing.', 400 );
		}

		if ( '' !== $version && ! Mcp_Version_Manager::is_modern( $version ) ) {
			return $this->error_response(
				$id,
				-32022,
				'Unsupported MCP protocol version.',
				400,
				array(
					'supported' => Mcp_Version_Manager::get_modern_versions(),
					'requested' => $version,
				)
			);
		}

		if ( '' !== $method && ( ! is_string( $message['method'] ) || ! hash_equals( $message['method'], $method ) ) ) {
			return $this->error_response( $id, -32020, 'Mcp-Method does not match the request body.', 400 );
		}

		// The 2026-07-28 transport does not require routing-header presence on
		// notification POSTs. Supplied values were still checked for conflicts.
		if ( ! $headers_required ) {
			return null;
		}

		if ( in_array( $message['method'], array( 'tools/call', 'resources/read', 'prompts/get' ), true ) ) {
			$name       = $this->decode_header_value( (string) $request->get_header( 'mcp-name' ) );
			$source_key = 'resources/read' === $message['method'] ? 'uri' : 'name';
			if ( null === $name || ! isset( $message['params'][ $source_key ] ) || ! is_string( $message['params'][ $source_key ] ) || ! hash_equals( $message['params'][ $source_key ], $name ) ) {
				return $this->error_response( $id, -32020, 'Mcp-Name is missing, malformed, or does not match the request body.', 400 );
			}
		}

		return null;
	}
	/**
	 * Validate request metadata.
	 *
	 * @param array $message The message.
	 * @param mixed $id The ID.
	 * @return ?
	 */
	private function validate_request_metadata( array $message, $id ): ?WP_REST_Response {
		$params = $message['params'] ?? null;
		$meta   = is_array( $params ) ? ( $params['_meta'] ?? null ) : null;
		if ( ! is_array( $meta ) ) {
			return $this->error_response( $id, -32602, 'Every request must include params._meta.', 400 );
		}

		$version      = $meta['io.modelcontextprotocol/protocolVersion'] ?? null;
		$capabilities = $meta['io.modelcontextprotocol/clientCapabilities'] ?? null;
		$client_info  = $meta['io.modelcontextprotocol/clientInfo'] ?? null;
		if (
			! is_string( $version )
			|| ! is_array( $capabilities )
			|| $this->is_list( $capabilities )
			|| ( null !== $client_info && ! $this->is_valid_client_info( $client_info ) )
		) {
			return $this->error_response( $id, -32602, 'Required per-request MCP metadata is missing.', 400 );
		}

		if ( ! hash_equals( Mcp_Version_Manager::get_latest_version(), $version ) ) {
			return $this->error_response( $id, -32020, 'Protocol version header and request metadata do not match.', 400 );
		}

		return null;
	}

	/**
	 * Validate optional modern client implementation metadata.
	 *
	 * @param mixed $client_info Candidate implementation metadata.
	 */
	private function is_valid_client_info( $client_info ): bool {
		return is_array( $client_info )
			&& ! $this->is_list( $client_info )
			&& isset( $client_info['name'], $client_info['version'] )
			&& is_string( $client_info['name'] )
			&& '' !== trim( $client_info['name'] )
			&& is_string( $client_info['version'] )
			&& '' !== trim( $client_info['version'] );
	}
	/**
	 * Get required scope.
	 *
	 * Unknown or malformed tool uses require write access until the router can
	 * reject them. They must never inherit the less-privileged read default.
	 *
	 * @param array $message The already-decoded message that will be executed.
	 * @return string
	 */
	private function get_required_scope( array $message ): string {
		if ( 'tools/call' === ( $message['method'] ?? null ) ) {
			$name       = isset( $message['params']['name'] ) && is_string( $message['params']['name'] ) ? $message['params']['name'] : '';
			$core_scope = Tool_Authorization_Policy::resolve_core_tool_scope( $name );
			if ( null !== $core_scope ) {
				return $core_scope;
			}

			$scope = $this->message_router->get_required_tool_scope( $name );
			return null === $scope ? Authenticated_Token_Context::SCOPE_WRITE : $scope;
		}

		return Authenticated_Token_Context::SCOPE_READ;
	}

	/**
	 * Resolve a safe challenge scope without constructing extension tools.
	 *
	 * Unknown tool names fail closed to write until an authenticated request may
	 * resolve an extension tool from the lazy registry.
	 */
	private function get_pre_auth_required_scope( array $message ): string {
		if ( 'tools/call' !== ( $message['method'] ?? null ) ) {
			return Authenticated_Token_Context::SCOPE_READ;
		}

		$name  = isset( $message['params']['name'] ) && is_string( $message['params']['name'] ) ? $message['params']['name'] : '';
		$scope = Tool_Authorization_Policy::resolve_core_tool_scope( $name );

		return null === $scope ? Authenticated_Token_Context::SCOPE_WRITE : $scope;
	}

	/**
	 * Convert one permission failure into the uniform MCP response and log entry.
	 *
	 * @param WP_Error  $permission    Permission failure.
	 * @param mixed     $id            JSON-RPC request ID.
	 * @param string    $required_scope Scope advertised in the OAuth challenge.
	 */
	private function permission_error_response( WP_Error $permission, $id, string $required_scope ): WP_REST_Response {
		$permission_data = $permission->get_error_data();
		$log_context     = array(
			'detail' => $permission->get_error_message(),
			// The ref never reaches the client: bearer failures answer
			// identically on purpose, so the response is no oracle.
			'ref'    => is_array( $permission_data ) ? (string) ( $permission_data['ref'] ?? '' ) : '',
		);
		if ( in_array( $log_context['ref'], array( 'UA-MCP-201', 'UA-MCP-202' ), true ) ) {
			Mcp_Auth_Log::record_unauthenticated_failure( 'bearer_auth', (string) $permission->get_error_code(), $log_context );
		} else {
			Mcp_Auth_Log::record( 'bearer_auth', (string) $permission->get_error_code(), $log_context );
		}

		$data   = is_array( $permission_data ) ? $permission_data : array();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 401;
		$code   = 403 === $status ? -31903 : -31901;
		unset( $data['ref'] );
		$response = $this->error_response( $id, $code, $permission->get_error_message(), $status, $data );
		$this->add_oauth_challenge( $response, $status, $data, $required_scope, 'UA-MCP-201' !== $log_context['ref'] );

		return $response;
	}
	/**
	 * Error response.
	 *
	 * @param mixed $id The ID.
	 * @param mixed $code The code.
	 * @param string $message The message.
	 * @param int $status The status.
	 * @param array $data The data.
	 * @return WP_REST_Response
	 */
	private function error_response( $id, $code, string $message, int $status, array $data = array() ): WP_REST_Response {
		$envelope = Json_Rpc_Envelope::create_error_response( $id, $code, $message, empty( $data ) ? null : $data );
		return new WP_REST_Response(
			$envelope,
			$status,
			array(
				'Content-Type'    => 'application/json',
				'X-Automator-MCP' => '1',
			)
		);
	}

	/**
	 * Convert a protocol-classification failure to the public JSON-RPC shape.
	 *
	 * @param mixed $id Request identifier.
	 */
	private function protocol_error_response( WP_Error $error, $id ): WP_REST_Response {
		$error_data = $error->get_error_data();
		$ref        = is_array( $error_data ) ? (string) ( $error_data['ref'] ?? '' ) : '';
		Mcp_Auth_Log::record_unauthenticated_failure(
			'handshake',
			(string) $error->get_error_code(),
			array(
				'detail' => $error->get_error_message(),
				'ref'    => $ref,
			)
		);

		$data   = $error->get_error_data();
		$data   = is_array( $data ) ? $data : array();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 400;
		$code   = isset( $data['json_rpc_code'] ) ? (int) $data['json_rpc_code'] : -32022;

		// The ref stays in the client-visible error data on purpose: protocol
		// errors carry no credential signal, and the visible ref is the
		// support handle. Only bearer failures strip it (anti-oracle).
		unset( $data['status'], $data['json_rpc_code'] );

		return $this->error_response( $id, $code, $error->get_error_message(), $status, $data );
	}

	/**
	 * Advertise the exact Automator-owned protected-resource metadata URL.
	 *
	 * @param WP_REST_Response $response        Error response being decorated.
	 * @param int              $status          HTTP status of that response.
	 * @param array            $data            Client-visible error data.
	 * @param string           $required_scope  Scope the request needed.
	 * @param bool             $token_presented Whether a bearer was presented and rejected.
	 */
	private function add_oauth_challenge( WP_REST_Response $response, int $status, array $data, string $required_scope, bool $token_presented = false ): void {
		if ( ! in_array( $status, array( 401, 403 ), true ) ) {
			return;
		}
		// A valid token can lose a WordPress capability. Consent cannot repair
		// that refusal; advertise more scopes only after an actual scope check.
		if ( 403 === $status && empty( $data['required_scope'] ) ) {
			return;
		}

		// Browser connections are intended to expose Automator's complete public
		// tool set. The first 401 therefore requests both scopes plus
		// offline_access: most clients take their scope list from this challenge
		// alone, and without offline_access they receive one-hour tokens and
		// re-open the consent page every hour. A later 403 remains narrow and
		// advertises only the scope required by that request.
		$scope     = 401 === $status
			? Authenticated_Token_Context::SCOPE_READ . ' ' . Authenticated_Token_Context::SCOPE_WRITE . ' ' . Authenticated_Token_Context::SCOPE_OFFLINE_ACCESS
			: ( isset( $data['required_scope'] ) && is_string( $data['required_scope'] )
				? $data['required_scope']
				: $required_scope );
		$challenge = 'Bearer';

		$challenge .= ' scope="' . $scope . '"';
		if ( 403 === $status ) {
			$challenge .= ', error="insufficient_scope"';
		} elseif ( $token_presented ) {
			// RFC 6750 section 3.1: a presented credential that was rejected is
			// announced so clients refresh instead of retrying the same token. The
			// missing-token 401 carries no error parameter.
			$challenge .= ', error="invalid_token"';
		}

		$response->header( 'WWW-Authenticate', $challenge );
	}
	/**
	 * Decode header value.
	 *
	 * @param string $value The value.
	 * @return string|null Decoded header value or null when invalid.
	 */
	private function decode_header_value( string $value ): ?string {
		if ( '' === $value || trim( $value, " \t" ) !== $value ) {
			return null;
		}

		$is_sentinel = 0 === strpos( $value, '=?base64?' ) && '?=' === substr( $value, -2 );
		if ( $is_sentinel ) {
			if ( 1 !== preg_match( '/^=\?base64\?([A-Za-z0-9+\/=]*)\?=$/D', $value, $matches ) ) {
				return null;
			}
			$decoded = base64_decode( $matches[1], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Protocol-defined encoding.
			return false === $decoded || wp_check_invalid_utf8( $decoded, true ) !== $decoded ? null : $decoded;
		}

		if ( 1 === preg_match( '/[^\x20-\x7E\x09]/', $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Validate any x-mcp-header values declared by the selected tool.
	 */
	private function validate_tool_routing_headers( WP_REST_Request $request, array $message, string $tool_name, $id ): ?WP_REST_Response {
		$map       = $this->message_router->get_tool_header_map( $tool_name );
		$arguments = isset( $message['params']['arguments'] ) && is_array( $message['params']['arguments'] )
			? $message['params']['arguments']
			: array();

		foreach ( $map as $header_suffix => $definition ) {
			$present = false;
			$value   = $this->read_argument_path( $arguments, $definition['path'], $present );
			$raw     = (string) $request->get_header( 'mcp-param-' . $header_suffix );

			if ( ! $present || null === $value ) {
				if ( '' !== $raw ) {
					return $this->error_response( $id, -32020, 'An MCP parameter header was supplied without a body value.', 400 );
				}
				continue;
			}

			$decoded = $this->decode_header_value( $raw );
			if ( null === $decoded || ! $this->header_value_matches( $decoded, $value, $definition['type'] ) ) {
				return $this->error_response( $id, -32020, 'An MCP parameter header is missing, malformed, or does not match the request body.', 400 );
			}
		}

		return null;
	}

	/**
	 * Read one statically reachable property path.
	 *
	 * @param string[] $path Property path.
	 * @param bool     $present Whether the full path was present.
	 * @return mixed
	 */
	private function read_argument_path( array $arguments, array $path, bool &$present ) {
		$value = $arguments;
		foreach ( $path as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				$present = false;
				return null;
			}
			$value = $value[ $segment ];
		}

		$present = true;
		return $value;
	}

	/**
	 * Compare a decoded routing header to its primitive body value.
	 *
	 * An integer header must be the integer's canonical decimal text, the way
	 * JSON writes it: digits with no leading zero, a leading "-" only for a
	 * negative value, and zero as "0". Another spelling of the same number
	 * ("42.0", "4.2e1", "+42", "042", "-0") is refused, so a gateway that reads
	 * the header text sees the value the server runs.
	 *
	 * @since 8.0.1 An integer header no longer matches another numeric spelling.
	 *
	 * @param mixed $value Body value.
	 */
	private function header_value_matches( string $decoded, $value, string $type ): bool {
		if ( 'string' === $type ) {
			return is_string( $value ) && hash_equals( $value, $decoded );
		}

		if ( 'boolean' === $type ) {
			return is_bool( $value ) && ( $value ? 'true' : 'false' ) === $decoded;
		}

		if ( ! is_int( $value ) || abs( $value ) > Tool_Header_Map::get_max_safe_integer() ) {
			return false;
		}

		return (string) $value === $decoded;
	}
	/**
	 * Is valid accept header.
	 *
	 * @param string $accept The accept.
	 * @return bool
	 */
	private function is_valid_accept_header( string $accept ): bool {
		$accepted = array();
		foreach ( explode( ',', strtolower( $accept ) ) as $range ) {
			$parts = array_map( 'trim', explode( ';', $range ) );
			$type  = array_shift( $parts );
			$q     = 1.0;
			foreach ( $parts as $parameter ) {
				if ( 1 === preg_match( '/^q\s*=\s*(0(?:\.0*)?|1(?:\.0*)?)$/D', $parameter, $matches ) ) {
					$q = (float) $matches[1];
				}
			}
			if ( is_string( $type ) && '' !== $type && $q > 0 ) {
				$accepted[ $type ] = true;
			}
		}

		return isset( $accepted['application/json'], $accepted['text/event-stream'] );
	}
	/**
	 * Is list.
	 *
	 * @param array $value The value.
	 * @return bool
	 */
	private function is_list( array $value ): bool {
		return array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
