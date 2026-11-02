<?php

namespace Uncanny_Automator\App\Transports\Restful\Utilities\Traits;

use WP_Error;
use WP_REST_Request;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Trait to validate the permissions of Rest API requests.
 *
 * All methods are static — the permission check reads no instance state,
 * and consumers reference them as `[ self::class, 'permissions_check' ]`
 * inside `register_rest_route()` callbacks so no controller instance is
 * required at registration time.
 */
trait Restful_Permissions {

	/**
	 * Validates the permissions of Rest API requests.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public static function permissions_check( WP_REST_Request $request ) {

		if ( ! self::validate_nonce( $request ) ) {
			return self::return_permission_error( 'Invalid nonce security.' );
		}

		if ( ! self::validate_user_permissions() ) {
			return self::return_permission_error( 'You do not have the capability to access this resource.' );
		}

		return true;
	}

	/**
	 * Validates the nonce of Rest API requests.
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	private static function validate_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Checks if the user has the required permissions to access the Rest API.
	 *
	 * @return bool
	 */
	private static function validate_user_permissions(): bool {
		$capability = automator_get_capability();
		// Backward compatibility - allow old filters to override.
		$capability = Dispatcher::filter_deprecated( 'uap_roles_modify_recipe', array( $capability ), '3.0', 'automator_capability' );
		$capability = Dispatcher::filter_deprecated( 'automator_capability_required', array( $capability ), '7.0', 'automator_capability' );

		if ( ! current_user_can( $capability ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from filter.
			return false;
		}

		$setting = true;
		$setting = Dispatcher::filter_deprecated( 'uap_save_setting_permissions', array( $setting ), '3.0', 'automator_save_setting_permissions' );

		return Dispatcher::filter( 'automator_save_setting_permissions', $setting );
	}

	/**
	 * Returns a REST error.
	 *
	 * @param string $message
	 * @return WP_Error
	 */
	private static function return_permission_error( string $message ): WP_Error {
		return new WP_Error(
			'rest_forbidden',
			$message,
			array( 'status' => 403 )
		);
	}
}
