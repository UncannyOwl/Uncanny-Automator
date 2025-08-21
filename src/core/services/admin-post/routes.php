<?php

namespace Uncanny_Automator\Services\Admin_Post;

/**
 * Simple Admin Post Route Registry.
 *
 * @since 6.0.2
 */
class Admin_Post_Routes {

	/**
	 * Route registry.
	 *
	 * @var array
	 */
	private static $routes = array();

	/**
	 * Add a route.
	 *
	 * @param string $action Action name.
	 * @param object $instance Instance of the class.
	 * @param string $method Method name.
	 *
	 * @return void
	 */
	public static function add( $action, $instance, $method ) {
		self::$routes[ $action ] = array( $instance, $method );
	}

	/**
	 * Register all routes with WordPress.
	 *
	 * @return void
	 */
	public static function register_routes() {
		foreach ( self::$routes as $action => $callback ) {
			add_action( "admin_post_{$action}", $callback );
		}
	}

	/**
	 * Get all routes.
	 *
	 * @return array
	 */
	public static function get_routes() {
		return self::$routes;
	}

	/**
	 * Get admin-post URL for an action.
	 *
	 * @param string $action Action name.
	 *
	 * @return string
	 */
	public static function get_url( $action ) {
		return admin_url( "admin-post.php?action={$action}" );
	}
}
