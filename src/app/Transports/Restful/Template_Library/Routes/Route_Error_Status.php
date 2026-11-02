<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Template_Library\Routes;

use WP_Error;

/**
 * WP_Error → HTTP status mapping shared by the template-library routes:
 * missing template is 404, upstream fetch/parse trouble is 502, any
 * other (import-engine) failure is a 400.
 */
final class Route_Error_Status {

	/**
	 * Resolve the HTTP status for a service failure.
	 *
	 * @param WP_Error $error Service error.
	 * @return int
	 */
	public static function for_error( WP_Error $error ): int {
		$code = (string) $error->get_error_code();

		if ( 'template_not_found' === $code ) {
			return 404;
		}
		if ( in_array( $code, array( 'template_library_unreachable', 'template_library_invalid_feed', 'template_library_invalid_export' ), true ) ) {
			return 502;
		}

		return 400;
	}
}
