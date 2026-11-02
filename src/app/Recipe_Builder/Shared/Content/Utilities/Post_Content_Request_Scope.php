<?php
/**
 * Bind one internal native post request to the content-edit workflow.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Content\Utilities;

/** No authority comes from headers or model-supplied request parameters. */
final class Post_Content_Request_Scope {

	private static ?\WP_REST_Request $active = null;
	private static string $route             = '';
	private static string $method            = '';

	/** Whether this exact, unchanged request belongs to the internal workflow. */
	public static function owns( \WP_REST_Request $request ): bool {
		return self::$active === $request && self::$route === $request->get_route() && self::$method === $request->get_method();
	}

	/** Run registered native permissions, validation and REST middleware. */
	public static function dispatch( \WP_REST_Request $request ) {
		if ( null !== self::$active ) {
			return new \WP_Error( 'rest_forbidden', 'Recursive content requests are not supported.', array( 'status' => 403 ) );
		}
		self::$active = $request;
		self::$route  = $request->get_route();
		self::$method = $request->get_method();
		$started      = false;
		$post_id      = (int) $request['id'];
		$guard        = static function ( $response, $server, $candidate ) use ( &$started, $post_id ) {
			if ( ! self::owns( $candidate ) || (int) $candidate['id'] !== $post_id || ( ! is_array( $server ) && $started ) ) {
				return new \WP_Error( 'rest_forbidden', 'Nested or changed content requests are not supported.', array( 'status' => 403 ) );
			}
			if ( ! is_array( $server ) ) {
				$started = true;
			}
			return $response;
		};
		add_filter( 'rest_pre_dispatch', $guard, 0, 3 );
		add_filter( 'rest_request_before_callbacks', $guard, PHP_INT_MAX - 1, 3 );
		try {
			$response = rest_do_request( $request );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, applied as WordPress applies it.
			$response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), rest_get_server(), $request );
			$response = rest_ensure_response( $response );
			return is_wp_error( $response ) ? $response : ( $response->is_error() ? $response->as_error() : $response );
		} finally {
			remove_filter( 'rest_pre_dispatch', $guard, 0 );
			remove_filter( 'rest_request_before_callbacks', $guard, PHP_INT_MAX - 1 );
			self::$active = null;
			self::$route  = '';
			self::$method = '';
		}
	}
}
