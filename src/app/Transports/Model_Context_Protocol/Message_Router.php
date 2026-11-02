<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use Uncanny_Automator\App\Site_Commands\Infrastructure\Request_Time_Budget;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Rate_Limiter;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Registry;
use WP_Error;

/**
 * Stateless MCP 2026-07-28 JSON-RPC dispatcher.
 */
final class Message_Router {

	private const MAX_MRTR_RESPONSES = 20;

	private const MAX_MRTR_BYTES = 1048576;

	private const MAX_MRTR_COLLECTION_ITEMS = 100;

	private ?Tool_Registry $tool_registry;

	private Tool_Rate_Limiter $rate_limiter;

	private Mcp_Request_State $request_state;

	/**
	 * Constructor.
	 */
	public function __construct(
		?Tool_Registry $tool_registry = null,
		?Tool_Rate_Limiter $rate_limiter = null,
		?Mcp_Request_State $request_state = null
	) {
		// Tool construction builds and validates every schema, and some schemas
		// enumerate WordPress object types. Keep that work behind the first actual
		// tool request instead of paying for it while MCP merely registers routes
		// during every unrelated WordPress request.
		$this->tool_registry = $tool_registry;
		$this->rate_limiter  = $rate_limiter ?? new Tool_Rate_Limiter();
		$this->request_state = $request_state ?? new Mcp_Request_State();
	}

	/**
	 * Route one client request. No state survives this call.
	 *
	 * @return array|WP_Error
	 */
	public function route_message( $message, $request, ?Authenticated_Token_Context $context = null ) {
		if ( ! $this->is_valid_request( $message ) ) {
			return new WP_Error(
				'mcp_invalid_json_rpc',
				'Invalid JSON-RPC request.',
				array( 'status' => 400 )
			);
		}

		$id     = $message['id'] ?? null;
		$method = $message['method'];
		$params = isset( $message['params'] ) ? $message['params'] : array();

		if ( null === $id ) {
			return array( 'status' => 'accepted' );
		}

		switch ( $method ) {
			case 'server/discover':
				return $this->handle_discover( $id );

			case 'tools/list':
				return $this->handle_tools_list( $id, $params, $context );

			case 'tools/call':
				return $this->handle_tools_call( $id, $params, $context );

		}

		return Json_Rpc_Envelope::create_error_response(
			$id,
			-32601,
			'Method not found: ' . $method
		);
	}

	/**
	 * Return the server-owned authorization scope for a tool.
	 *
	 * @return string|null
	 */
	public function get_required_tool_scope( string $name ): ?string {
		return $this->get_tool_registry()->get_required_scope( $name );
	}

	/**
	 * Return the validated routing-header map for an exposed tool.
	 *
	 * @return array<string,array{path:string[],type:string}>
	 */
	public function get_tool_header_map( string $name ): array {
		return $this->get_tool_registry()->get_header_map( $name );
	}

	/**
	 * Return the server's stateless protocol contract.
	 *
	 * @param mixed $id JSON-RPC request ID.
	 * @return array
	 */
	private function handle_discover( $id ): array {
		return Json_Rpc_Envelope::create_success_response(
			$id,
			array(
				'supportedVersions' => Mcp_Version_Manager::get_modern_versions(),
				'capabilities'      => array( 'tools' => new \stdClass() ),
				'instructions'      => Server_Instructions::TEXT,
				'ttlMs'             => 300000,
				'cacheScope'        => 'private',
			)
		);
	}

	/**
	 * Return the deterministic list of exposed tools.
	 *
	 * @param mixed $id     JSON-RPC request ID.
	 * @param array $params Request parameters.
	 * @return array
	 */
	private function handle_tools_list( $id, array $params, ?Authenticated_Token_Context $context ): array {
		if ( array_key_exists( 'cursor', $params ) ) {
			return $this->invalid_params( $id, 'The tools cursor is invalid or expired.' );
		}

		return Json_Rpc_Envelope::create_success_response(
			$id,
			array(
				'tools'      => $this->get_tool_registry()->get_schemas_for_client( $context ),
				'ttlMs'      => 300000,
				'cacheScope' => 'private',
			)
		);
	}

	/**
	 * Validate and execute one tool use.
	 *
	 * @param mixed $id     JSON-RPC request ID.
	 * @param mixed $params Request parameters.
	 * @return array
	 */
	private function handle_tools_call( $id, $params, ?Authenticated_Token_Context $context ): array {
		if ( ! is_array( $params ) || $this->is_list( $params ) ) {
			return $this->invalid_params( $id, 'tools/call params must be an object.' );
		}

		if ( ! $this->validate_mrtr_retry( 'tools/call', $params ) ) {
			return $this->invalid_params( $id, 'Invalid MRTR inputResponses or requestState.' );
		}

		$name = $params['name'] ?? null;
		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			return $this->invalid_params( $id, 'tools/call requires a non-empty string name.' );
		}

		$tool_registry = $this->get_tool_registry();
		if ( ! $tool_registry->is_tool_available_to_client( $name, $context ) ) {
			$scope = $tool_registry->get_required_scope( $name );
			if ( $context instanceof Authenticated_Token_Context && null !== $scope && $context->has_scope( $scope ) ) {
				$reason = $tool_registry->tool_unavailable_reason( $name, $context );
				if ( null !== $reason && 'site_tool_disabled' === $reason['code'] ) {
					$message = $reason['message'];
					unset( $reason['message'] );
					return Json_Rpc_Envelope::create_error_response(
						$id,
						-32602,
						$message,
						array_merge(
							$reason,
							array(
								'tool' => $name,
								'outcome' => 'refused_before_running',
								'requires_reauthorization' => false,
							)
						)
					);
				}
			}
			return $this->invalid_params( $id, 'Unknown or unauthorized tool: ' . $name );
		}

		// The REST controller checks this before dispatch, and the router checks it
		// again at the execution boundary. A future transport or parser regression
		// therefore cannot invoke a write tool with a read-only token context.
		$required_scope = $tool_registry->get_required_scope( $name );
		if (
			null === $required_scope
			|| ! $context instanceof Authenticated_Token_Context
			|| ! $context->has_scope( $required_scope )
		) {
			return Json_Rpc_Envelope::create_error_response(
				$id,
				-31903,
				'Bearer token does not grant the required MCP scope.',
				array( 'required_scope' => $required_scope ?? Authenticated_Token_Context::SCOPE_WRITE )
			);
		}

		$arguments = $params['arguments'] ?? array();
		if ( ! is_array( $arguments ) || $this->is_list( $arguments ) ) {
			return $this->invalid_params( $id, 'arguments must be an object.' );
		}

		if ( ! $this->rate_limiter->allows( get_current_user_id(), $name ) ) {
			$retry_after = $this->rate_limiter->retry_after();
			return Json_Rpc_Envelope::create_error_response(
				$id,
				-31929,
				0 < $retry_after
					? sprintf( 'This tool use was refused because its request limit was reached (-31929). Wait %d seconds before trying again.', $retry_after )
					: 'This tool use is disabled by the site rate-limit configuration (-31929). Ask the site administrator to check the automator_mcp_tool_rate_limit_per_minute filter in site plugins or theme code. Waiting will not enable this tool.',
				array(
					'outcome'     => 'refused_before_running',
					'retry_after' => $retry_after,
					'reason'      => 0 < $retry_after ? 'tool_rate_limited' : 'tool_rate_limit_disabled',
				)
			);
		}

		// The credential purpose and write scope travel with the call: action-level
		// policies let a first-party Uncanny Agent credential bypass site-owner
		// switches, and run_ability refuses abilities that make changes when the
		// credential only reads. $context passed the instanceof check above.
		$executor     = get_current_user_id() ? get_current_user_id() : User_Context::ANONYMOUS;
		$user_context = new User_Context(
			$executor,
			null,
			$context->is_first_party_agent(),
			$context->has_scope( Authenticated_Token_Context::SCOPE_WRITE ),
			$context->get_client_id()
		);

		// site_manage keeps to the host's own limit, so record it before raising it.
		Request_Time_Budget::remember_host_limit( (int) ini_get( 'max_execution_time' ) );
		// Actions and recipes run synchronously inside this request; shared hosts
		// commonly cap PHP at 30 seconds, which a slow third-party API exceeds.
		if ( function_exists( 'set_time_limit' ) && false === stripos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Hosts may forbid the call at runtime; a notice must not break the JSON response. The raised limit lets a synchronous action finish.
			@set_time_limit( 300 );
		}

		$result = $tool_registry->execute_tool_with_context( $name, $arguments, $user_context );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Envelope::create_error_response(
				$id,
				$result->get_error_code(),
				$result->get_error_message()
			);
		}

		return Json_Rpc_Envelope::create_success_response( $id, $result );
	}

	/**
	 * Create and validate the server-owned tool catalog only when it is needed.
	 */
	private function get_tool_registry(): Tool_Registry {
		if ( null === $this->tool_registry ) {
			$this->tool_registry = new Tool_Registry();
			$this->tool_registry->auto_register_tools();
		}

		return $this->tool_registry;
	}

	/**
	 * Validate the client-carried portion of a multi-round-trip retry.
	 *
	 * @param string $method Original MCP method.
	 * @param array  $params Retry parameters.
	 * @return bool
	 */
	private function validate_mrtr_retry( string $method, array $params ): bool {
		if ( isset( $params['requestState'] ) ) {
			if (
				! is_string( $params['requestState'] )
				|| ! $this->request_state->verify( $params['requestState'], $method, $params, get_current_user_id() )
			) {
				return false;
			}
		}

		if ( ! isset( $params['inputResponses'] ) ) {
			return true;
		}

		$responses = $params['inputResponses'];
		$encoded   = wp_json_encode( $responses );
		if (
			! is_array( $responses )
			|| $this->is_list( $responses )
			|| count( $responses ) > self::MAX_MRTR_RESPONSES
			|| ! is_string( $encoded )
			|| strlen( $encoded ) > self::MAX_MRTR_BYTES
		) {
			return false;
		}

		foreach ( $responses as $key => $response ) {
			if ( ! is_string( $key ) || '' === $key || ! $this->is_valid_input_response( $response ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate one MRTR response against the three core input response shapes.
	 *
	 * @param mixed $response Candidate input response.
	 */
	private function is_valid_input_response( $response ): bool {
		if ( ! is_array( $response ) || $this->is_list( $response ) ) {
			return false;
		}

		if ( array_key_exists( 'action', $response ) ) {
			return $this->is_valid_elicitation_result( $response );
		}

		if ( array_key_exists( 'roots', $response ) ) {
			return $this->is_valid_roots_result( $response );
		}

		return $this->is_valid_sampling_result( $response );
	}

	/**
	 * Validate an elicitation response.
	 */
	private function is_valid_elicitation_result( array $response ): bool {
		if ( ! is_string( $response['action'] ) || ! in_array( $response['action'], array( 'accept', 'decline', 'cancel' ), true ) ) {
			return false;
		}

		if ( ! array_key_exists( 'content', $response ) ) {
			return true;
		}

		if ( 'accept' !== $response['action'] || ! is_array( $response['content'] ) || $this->is_list( $response['content'] ) ) {
			return false;
		}

		foreach ( $response['content'] as $value ) {
			if ( is_string( $value ) || is_int( $value ) || is_float( $value ) || is_bool( $value ) ) {
				continue;
			}

			if (
				! is_array( $value )
				|| ! $this->is_list_or_empty( $value )
				|| count( $value ) > self::MAX_MRTR_COLLECTION_ITEMS
			) {
				return false;
			}

			foreach ( $value as $choice ) {
				if ( ! is_string( $choice ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Validate a roots/list response.
	 */
	private function is_valid_roots_result( array $response ): bool {
		if ( ! is_array( $response['roots'] ) || count( $response['roots'] ) > self::MAX_MRTR_COLLECTION_ITEMS ) {
			return false;
		}

		foreach ( $response['roots'] as $root ) {
			if ( ! is_array( $root ) || $this->is_list( $root ) ) {
				return false;
			}

			$uri = $root['uri'] ?? null;
			if ( ! is_string( $uri ) || 0 !== strpos( $uri, 'file://' ) || strlen( $uri ) > 2048 ) {
				return false;
			}

			if ( isset( $root['name'] ) && ( ! is_string( $root['name'] ) || strlen( $root['name'] ) > 256 ) ) {
				return false;
			}

			if ( isset( $root['_meta'] ) && ( ! is_array( $root['_meta'] ) || $this->is_list( $root['_meta'] ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate a sampling/createMessage response.
	 */
	private function is_valid_sampling_result( array $response ): bool {
		if (
			! isset( $response['role'], $response['content'], $response['model'] )
			|| ! in_array( $response['role'], array( 'user', 'assistant' ), true )
			|| ! is_string( $response['model'] )
			|| '' === $response['model']
			|| strlen( $response['model'] ) > 256
		) {
			return false;
		}

		if ( isset( $response['stopReason'] ) && ! is_string( $response['stopReason'] ) ) {
			return false;
		}

		$content = $response['content'];
		if ( is_array( $content ) && $this->is_list( $content ) ) {
			if ( count( $content ) > self::MAX_MRTR_COLLECTION_ITEMS ) {
				return false;
			}

			foreach ( $content as $block ) {
				if ( ! $this->is_valid_sampling_content_block( $block ) ) {
					return false;
				}
			}

			return true;
		}

		return $this->is_valid_sampling_content_block( $content );
	}

	/**
	 * Validate one content block from a sampling result.
	 *
	 * @param mixed $block Candidate content block.
	 */
	private function is_valid_sampling_content_block( $block ): bool {
		if ( ! is_array( $block ) || $this->is_list( $block ) || ! is_string( $block['type'] ?? null ) ) {
			return false;
		}

		switch ( $block['type'] ) {
			case 'text':
				return is_string( $block['text'] ?? null );

			case 'image':
			case 'audio':
				return is_string( $block['data'] ?? null ) && is_string( $block['mimeType'] ?? null );

			case 'tool_use':
				return is_string( $block['id'] ?? null )
					&& is_string( $block['name'] ?? null )
					&& is_array( $block['input'] ?? null )
					&& ! $this->is_list( $block['input'] );

			case 'tool_result':
				return is_string( $block['toolUseId'] ?? null )
					&& is_array( $block['content'] ?? null )
					&& $this->is_list_or_empty( $block['content'] )
					&& $this->is_valid_tool_result_content( $block['content'] );
		}

		return false;
	}

	/**
	 * Validate the standard content blocks allowed in a sampling tool result.
	 */
	private function is_valid_tool_result_content( array $content ): bool {
		if ( count( $content ) > self::MAX_MRTR_COLLECTION_ITEMS ) {
			return false;
		}

		foreach ( $content as $block ) {
			if ( ! is_array( $block ) || $this->is_list( $block ) || ! is_string( $block['type'] ?? null ) ) {
				return false;
			}

			if ( in_array( $block['type'], array( 'text', 'image', 'audio' ), true ) ) {
				if ( ! $this->is_valid_sampling_content_block( $block ) ) {
					return false;
				}
				continue;
			}

			if ( 'resource_link' === $block['type'] ) {
				if ( ! is_string( $block['name'] ?? null ) || ! is_string( $block['uri'] ?? null ) ) {
					return false;
				}
				continue;
			}

			if ( 'resource' !== $block['type'] || ! $this->is_valid_embedded_resource( $block['resource'] ?? null ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate the required portion of an embedded text or binary resource.
	 *
	 * @param mixed $candidate Candidate resource contents.
	 */
	private function is_valid_embedded_resource( $candidate ): bool {
		if ( ! is_array( $candidate ) || $this->is_list( $candidate ) || ! is_string( $candidate['uri'] ?? null ) ) {
			return false;
		}

		$has_text = array_key_exists( 'text', $candidate ) && is_string( $candidate['text'] );
		$has_blob = array_key_exists( 'blob', $candidate ) && is_string( $candidate['blob'] );

		return $has_text xor $has_blob;
	}

	/**
	 * Return a JSON-RPC invalid-parameters error.
	 *
	 * @param mixed  $id      JSON-RPC request ID.
	 * @param string $message Client-safe error message.
	 * @return array
	 */
	private function invalid_params( $id, string $message ): array {
		return Json_Rpc_Envelope::create_error_response( $id, -32602, $message );
	}

	/**
	 * Determine whether a decoded value is a client request or notification.
	 *
	 * @param mixed $message Decoded JSON value.
	 * @return bool
	 */
	private function is_valid_request( $message ): bool {
		if ( ! is_array( $message ) || $this->is_list( $message ) ) {
			return false;
		}

		if ( '2.0' !== ( $message['jsonrpc'] ?? null ) || ! is_string( $message['method'] ?? null ) || '' === $message['method'] ) {
			return false;
		}

		if ( array_key_exists( 'id', $message ) && ! is_string( $message['id'] ) && ! is_int( $message['id'] ) ) {
			return false;
		}

		return ! isset( $message['params'] ) || ( is_array( $message['params'] ) && ! $this->is_list( $message['params'] ) );
	}

	/**
	 * Determine whether an array represents a non-empty JSON list.
	 *
	 * @param array $value Decoded JSON array.
	 * @return bool
	 */
	private function is_list( array $value ): bool {
		return array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Determine whether an array represents a JSON list, including an empty one.
	 */
	private function is_list_or_empty( array $value ): bool {
		return array() === $value || $this->is_list( $value );
	}
}
