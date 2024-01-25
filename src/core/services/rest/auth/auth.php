<?php
namespace Uncanny_Automator\Rest\Auth;

use WP_REST_Request;

class Auth {

	/**
	 * Callback method for rest authentication.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool True if nonce is verified. Otherwise, false.
	 */
	public static function verify_permission( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

}
