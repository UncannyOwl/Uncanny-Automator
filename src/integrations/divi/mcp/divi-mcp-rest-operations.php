<?php

namespace Uncanny_Automator\Integrations\Divi;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Request_Scope;

/**
 * Class Divi_Mcp_Rest_Operations
 *
 * Opens Divi's own AI-agent routes for Theme Builder templates and layouts to
 * Automator's MCP REST tools. Registering a route never exposes it: only the
 * operations below, still handled by Divi's own callbacks, are reviewed. Every
 * other divi/v1 route stays closed.
 *
 * @package Uncanny_Automator\Integrations\Divi
 */
class Divi_Mcp_Rest_Operations {

	/**
	 * Divi's REST namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'divi/v1';

	/**
	 * Divi's controller for its AI-agent routes.
	 *
	 * @var string
	 */
	const CONTROLLER = 'ET\Builder\VisualBuilder\REST\OutsideVb\OutsideVbController';

	/**
	 * Divi's REST controller, which names each route's nonce.
	 *
	 * @var string
	 */
	const REST_CONTROLLER = 'ET\Builder\Framework\Controllers\RESTController';

	/**
	 * Reviewed routes: callback, permission callback and whether the route only reads.
	 * Divi registers all of them as POST, including the reads.
	 *
	 * @var array<string, array{0: string, 1: string, 2: bool}>
	 */
	const OPERATIONS = array(
		'/divi/v1/outside-vb/theme-builder/list-templates'  => array( 'list_templates', 'theme_builder_permission', true ),
		'/divi/v1/outside-vb/theme-options/get'             => array( 'get_theme_options', 'theme_options_permission', true ),
		'/divi/v1/outside-vb/theme-builder/create-template' => array( 'create_template', 'create_template_permission', false ),
		'/divi/v1/outside-vb/theme-builder/update-template' => array( 'update_template', 'update_template_permission', false ),
		'/divi/v1/outside-vb/theme-builder/assign-template' => array( 'assign_template', 'assign_template_permission', false ),
		'/divi/v1/outside-vb/theme-builder/upsert-layout'   => array( 'upsert_layout', 'upsert_layout_permission', false ),
	);

	/**
	 * Body fields that choose what a request changes. A hook cannot change them
	 * after review. The title and layout_content are left to Divi's own
	 * sanitizers, which may rewrite them.
	 *
	 * @var string[]
	 */
	const TARGET_FIELDS = array(
		'body.live',
		'body.template_id',
		'body.template',
		'body.layout_type',
		'body.use_default_website_template',
		'body.use_on',
		'body.exclude_from',
		'body.confirm_overwrite',
		'body.create_header_layout',
		'body.create_body_layout',
		'body.create_footer_layout',
	);

	/**
	 * Layout writes carry a whole header or footer layout.
	 *
	 * @var int
	 */
	const LAYOUT_MAX_INPUT_BYTES = 1048576;

	/**
	 * Requests that received a server-side Divi nonce, keyed by object hash.
	 *
	 * @var array<string, true>
	 */
	private $nonced = array();

	/**
	 * Register the review and the nonce bridge around Divi's nonce check.
	 *
	 * @return void
	 */
	public function register() {
		// Run last so a later review filter cannot reopen a divi/v1 route.
		add_filter( 'automator_mcp_rest_operation_policy_review', array( $this, 'review_policy' ), PHP_INT_MAX, 4 );
		// Divi checks its nonce at priority 10; MCP's final guard runs at PHP_INT_MAX.
		add_filter( 'rest_request_before_callbacks', array( $this, 'attach_nonce' ), 9, 3 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'detach_nonce' ), 11, 3 );
	}

	/**
	 * Review exposure for divi/v1 routes and leave every other route untouched.
	 *
	 * @param array|null $policy  Policy from earlier filters.
	 * @param string     $route   Registered route pattern.
	 * @param string     $method  HTTP method.
	 * @param array      $handler Registered route handler.
	 *
	 * @return array|null
	 */
	public function review_policy( $policy, $route = null, $method = null, $handler = null ) {
		if ( ! is_string( $route ) || 0 !== strpos( strtolower( $route ), '/' . self::REST_NAMESPACE . '/' ) ) {
			return $policy;
		}
		if ( ! $this->is_reviewed( $route, $method, $handler ) ) {
			return null;
		}

		$read_only = self::OPERATIONS[ $route ][2];
		$reviewed  = array(
			'read_only'      => $read_only,
			// Theme Builder writes use the same gate as other content edits.
			'tools'          => $read_only ? array( 'get_posts' ) : array( 'get_posts', 'post_apply_content_edit' ),
			'request_schema' => self::request_schema( $read_only ),
			'target_fields'  => self::TARGET_FIELDS,
		);
		if ( '/divi/v1/outside-vb/theme-builder/upsert-layout' === $route ) {
			$reviewed['max_input_bytes'] = self::LAYOUT_MAX_INPUT_BYTES;
		}

		return $reviewed;
	}

	/**
	 * Satisfy Divi's browser nonce for a verified MCP request.
	 *
	 * Divi's X-ET-Nonce protects cookie sessions from cross-site requests. MCP
	 * requests are token-authenticated and checked by Rest_Request_Scope, the same
	 * reason WordPress skips its own REST nonce for non-cookie authentication. The
	 * nonce is created for the verified executor and removed before MCP's final
	 * guard, which refuses any request header. The nonce stays valid for its normal
	 * lifetime, but it only stands in for CSRF protection, which a client holding
	 * the user's credentials does not need.
	 *
	 * @param mixed            $response Response from earlier filters.
	 * @param array            $handler  Matched route handler.
	 * @param \WP_REST_Request $request  Request.
	 *
	 * @return mixed
	 */
	public function attach_nonce( $response, $handler = null, $request = null ) {
		if ( null !== $response || ! $request instanceof \WP_REST_Request ) {
			return $response;
		}
		$route = $request->get_route();
		if ( ! $this->is_reviewed( $route, $request->get_method(), $handler ) || ! is_callable( array( self::REST_CONTROLLER, 'get_nonce_name' ) ) ) {
			return $response;
		}
		if ( null === Rest_Request_Scope::current_context_for_request( $request ) ) {
			return $response;
		}

		$action = call_user_func( array( self::REST_CONTROLLER, 'get_nonce_name' ), self::REST_NAMESPACE, substr( $route, strlen( '/' . self::REST_NAMESPACE ) ), 'POST' );
		$request->set_header( 'X-ET-Nonce', wp_create_nonce( $action ) );
		$this->nonced[ spl_object_hash( $request ) ] = true;

		return $response;
	}

	/**
	 * Remove the server-side nonce once Divi has checked it.
	 *
	 * @param mixed            $response Response from earlier filters.
	 * @param array            $handler  Matched route handler.
	 * @param \WP_REST_Request $request  Request.
	 *
	 * @return mixed
	 */
	public function detach_nonce( $response, $handler = null, $request = null ) {
		if ( $request instanceof \WP_REST_Request && isset( $this->nonced[ spl_object_hash( $request ) ] ) ) {
			unset( $this->nonced[ spl_object_hash( $request ) ] );
			$request->remove_header( 'X-ET-Nonce' );
		}

		return $response;
	}

	/**
	 * Whether the handler is Divi's own reviewed operation for this route.
	 *
	 * @param string $route   Route.
	 * @param string $method  HTTP method.
	 * @param mixed  $handler Route handler.
	 *
	 * @return bool
	 */
	private function is_reviewed( $route, $method, $handler ) {
		$operation = self::OPERATIONS[ $route ] ?? null;
		if ( null === $operation || 'POST' !== $method || ! is_array( $handler ) || ! class_exists( self::CONTROLLER ) ) {
			return false;
		}

		// A route another plugin re-registered with its own callbacks is not Divi's operation.
		return array( self::CONTROLLER, $operation[0] ) === ( $handler['callback'] ?? null )
			&& array( self::CONTROLLER, $operation[1] ) === ( $handler['permission_callback'] ?? null );
	}

	/**
	 * Request shape: arguments in the JSON body, and an explicit live choice on writes.
	 *
	 * @param bool $read_only Whether the route only reads.
	 *
	 * @return array
	 */
	private static function request_schema( $read_only ) {
		$body = array( 'type' => 'object' );
		if ( ! $read_only ) {
			// Divi writes the live Theme Builder by default; every write must choose.
			$body['required']   = array( 'live' );
			$body['properties'] = array( 'live' => array( 'type' => 'boolean' ) );
		}

		return array(
			'type'       => 'object',
			'properties' => array(
				'query' => array(
					'type'          => 'object',
					'maxProperties' => 0,
				),
				'body'  => $body,
			),
			'required'   => array( 'query', 'body' ),
		);
	}
}
