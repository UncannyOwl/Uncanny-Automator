<?php
/**
 * Request-local authority for one checked native REST dispatch.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Recipe_Builder\Shared\Content\Utilities\Post_Content_Request_Scope;
use Uncanny_Automator\App\Transports\Restful\Post_Content\Post_Content_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Post_Meta\Post_Meta_Rest_Controller;
use Uncanny_Automator\App\Transports\Abilities\Automator_Abilities;
use WP_Error;
use WP_REST_Request;

/**
 * No authority is encoded in a model argument, header or persistent option.
 * Nested requests are refused until their own policy has been reviewed.
 */
final class Rest_Request_Scope {

	/** @var self|null The synchronous MCP tool dispatch, never the HTTP session. */
	private static ?self $active = null;

	private WP_REST_Request $request;
	private User_Context $context;
	private string $route;
	private string $method;
	private array $handler;
	private int $max_input_bytes;
	private bool $dispatch_started      = false;
	private bool $callback_started      = false;
	private bool $refused               = false;
	private ?string $upload_fingerprint = null;
	private array $content_handlers     = array();
	private bool $runs_plugin_ability   = false;

	/** @var array<string, string|null> Explicit body/query target identity. */
	private array $targets = array();

	/** Bind authority to one request and its verified executor. */
	private function __construct( WP_REST_Request $request, User_Context $context, array $handler, array $policy ) {
		$this->request         = $request;
		$this->context         = $context;
		$this->route           = $request->get_route();
		$this->method          = $request->get_method();
		$this->handler         = $handler;
		$this->max_input_bytes = $policy['max_input_bytes'];
		if ( isset( $policy['upload'] ) ) {
			$this->upload_fingerprint = Media_Upload_Transfer::fingerprint( $request );
		}
		foreach ( $policy['target_fields'] ?? array() as $field ) {
			$this->targets[ $field ] = self::target_value( $request, $field );
		}
		// Another plugin's ability, not an Automator operation or reviewed route.
		$this->runs_plugin_ability = isset( $policy['ability_name'] ) && array( 'run_ability' ) === ( $policy['tools'] ?? null );
	}

	/**
	 * Whether native dispatch currently belongs to the REST MCP tool.
	 */
	public static function is_active(): bool {
		return null !== self::$active;
	}

	/** Verified caller for the exact native ability, never an ambient WP login. */
	public static function current_context( ?string $ability_name = null ): ?User_Context {
		$scope = self::$active;
		if ( null === $scope || ( null !== $ability_name && '/wp-abilities/v1/abilities/' . $ability_name . '/run' !== $scope->route ) || null !== self::authorize( $scope->request ) ) {
			return null;
		}
		return $scope->context;
	}

	/** Verified authority for this exact native request, including headerless dispatch. */
	public static function current_context_for_request( WP_REST_Request $request ): ?User_Context {
		$scope = self::$active;
		if ( null === $scope || $scope->request !== $request || null !== self::authorize( $request ) ) {
			return null;
		}
		return $scope->context;
	}

	/**
	 * Check the exact request before validation and again before its callback.
	 */
	public static function authorize( WP_REST_Request $request, ?array $handler = null ): ?WP_Error {
		$scope = self::$active;
		if ( null !== $scope && $scope->request !== $request && $scope->runs_plugin_ability ) {
			// Plugins such as WooCommerce implement abilities with internal REST
			// requests. They run natively as the same administrator, exactly as
			// through run_ability. They receive no MCP context: Automator
			// operations and Page Builder routes still require their own scope.
			return null;
		}
		if ( null !== $scope && $scope->request !== $request && Post_Content_Request_Scope::owns( $request ) ) {
			return $scope->authorize_content_request( $request, $handler );
		}
		if ( null === $scope || $scope->request !== $request || $scope->route !== $request->get_route() || $scope->method !== $request->get_method() ) {
			return new WP_Error( 'rest_forbidden', 'Nested or changed REST requests are not available through this tool.', array( 'status' => 403 ) );
		}
		$operation = ( new Rest_Operation_Service() )->resolve( $scope->route, $scope->method );
		if ( is_wp_error( $operation ) ) {
			return $operation;
		}
		if ( $scope->handler !== $operation['handler'] || ( null !== $handler && $handler !== $scope->handler ) ) {
			return new WP_Error( 'rest_forbidden', 'The matched handler changed. Discover the operation again.', array( 'status' => 403 ) );
		}
		if ( $scope->max_input_bytes !== $operation['policy']['max_input_bytes'] ) {
			return new WP_Error( 'rest_forbidden', 'The reviewed operation input limit changed.', array( 'status' => 403 ) );
		}
		if ( ( null !== $scope->upload_fingerprint ) !== isset( $operation['policy']['upload'] ) ) {
			return Media_Upload_Transfer::error( 'upload_changed', 403 );
		}
		foreach ( $scope->targets as $field => $expected ) {
			if ( self::target_value( $request, $field ) !== $expected ) {
				return new WP_Error( 'rest_forbidden', 'The reviewed operation target changed.', array( 'status' => 403 ) );
			}
		}
		// Native sanitization may turn an ID string into an integer, but a hook
		// must not change which object the concrete path selected.
		$actual = $request->get_url_params();
		if ( array_diff_key( $actual, $operation['path_params'] ) ) {
			return new WP_Error( 'rest_forbidden', 'The route parameters changed.', array( 'status' => 403 ) );
		}
		foreach ( $operation['path_params'] as $name => $value ) {
			if ( ! isset( $actual[ $name ] ) || ! is_scalar( $actual[ $name ] ) ) {
				return new WP_Error( 'rest_forbidden', 'The route parameters changed.', array( 'status' => 403 ) );
			}
			$expected = 'integer' === ( $operation['handler']['args'][ $name ]['type'] ?? null ) ? (string) (int) $value : $value;
			$expected = Site_Editor_Operations::canonical_parameter( $operation['handler'], $name, $expected );
			if ( (string) $actual[ $name ] !== $value && (string) $actual[ $name ] !== $expected ) {
				return new WP_Error( 'rest_forbidden', 'The route parameters changed.', array( 'status' => 403 ) );
			}
		}
		$reason = ( new Rest_Operation_Policy() )->unavailable_reason( $operation['policy'], $scope->context, 'rest_request' );
		if ( null !== $reason ) {
			return new WP_Error( 'rest_forbidden', $reason, array( 'status' => 403 ) );
		}
		if ( null !== $scope->upload_fingerprint && ! hash_equals( $scope->upload_fingerprint, Media_Upload_Transfer::fingerprint( $request ) ) ) {
			return Media_Upload_Transfer::error( 'upload_changed', 403 );
		}
		$error = Rest_Operation_Service::validate_request( $request, true, $scope->max_input_bytes, null !== $scope->upload_fingerprint );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		$error = Rest_Operation_Service::validate_operation_request( $request, $operation['policy'] );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		wp_set_current_user( $scope->context->get_executor() );

		return null;
	}

	/** Allow only the content workflow's exact native operation on the same post. */
	private function authorize_content_request( WP_REST_Request $request, ?array $handler ): ?WP_Error {
		$error = self::authorize( $this->request );
		if ( null !== $error ) {
			return $error;
		}
		$callback = $this->handler['callback'] ?? null;
		if ( ! is_array( $callback ) || ! $callback[0] instanceof Post_Content_Rest_Controller || ! in_array( $this->method, array( 'GET', 'POST' ), true ) || ! in_array( $request->get_method(), array( 'GET', 'POST' ), true ) || ( 'GET' === $this->method && 'POST' === $request->get_method() ) ) {
			return new WP_Error( 'rest_forbidden', 'This operation cannot delegate content requests.', array( 'status' => 403 ) );
		}
		$operation = ( new Rest_Operation_Service() )->resolve( $request->get_route(), $request->get_method() );
		if ( is_wp_error( $operation ) ) {
			return $operation;
		}
		$native = $operation['handler']['callback'] ?? null;
		$method = 'GET' === $request->get_method() ? 'get_item' : 'update_item';
		if ( ! is_array( $native ) || ! $native[0] instanceof \WP_REST_Posts_Controller || $method !== $native[1] || (int) ( $operation['path_params']['id'] ?? 0 ) !== (int) $this->request['id'] || (int) $request['id'] !== (int) $this->request['id'] ) {
			return new WP_Error( 'rest_forbidden', 'The native content operation changed.', array( 'status' => 403 ) );
		}
		$key = spl_object_hash( $request );
		if ( ! isset( $this->content_handlers[ $key ] ) ) {
			$this->content_handlers[ $key ] = array( $request, $operation['handler'] );
		}
		if ( $operation['handler'] !== $this->content_handlers[ $key ][1] || ( null !== $handler && $handler !== $operation['handler'] ) ) {
			return new WP_Error( 'rest_forbidden', 'The native content handler changed.', array( 'status' => 403 ) );
		}
		$reason = ( new Rest_Operation_Policy() )->unavailable_reason( $operation['policy'], $this->context, 'rest_request' );
		if ( null !== $reason ) {
			return new WP_Error( 'rest_forbidden', $reason, array( 'status' => 403 ) );
		}
		if ( array_diff( array_keys( $request->get_query_params() ), array( 'context', '_fields' ) ) || array_diff( array_keys( $request->get_body_params() ), array( 'content' ) ) || $request->get_json_params() ) {
			return new WP_Error( 'rest_forbidden', 'Only the prepared content update can be delegated.', array( 'status' => 403 ) );
		}
		// The parent model input is already bounded. This body includes existing
		// article source, which can be larger than the small edits supplied by AI.
		$error = Rest_Operation_Service::validate_request( $request, false );
		if ( ! is_wp_error( $error ) ) {
			wp_set_current_user( $this->context->get_executor() );
		}
		return $error;
	}

	/**
	 * Record that Automator's own operation refused this request before doing anything.
	 *
	 * Only in-process code can say so; a response body never can.
	 */
	public static function refuse_before_running(): void {
		if ( null !== self::$active ) {
			self::$active->refused = true;
		}
	}

	/**
	 * Record a refusal when Automator's content or meta edit saved nothing.
	 *
	 * Those services mark the errors they return before writing with
	 * `nothing_saved`. Called by their own REST controllers, in process.
	 *
	 * @param mixed $result Service result.
	 */
	public static function refuse_if_nothing_saved( $result ): void {
		if ( ! is_wp_error( $result ) ) {
			return;
		}
		$data = $result->get_error_data();
		if ( is_array( $data ) && true === ( $data['nothing_saved'] ?? false ) ) {
			self::refuse_before_running();
		}
	}

	/**
	 * Run native validation, permission callbacks and saving under the executor.
	 *
	 * @param bool|null $may_have_changed Set to whether the route's callback ran without refusing first.
	 */
	public static function dispatch( WP_REST_Request $request, User_Context $context, array $handler, ?array $policy = null, ?bool &$may_have_changed = null ) {
		$may_have_changed = false;
		$parent           = self::$active;
		if ( null !== $parent && ! $parent->can_delegate_content_edit( $request, $context, $handler ) && ! $parent->can_delegate_meta_edit( $request, $context, $handler ) ) {
			return new WP_Error( 'rest_forbidden', 'Recursive REST tool uses are not supported.', array( 'status' => 403 ) );
		}
		$operation = ( new Rest_Operation_Service() )->resolve( $request->get_route(), $request->get_method() );
		if ( is_wp_error( $operation ) || $operation['handler'] !== $handler ) {
			return is_wp_error( $operation ) ? $operation : new WP_Error( 'rest_forbidden', 'The matched handler changed.', array( 'status' => 403 ) );
		}
		if ( null !== $policy && $policy['max_input_bytes'] !== $operation['policy']['max_input_bytes'] ) {
			return new WP_Error( 'rest_forbidden', 'The reviewed operation input limit changed.', array( 'status' => 403 ) );
		}
		if ( null !== $policy && isset( $policy['upload'] ) !== isset( $operation['policy']['upload'] ) ) {
			return Media_Upload_Transfer::error( 'upload_changed', 403 );
		}
		$previous_user = get_current_user_id();
		self::$active  = new self( $request, $context, $handler, $policy ?? $operation['policy'] );
		$scope         = self::$active;
		$guard         = static function ( $response, $server_or_handler, $candidate ) use ( $scope ) {
			if ( self::$active !== $scope ) {
				return $response;
			}
			$error = self::authorize( $candidate, is_array( $server_or_handler ) ? $server_or_handler : null );
			if ( null === $error && $candidate === $scope->request && ! is_array( $server_or_handler ) ) {
				if ( self::$active->dispatch_started ) {
					return new WP_Error( 'rest_forbidden', 'Nested REST requests are not supported.', array( 'status' => 403 ) );
				}
				self::$active->dispatch_started = true;
			}
			return $error ?? $response;
		};
		// WordPress applies this only after argument validation and the permission check pass, right before the callback.
		$started = static function ( $result, $candidate ) use ( $scope ) {
			if ( $candidate === $scope->request ) {
				$scope->callback_started = true;
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $guard, 1, 3 );
		add_filter( 'rest_request_before_callbacks', $guard, PHP_INT_MAX, 3 );
		add_filter( 'rest_dispatch_request', $started, PHP_INT_MIN, 2 );
		try {
			$server   = rest_get_server();
			$response = rest_do_request( $request );
			if ( is_wp_error( $response ) ) {
				$response = rest_convert_error_to_response( $response );
			}
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, applied as WordPress applies it.
			$response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), $server, $request );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$response = rest_ensure_response( $response );
			$response->set_data( $server->response_to_data( $response, false ) );
			return $response;
		} finally {
			remove_filter( 'rest_pre_dispatch', $guard, 1 );
			remove_filter( 'rest_request_before_callbacks', $guard, PHP_INT_MAX );
			remove_filter( 'rest_dispatch_request', $started, PHP_INT_MIN );
			$may_have_changed = $scope->callback_started && ! $scope->refused;
			self::$active     = $parent;
			wp_set_current_user( $previous_user );
		}
	}

	/** Compare nested identity and permit native sanitization of scalar IDs. */
	private static function target_value( WP_REST_Request $request, string $field ): ?string {
		$parts = explode( '.', $field );
		$place = array_shift( $parts );
		$value = 'query' === $place ? $request->get_query_params() : $request->get_json_params();
		foreach ( $parts as $part ) {
			if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
				return null;
			}
			$value = $value[ $part ];
		}
		if ( is_array( $value ) ) {
			// The reviewed body includes nested IDs and ordered identity arrays.
			// Preserve their exact structure instead of treating arrays as null.
			$encoded = wp_json_encode( $value );
			return false === $encoded ? null : $encoded;
		}
		if ( is_string( $value ) && ctype_digit( $value ) ) {
			$digits = ltrim( $value, '0' );
			return '' === $digits ? '0' : $digits;
		}
		return is_scalar( $value ) ? (string) $value : null;
	}

	/** Whether this scope runs the named Automator ability for the same caller. */
	private function runs_owned_ability( string $ability, User_Context $context ): bool {
		return $context === $this->context && null === self::authorize( $this->request ) && '/wp-abilities/v1/abilities/' . $ability . '/run' === $this->route && 'POST' === $this->method && function_exists( 'wp_has_ability' ) && function_exists( 'wp_get_ability' ) && wp_has_ability( $ability ) && Automator_Abilities::owns( wp_get_ability( $ability ) );
	}

	/** The existing patch tool may delegate only its exact checked content edit. */
	private function can_delegate_content_edit( WP_REST_Request $request, User_Context $context, array $handler ): bool {
		if ( ! $this->runs_owned_ability( 'automator/post-apply-content-edit', $context ) ) {
			return false;
		}
		$input = $this->request['input'];
		if ( ! is_array( $input ) ) {
			return false;
		}
		$expected = array(
			'expected_content_hash' => $input['expected_content_hash'] ?? null,
			'edits'                 => $input['edits'] ?? null,
		);
		if ( true === ( $input['include_content'] ?? false ) ) {
			$expected['include_content'] = true;
		}
		if ( isset( $input['editor'] ) ) {
			$expected['editor'] = $input['editor'];
		}
		if ( 'POST' !== $request->get_method() || '/automator/v1/posts/' . ( $input['post_id'] ?? '' ) . '/content-edit' !== $request->get_route() || $request->get_query_params() || $request->get_body_params() || $request->get_json_params() !== $expected ) {
			return false;
		}
		$callback = $handler['callback'] ?? null;
		return is_array( $callback ) && $callback[0] instanceof Post_Content_Rest_Controller && 'post_apply_content_edit' === $callback[1];
	}

	/** The meta tool may delegate only its exact checked change set. */
	private function can_delegate_meta_edit( WP_REST_Request $request, User_Context $context, array $handler ): bool {
		if ( ! $this->runs_owned_ability( 'automator/post-apply-meta-edit', $context ) ) {
			return false;
		}
		$input = $this->request['input'];
		if ( ! is_array( $input ) ) {
			return false;
		}
		// The nested body crossed one JSON encoding, which turns 1.0 into 1.
		$expected = json_decode( (string) wp_json_encode( array( 'changes' => $input['changes'] ?? null ) ), true );
		if ( 'POST' !== $request->get_method() || '/automator/v1/posts/' . ( $input['post_id'] ?? '' ) . '/meta' !== $request->get_route() || $request->get_query_params() || $request->get_body_params() || $request->get_json_params() !== $expected ) {
			return false;
		}
		$callback = $handler['callback'] ?? null;
		return is_array( $callback ) && $callback[0] instanceof Post_Meta_Rest_Controller && 'post_apply_meta_edit' === $callback[1];
	}
}
