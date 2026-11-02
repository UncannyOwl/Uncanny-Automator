<?php
/**
 * Automator component field options tool.
 *
 * @package Uncanny_Automator
 * @since 8.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Action_Availability_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Standalone\Dropdown_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Fetch dropdown options for an Automator action or trigger field.
 */
class Get_Field_Options_Tool extends Abstract_MCP_Tool {

	/** @var Dropdown_Controller */
	private $dropdown_controller;

	/** @var Action_Availability_Policy */
	private Action_Availability_Policy $availability;

	/**
	 * @param Dropdown_Controller|null $dropdown_controller Shared dropdown resolver.
	 * @param Action_Availability_Policy|null $availability Action availability for this caller.
	 */
	public function __construct( ?Dropdown_Controller $dropdown_controller = null, ?Action_Availability_Policy $availability = null ) {
		$this->dropdown_controller = $dropdown_controller ?? new Dropdown_Controller();
		$this->availability        = $availability ?? new Action_Availability_Policy();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'get_field_options';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Get the allowed values for a dropdown field returned by get_component_schema. '
			. 'Use the option value when configuring a recipe; the option text is only a human-readable label. '
			. 'For dependent fields, include every selection already made in parent_values.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'entity_type'       => array(
					'type'        => 'string',
					'enum'        => array( 'action', 'trigger' ),
					'description' => 'Component type: action or trigger.',
				),
				'entity_code'       => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Action or trigger code returned by search.',
				),
				'field_option_code' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Field option code returned by get_component_schema.',
				),
				'parent_values'     => array(
					'type'                 => 'object',
					'description'          => 'Selections already made for parent fields, keyed by field option code.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'entity_type', 'entity_code', 'field_option_code' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		if ( 'action' === $params['entity_type'] && ! $this->availability->is_available( $params['entity_code'], $user_context ) ) {
			return Json_Rpc_Response::create_error_response( $this->availability->get_refusal_message( $params['entity_code'] ) );
		}

		$request = new WP_REST_Request( 'GET', '/automator/v1/mcp/dropdown' );
		$request->set_param( 'entity_type', $params['entity_type'] );
		$request->set_param( 'entity_code', $params['entity_code'] );
		$request->set_param( 'field_option_code', $params['field_option_code'] );

		$parent_values = $params['parent_values'] ?? array();
		if ( ! empty( $parent_values ) ) {
			$request->set_param( 'parent_values', wp_json_encode( $parent_values ) );

			// Legacy dropdown handlers accept one parent through dedicated fields.
			if ( 1 === count( $parent_values ) ) {
				$parent_code = array_key_first( $parent_values );
				$request->set_param( 'field_parent_option_code', $parent_code );
				$request->set_param( 'field_parent_option_value', $parent_values[ $parent_code ] );
			}
		}

		// This is an internal method call, not a dispatch through the standalone
		// /mcp/dropdown REST route. The two entrances deliberately have different
		// token contracts: that legacy endpoint is Agent-only, while this registered
		// MCP tool has already passed Message_Router's mcp:read check and
		// Abstract_MCP_Tool's administrator check. The REST controller reuses the
		// current authenticated administrator for any legacy AJAX loopback.
		$response = $this->dropdown_controller->get_dropdown_options( $request );

		if ( is_wp_error( $response ) ) {
			return Json_Rpc_Response::create_wp_error_response( $response );
		}

		if ( ! $response instanceof WP_REST_Response || ! is_array( $response->get_data() ) ) {
			return Json_Rpc_Response::create_error_response( 'Field options could not be resolved.' );
		}

		$data   = $response->get_data();
		$status = $response->get_status();
		if ( $status < 200 || $status >= 300 || false === ( $data['success'] ?? null ) ) {
			$data['status'] = $status;
			return Json_Rpc_Response::create_error_response(
				is_string( $data['message'] ?? null ) ? $data['message'] : 'Field options could not be resolved.',
				$data
			);
		}

		return Json_Rpc_Response::create_success_response(
			'Field options retrieved successfully',
			$data
		);
	}
}
