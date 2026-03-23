<?php
/**
 * Automator Component Schema Tool.
 *
 * Unified MCP tool for retrieving trigger, action, and condition schemas.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Action\Services\Action_Registry_Service;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Automator component schema tool.
 *
 * @since 7.0.0
 */
class Get_Component_Schema_Tool extends Abstract_MCP_Tool {

	private const TYPE_TRIGGER   = 'trigger';
	private const TYPE_ACTION    = 'action';
	private const TYPE_CONDITION = 'condition';

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'get_component_schema';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Get field schema for a trigger, action, or condition using code or instance ID. Shows required fields and defaults. After getting schema, use get_field_options for any fields with supports_custom_value: false.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'component_type'       => array(
					'type'        => 'string',
					'enum'        => array( self::TYPE_TRIGGER, self::TYPE_ACTION, self::TYPE_CONDITION ),
					'description' => 'Component type to retrieve (trigger, action, or condition).',
				),
				'code'                 => array(
					'type'        => 'string',
					'description' => 'Component code from the registry (e.g., "WP_USER_LOGIN", "SENDEMAIL"). Required unless retrieving a trigger/action instance by ID.',
				),
				'id'                   => array(
					'type'        => 'integer',
					'description' => 'Existing trigger or action instance ID. Use this when you need the saved configuration from the database.',
					'minimum'     => 1,
				),
				'include_field_schema' => array(
					'type'        => 'boolean',
					'description' => 'Whether to include detailed field schema for conditions. Ignored for triggers/actions. Default: true.',
					'default'     => true,
				),
			),
			'required'   => array( 'component_type' ),
		);
	}

	/**
	 * Validate component schema parameters.
	 *
	 * @since 7.0.0
	 * @param array $params Tool parameters from MCP client.
	 * @return array Validation result with 'success', 'params', or 'error' keys.
	 */
	public function validate_component_params( array $params ): array {
		$type                     = strtolower( trim( $params['component_type'] ?? '' ) );
		$code                     = trim( $params['code'] ?? '' );
		$id                       = isset( $params['id'] ) ? (int) $params['id'] : 0;
		$include_condition_fields = isset( $params['include_field_schema'] ) ? (bool) $params['include_field_schema'] : true;

		$valid_types = array( self::TYPE_TRIGGER, self::TYPE_ACTION, self::TYPE_CONDITION );
		if ( ! in_array( $type, $valid_types, true ) ) {
			return array(
				'success' => false,
				'error'   => 'Invalid component_type. Expected trigger, action, or condition.',
			);
		}

		// Type-specific validation
		if ( self::TYPE_CONDITION !== $type && '' === $code && $id <= 0 ) {
			return array(
				'success' => false,
				'error'   => sprintf( '%s code or id is required.', ucfirst( $type ) ),
			);
		}

		if ( self::TYPE_CONDITION === $type && '' === $code ) {
			return array(
				'success' => false,
				'error'   => 'Condition code is required.',
			);
		}

		return array(
			'success' => true,
			'params'  => array(
				'type'                     => $type,
				'code'                     => $code,
				'id'                       => $id,
				'include_condition_fields' => $include_condition_fields,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$validation = $this->validate_component_params( $params );
		if ( ! $validation['success'] ) {
			return Json_Rpc_Response::create_error_response( $validation['error'] );
		}

		$validated = $validation['params'];

		switch ( $validated['type'] ) {
			case self::TYPE_TRIGGER:
				return $this->handle_trigger_request( $validated['code'], $validated['id'] );

			case self::TYPE_ACTION:
				return $this->handle_action_request( $validated['code'], $validated['id'] );

			case self::TYPE_CONDITION:
				return $this->handle_condition_request( $validated['code'], $validated['include_condition_fields'] );
		}

		return array();
	}

	/**
	 * Handle trigger component request.
	 *
	 * @since 7.0.0
	 * @param string $code Trigger code.
	 * @param int    $id   Trigger instance ID.
	 * @return array JSON-RPC response.
	 */
	public function handle_trigger_request( string $code, int $id ): array {
		if ( $id > 0 ) {
			$result = Trigger_CRUD_Service::instance()->get_trigger( $id );
			if ( is_wp_error( $result ) ) {
				return Json_Rpc_Response::create_error_response( $result->get_error_message() );
			}

			return $this->build_success_response(
				self::TYPE_TRIGGER,
				$result['trigger'],
				array(
					'source'     => 'database',
					'trigger_id' => $id,
				)
			);
		}

		if ( '' === $code ) {
			return Json_Rpc_Response::create_error_response( 'Trigger code is required when trigger_id is not supplied.' );
		}

		$result = Trigger_Registry_Service::get_instance()->get_trigger_definition( $code, true );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$definition = $result['trigger'] ?? array();

		if ( ! empty( $definition['integration'] ) ) {
			$availability_input = array(
				'integration_id' => $definition['integration'],
				'code'           => $code,
				'required_tier'  => $definition['required_tier'] ?? 'lite',
			);

			$definition['availability'] = Trigger_Registry_Service::get_instance()->check_trigger_integration_availability(
				$availability_input
			);
		}

		return $this->build_success_response(
			self::TYPE_TRIGGER,
			$definition,
			array(
				'source'       => 'registry',
				'trigger_code' => $code,
			)
		);
	}

	/**
	 * Handle action component request.
	 *
	 * @since 7.0.0
	 * @param string $code Action code.
	 * @param int    $id   Action instance ID.
	 * @return array JSON-RPC response.
	 */
	public function handle_action_request( string $code, int $id ): array {
		if ( $id > 0 ) {
			$result = Action_CRUD_Service::instance()->get_action( $id );
			if ( is_wp_error( $result ) ) {
				return Json_Rpc_Response::create_error_response( $result->get_error_message() );
			}

			return $this->build_success_response(
				self::TYPE_ACTION,
				$result['action'],
				array(
					'source'    => 'database',
					'action_id' => $id,
				)
			);
		}

		if ( '' === $code ) {
			return Json_Rpc_Response::create_error_response( 'Action code is required when action_id is not supplied.' );
		}

		$result = Action_Registry_Service::instance()->get_action_definition( $code, true );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return $this->build_success_response(
			self::TYPE_ACTION,
			$result,
			array(
				'source'      => 'registry',
				'action_code' => $code,
			)
		);
	}

	/**
	 * Handle condition component request.
	 *
	 * @since 7.0.0
	 * @param string $code Condition code.
	 * @param bool   $include_schema Whether to include field schema.
	 * @return array JSON-RPC response.
	 */
	public function handle_condition_request( string $code, bool $include_schema ): array {
		if ( '' === $code ) {
			return Json_Rpc_Response::create_error_response( 'Condition code is required.' );
		}

		$registry_service = Condition_Registry_Service::get_instance();

		// Resolve integration code via service layer
		$integration_code = $registry_service->get_integration_by_condition_code( $code );
		if ( is_wp_error( $integration_code ) ) {
			return Json_Rpc_Response::create_error_response( $integration_code->get_error_message() );
		}

		$result = $registry_service->get_condition_definition( $integration_code, $code );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$condition = $result['condition'] ?? array();

		if ( $include_schema ) {
			$field_schema              = $registry_service->get_condition_field_schema( $integration_code, $code );
			$condition['field_schema'] = $field_schema;
			$condition['has_fields']   = ! empty( $field_schema );
		}

		$availability = $registry_service->check_condition_integration_availability(
			array(
				'integration_id' => $integration_code,
				'code'           => $code,
			)
		);

		return $this->build_success_response(
			self::TYPE_CONDITION,
			array(
				'integration_code' => $integration_code,
				'definition'       => $condition,
				'availability'     => $availability,
			),
			array(
				'source'           => 'registry',
				'condition_code'   => $code,
				'integration_code' => $integration_code,
			)
		);
	}

	/**
	 * Build standardized success response.
	 *
	 * @since 7.0.0
	 * @param string $type Component type.
	 * @param array  $component Component data.
	 * @param array  $meta Additional metadata.
	 * @return array JSON-RPC success response.
	 */
	public function build_success_response( string $type, array $component, array $meta = array() ): array {
		return Json_Rpc_Response::create_success_response(
			sprintf( 'Component schema retrieved for %s', $type ),
			array(
				'component_type' => $type,
				'component'      => $component,
				'meta'           => $meta,
			)
		);
	}
}
