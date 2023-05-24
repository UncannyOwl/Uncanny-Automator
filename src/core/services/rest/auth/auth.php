<?php
namespace Uncanny_Automator\Rest\Auth;

use WP_REST_Request;

class Auth {

	/**
	 * Callback method for rest authentication.
	 *
	 * @template Adapter_Of_Array_Access_Port of \WP_REST_Request
	 * @param Adapter_Of_Array_Access_Port $request
	 *
	 * @return int|bool True if nonce is verified. Otherwise, false.
	 */
	public static function verify_nonce( WP_REST_Request $request ) {

		$x_nonce = $request->get_header( 'X-WP-Nonce' );

		// X-WP-Nonce is required.
		if ( is_null( $x_nonce ) ) {
			return apply_filters( 'automator_rest_auth_verify', false );
		}

		return apply_filters(
			'automator_rest_auth_verify',
			wp_verify_nonce( $x_nonce, 'wp_rest' )
		);

	}

}
