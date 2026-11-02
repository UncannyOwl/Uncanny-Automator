<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Utilities\Traits;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Context_Service;
use WP_Error;
use WP_REST_Request;

/**
 * Permission check for routes that read site-administration data: the REST
 * nonce and the fixed manage_options capability the MCP routes use.
 *
 * Never the filterable Automator capability (`Restful_Permissions`): a site can
 * lower it so editors build recipes, and editors must not read this data.
 *
 * Static, so controllers reference it as `[ self::class, 'permissions_check' ]`.
 */
trait Restful_Admin_Permissions {

	/**
	 * The REST nonce and the fixed manage_options capability.
	 *
	 * @return true|WP_Error
	 */
	public static function permissions_check( WP_REST_Request $request ) {
		if ( ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', 'Invalid nonce security.', array( 'status' => 403 ) );
		}

		$context = new Client_Context_Service();
		if ( ! $context->user_has_capability( $context->get_client_access_capability() ) ) {
			return new WP_Error( 'rest_forbidden', 'You do not have the capability to access this resource.', array( 'status' => 403 ) );
		}

		return true;
	}
}
