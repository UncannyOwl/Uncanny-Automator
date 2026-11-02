<?php
/**
 * Inspect and replace a LearnDash curriculum through its native builder.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */

declare(strict_types=1);

namespace Uncanny_Automator\Integrations\Learndash\Mcp;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes the curriculum service through a bounded REST contract.
 *
 * LearnDash's v2 steps writer does not forward section headings. This adapter
 * keeps them in the native builder lifecycle instead of patching metadata.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */
final class Curriculum_Rest_Controller extends WP_REST_Controller {

	/**
	 * Registered route pattern used by MCP's handler ownership check.
	 *
	 * @var string
	 */
	public const ROUTE = '/automator/v1/learndash/courses/(?P<id>[\d]+)/curriculum';

	/**
	 * Curriculum validation and native persistence service.
	 *
	 * @var Curriculum_Service
	 */
	private Curriculum_Service $service;

	/**
	 * Initialize the controller with its curriculum service.
	 *
	 * @param Curriculum_Service|null $service Service instance. Defaults to the native adapter.
	 */
	public function __construct( ?Curriculum_Service $service = null ) {
		$this->namespace = 'automator/v1';
		$this->service   = $service ?? new Curriculum_Service();
	}

	/**
	 * Register inspection and replacement when LearnDash can preserve sections.
	 *
	 * @return void
	 */
	public function register_routes() {
		if ( ! class_exists( 'LD_REST_Courses_Steps_Controller_V2' ) || ! method_exists( 'LDLMS_Course_Steps', 'set_steps_keeping_sections' ) ) {
			return;
		}
		$id     = array(
			'id' => array(
				'type'     => 'integer',
				'minimum'  => 1,
				'required' => true,
			),
		);
		$schema = Curriculum_Service::schema();
		$args   = $schema['properties'];
		foreach ( $schema['required'] as $key ) {
			$args[ $key ]['required'] = true;
		}
		register_rest_route(
			$this->namespace,
			'/learndash/courses/(?P<id>[\d]+)/curriculum',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $id,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array_merge( $id, $args ),
				),
			)
		);
	}

	/**
	 * Check native editing permissions for both inspection and replacement.
	 *
	 * @param WP_REST_Request $request Request containing the target course ID.
	 *
	 * @return bool|WP_Error True when permitted, or an authorization/dependency error.
	 */
	public function permissions_check( WP_REST_Request $request ) {
		return $this->service->permissions_check( (int) $request['id'] );
	}

	/**
	 * Return the ordered curriculum and its change-detection revision.
	 *
	 * @param WP_REST_Request $request Request containing the target course ID.
	 *
	 * @return WP_REST_Response|WP_Error Curriculum response, or an inspection error.
	 */
	public function get_item( $request ) {
		return rest_ensure_response( $this->service->read( (int) $request['id'] ) );
	}

	/**
	 * Replace the curriculum using only fields from the reviewed schema.
	 *
	 * @param WP_REST_Request $request Request containing the complete replacement.
	 *
	 * @return WP_REST_Response|WP_Error Verified curriculum response, or a save error.
	 */
	public function update_item( $request ) {
		$input = array();
		foreach ( array_keys( Curriculum_Service::schema()['properties'] ) as $key ) {
			if ( $request->has_param( $key ) ) {
				$input[ $key ] = $request[ $key ];
			}
		}
		return rest_ensure_response( $this->service->apply( (int) $request['id'], $input ) );
	}
}
