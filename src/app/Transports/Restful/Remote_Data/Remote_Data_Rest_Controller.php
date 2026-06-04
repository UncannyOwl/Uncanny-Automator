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
 *
 * @since 7.3
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Remote_Data;

use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;
use Exception;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Remote Data REST Controller.
 *
 * Thin transport. Delegates handler resolution + dispatch to
 * Remote_Data_Handler_Resolver so the same dispatch path is reachable
 * in-process from non-REST callers (e.g. the MCP Dropdown_Controller).
 */
class Remote_Data_Rest_Controller {

	use Restful_Permissions;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'remote-data';

	/**
	 * Register the remote data route.
	 *
	 * POST /wp-json/uap/v2/remote-data/{id}/{data}
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/(?P<data>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'handle_request' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_endpoint_args(),
				),
			)
		);
	}

	/**
	 * Define the endpoint arguments.
	 *
	 * @return array
	 */
	protected function get_endpoint_args(): array {
		return array(
			'id'           => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'data'         => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'recipe_id'    => array(
				'required' => false,
				'type'     => 'integer',
			),
			'item_id'      => array(
				'required' => false,
				// Triggers/actions send a numeric post ID; conditions/loop-filters send a
				// generated slot slug (e.g. "mpcymlpobzm9q35d8gg"). Accept both — handlers
				// treat item_id as opaque context, never index off it as an integer.
				'type'     => array( 'integer', 'string' ),
			),
			'parent_id'    => array(
				'required' => false,
				'type'     => 'integer',
			),
			'group_id'     => array(
				'required' => false,
				'type'     => 'string',
			),
			'field_id'     => array(
				'required' => false,
				'type'     => 'string',
			),
			'triggered_by' => array(
				'required' => false,
				'type'     => 'string',
			),
			'values'       => array(
				'required' => false,
				'type'     => 'object',
			),
			'context'      => array(
				'required' => false,
				'type'     => 'string',
			),
			'q'            => array(
				'required' => false,
				'type'     => 'string',
			),
		);
	}

	/**
	 * Handle a remote data request.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ) {

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
