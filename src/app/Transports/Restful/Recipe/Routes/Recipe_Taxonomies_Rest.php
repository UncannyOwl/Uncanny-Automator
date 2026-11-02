<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Taxonomy_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;

/**
 * Available recipe terms read handler.
 *
 * `GET /uap/v2/recipe-taxonomies` -> `{ data: { categories: Term[], tags: Term[] } }`
 * — the full term universe for the taxonomy pickers (incl. unused terms).
 * Term shape: `{ id, name, slug, parent }`.
 */
final class Recipe_Taxonomies_Rest {

	use Rest_Responses;

	/**
	 * REST callback. Referenced from Recipe_Rest_Controller as
	 * `[ Recipe_Taxonomies_Rest::class, 'dispatch' ]`.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function dispatch( WP_REST_Request $request ) {
		unset( $request );

		return self::success( Recipe_Taxonomy_Service::instance()->get_all_terms_wire() );
	}
}
