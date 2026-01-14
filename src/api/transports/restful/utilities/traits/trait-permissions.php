<?php

namespace Uncanny_Automator\Api\Transports\Restful\Utilities\Traits;

use WP_Error;
use WP_REST_Request;

/**
 * Trait to validate the permissions of Rest API requests
 *
 */
trait Restful_Permissions {

	/**
	 * Validates the permissions of Rest API requests
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool|WP_Error
	 */
	public function permissions_check( WP_REST_Request $request ) {

		if ( ! $this->validate_nonce( $request ) ) {
			return $this->return_permission_error( 'Invalid nonce security.' );
		}

		if ( ! $this->validate_user_permissions( $request ) ) {
			return $this->return_permission_error( 'You do not have the capability to access this resource.' );
		}

		return true;
	}

	/**
	 * Validates the nonce of Rest API requests
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool
	 */
	private function validate_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Checks if the user has the required permissions to access the Rest API
	 *
	 * @return bool
	 */
	private function validate_user_permissions(): bool {
		$capability = automator_get_capability();
		// Backward compatibility - allow old filters to override
		$capability = apply_filters_deprecated( 'uap_roles_modify_recipe', array( $capability ), '3.0', 'automator_capability' );
		$capability = apply_filters_deprecated( 'automator_capability_required', array( $capability ), '7.0', 'automator_capability' );

		// Restrict endpoint to only users who have the edit_posts capability.
		if ( ! current_user_can( $capability ) ) {  // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from filter.
			return false;
		}

		// This is a black-listing approach. You could alternatively do this via white-listing, by returning false here and changing the permissions check.
		$setting = true;
		$setting = apply_filters_deprecated( 'uap_save_setting_permissions', array( $setting ), '3.0', 'automator_save_setting_permissions' );

		return apply_filters( 'automator_save_setting_permissions', $setting );
	}

	/**
	 * Returns a REST error
	 *
	 * @param string $message
	 *
	 * @return WP_Error
	 */
	private function return_permission_error( string $message ): WP_Error {
		return new WP_Error(
			'rest_forbidden',
			$message,
			array( 'status' => 403 )
		);
	}
}
