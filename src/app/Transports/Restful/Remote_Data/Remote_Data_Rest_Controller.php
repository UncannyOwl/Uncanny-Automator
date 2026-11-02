<?php
/**
 * Remote Data REST Controller.
 *
 * Single REST endpoint that dispatches to integration handler instances
 * resolved via the `automator_remote_data_instance_{id}` filter. Replaces
 * the per-integration admin-ajax registration pattern with a unified
 * `POST /wp-json/uap/v2/remote-data/{id}/{data}` route.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Remote_Data;

use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Arg;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;
use Exception;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Remote Data REST registration hub.
 *
 * Static-only. Single endpoint — no `Routes/` split. Delegates handler
 * resolution + dispatch to `Remote_Data_Handler_Resolver` so the same
 * dispatch path is reachable in-process from non-REST callers (e.g. the
 * MCP Dropdown_Controller).
 */
final class Remote_Data_Rest_Controller {

	use Restful_Permissions;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route segment for the remote-data surface.
	 *
	 * @var string
	 */
	private const REST_BASE = 'remote-data';

	/**
	 * Register the remote-data route. Called once per process from
	 * `Restful_Bootstrap::register_rest_routes()` on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function register_routes(): void {

		// POST /uap/v2/remote-data/{id}/{data}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<id>[a-zA-Z0-9_-]+)/(?P<data>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'handle_request' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => self::endpoint_args(),
				),
			)
		);
	}

	/**
	 * Declare what the route accepts and how each value is sanitised.
	 *
	 * `id` / `data` are required URL slugs (sanitize_key, type string);
	 * everything else is optional request-body context that the
	 * `Remote_Data_Request` DTO reads via per-field accessors.
	 *
	 * `values` is inlined because it's the only `type: 'object'` arg
	 * across the entire REST surface — not worth a `Rest_Arg::*`
	 * helper of its own.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function endpoint_args(): array {
		return array(
			'id'           => Rest_Arg::key( null, true ),
			'data'         => Rest_Arg::key( null, true ),
			'recipe_id'    => Rest_Arg::integer( null, false ),
			'item_id'      => Rest_Arg::integer( null, false ),
			'parent_id'    => Rest_Arg::integer( null, false ),
			'group_id'     => Rest_Arg::string( null, false ),
			'field_id'     => Rest_Arg::string( null, false ),
			'triggered_by' => Rest_Arg::string( null, false ),
			'values'       => array(
				'required' => false,
				'type'     => 'object',
			),
			'context'      => Rest_Arg::string( null, false ),
			'q'            => Rest_Arg::string( null, false ),
		);
	}

	/**
	 * Handle a remote data request.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_request( WP_REST_Request $request ): WP_REST_Response {

		try {
			$id   = $request->get_param( 'id' );
			$data = $request->get_param( 'data' );

			$resolver       = new Remote_Data_Handler_Resolver();
			$option_request = new Remote_Data_Request( $request );
			$result         = $resolver->dispatch( $id, $data, $option_request );

			return new WP_REST_Response( $result, 200 );

		} catch ( Exception $e ) {
			// HTTP 200 is intentional — the frontend reads response.success to determine
			// outcome and displays the error message in the field UI. Non-200 status codes
			// would be swallowed by apiFetch and not reach the field handler callbacks.
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				),
				200
			);
		}
	}
}
