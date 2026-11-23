<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Template_Library\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Importer;
use WP_Error;

/**
 * WP_Error → HTTP status mapping shared by the template-library routes:
 * a missing template or recipe is 404, a recipe that can't be filled is 409,
 * upstream fetch/parse trouble is 502, a fill the database didn't confirm
 * is 503, any other (import-engine) failure is a 400.
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

		$not_found = array(
			'template_not_found',
			Recipe_Importer::ERROR_RECIPE_NOT_FOUND,
		);
		if ( in_array( $code, $not_found, true ) ) {
			return 404;
		}

		$cannot_fill = array(
			Recipe_Importer::ERROR_RECIPE_NOT_BLANK,
			Recipe_Importer::ERROR_RECIPE_NOT_DRAFT,
		);
		if ( in_array( $code, $cannot_fill, true ) ) {
			return 409;
		}

		if ( Recipe_Importer::ERROR_FILL_UNVERIFIED === $code ) {
			return 503;
		}

		if ( in_array( $code, array( 'template_library_unreachable', 'template_library_invalid_feed', 'template_library_invalid_export' ), true ) ) {
			return 502;
		}

		return 400;
	}
}
