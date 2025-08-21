<?php

namespace Uncanny_Automator\Services\Admin_Post;

use Uncanny_Automator\Services\Admin_Post\Routes\Pro_Auto_Install;
use Uncanny_Automator\Services\Admin_Post\Routes\Pro_Auto_Install\Silent_Upgrader_Skin;
use Plugin_Upgrader;

/**
 * Admin Post Routes Registry.
 *
 * Add your routes here.
 *
 * @since 6.0.2
 */
final class Routes_Registry {

	/**
	 * Register all admin-post routes.
	 *
	 * Register your routes here.
	 *
	 * @return void
	 */
	public static function register() {

		// Register only in admin.
		if ( ! is_admin() ) {
			return;
		}

		// Best practice is to explicitly pass the dependencie to the class instead of tightly coupling the class to the route.
		Admin_Post_Routes::add(
			'uncanny_automator_pro_auto_install',
			new Pro_Auto_Install( self::create_upgrader() ),
			'process_installation'
		);

		// Register all routes.
		Admin_Post_Routes::register_routes();
	}

	/**
	 * Create a new upgrader instance.
	 *
	 * @return Plugin_Upgrader
	 */
	private static function create_upgrader() {

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		return new Plugin_Upgrader( new Silent_Upgrader_Skin() );
	}
}
