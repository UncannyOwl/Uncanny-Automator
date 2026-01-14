<?php
/**
 * Automator Component Field Options Tool.
 *
 * Retrieves dropdown/options for Automator component fields (actions today).
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Agent_Tools;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

class Get_Field_Options_Tool extends Abstract_MCP_Tool {

	private const TYPE_ACTION  = 'action';
	private const TYPE_TRIGGER = 'trigger';

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
		return 'Get dropdown options for component fields. Use after get_component_schema for fields with supports_custom_value: false. Pass component_code and option_code. For cascading fields, pass parent selection in value parameter. If returns options_available: false, use get_posts or get_terms instead.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'component_type' => array(
					'type'        => 'string',
					'enum'        => array( self::TYPE_ACTION, self::TYPE_TRIGGER ),
					'description' => 'Component type to fetch options for. Supports "action" and "trigger".',
					'default'     => self::TYPE_ACTION,
				),
				'component_code' => array(
					'type'        => 'string',
					'description' => 'Component identifier (e.g., action code or trigger code). Retrieved from get_component_schema or search_components.',
				),
				'option_code'    => array(
					'type'        => 'string',
					'description' => 'Field code to get options for (e.g., WPPOSTTYPES, WPTAXONOMIES).',
				),
				'value'          => array(
					'type'        => 'string',
					'description' => 'Parent selection value for dependent lookups (optional).',
				),
				'context'        => array(
					'type'                 => 'object',
					'description'          => 'Optional additional context for complex dependencies.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'component_code', 'option_code' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$component_type = strtolower( $params['component_type'] ?? self::TYPE_ACTION );

		// Validate component_type is either action or trigger
		if ( ! in_array( $component_type, array( self::TYPE_ACTION, self::TYPE_TRIGGER ), true ) ) {
			return Json_Rpc_Response::create_error_response( 'component_type must be either "action" or "trigger".' );
		}

		$action_code = $params['component_code'] ?? $params['action_code'] ?? null;
		$option_code = $params['option_code'] ?? null;
		$value       = $params['value'] ?? null;
		$context     = $params['context'] ?? array();

		if ( empty( $action_code ) ) {
			return Json_Rpc_Response::create_error_response( 'Missing component_code (action code).' );
		}

		if ( empty( $option_code ) ) {
			return Json_Rpc_Response::create_error_response( 'Missing option_code.' );
		}

		// Route based on component type
		if ( self::TYPE_TRIGGER === $component_type ) {
			return $this->resolve_trigger_options( $action_code, $option_code, $value, $context );
		}

		return $this->resolve_action_options( $action_code, $option_code, $value, $context );
	}

	/**
	 * Resolve dynamic field options for an action component.
	 *
	 * @param string     $action_code Action registry code.
	 * @param string     $option_code Field option identifier.
	 * @param mixed|null $value       Dependent value (when cascading selects are used).
	 * @param array      $context     Optional contextual payload from callers.
	 *
	 * @return array JSON-RPC response.
	 */
	private function resolve_action_options( $action_code, $option_code, $value = null, $context = array() ) {

		$action = Automator()->get_action( $action_code );

		// Validate action and agent.
		if ( empty( $action ) ) {
			return Json_Rpc_Response::create_error_response( 'Unknown action_code: ' . $action_code );
		}

		// Instantiate agent.
		$agent_class = $action['agent_class'] ?? null;
		if ( empty( $agent_class ) || ! class_exists( $agent_class ) ) {
			return Json_Rpc_Response::create_success_response(
				'Field options not available - use get_posts or get_taxonomies',
				array(
					'component_type'        => self::TYPE_ACTION,
					'action_code'           => $action_code,
					'option_code'           => $option_code,
					'options_available'     => false,
					'supports_custom_value' => true,
					'message'               => 'This action is not yet agentified. Use get_posts tool (for products, subscriptions, courses, memberships, etc.) or get_taxonomies tool (for categories, tags, terms) to fetch available options. If those tools return nothing relevant, ask the user to provide the specific value (name, ID, or slug).',
				)
			);
		}

		$agent = new $agent_class();

		if ( ! $agent instanceof Agent_Tools ) {
			return Json_Rpc_Response::create_error_response( 'Handler does not implement Agent_Tools.' );
		}

		$method_name = 'get_' . strtolower( $option_code );
		if ( ! method_exists( $agent, $method_name ) ) {
			return Json_Rpc_Response::create_error_response( 'Unsupported option_code: ' . $option_code );
		}

		try {
			$options = $agent->{$method_name}( $value, $context );
		} catch ( \Throwable $e ) {
			return Json_Rpc_Response::create_error_response( 'Failed to resolve options: ' . $e->getMessage() );
		}

		return Json_Rpc_Response::create_success_response(
			'Options retrieved successfully',
			array(
				'component_type' => self::TYPE_ACTION,
				'action_code'    => $action_code,
				'option_code'    => $option_code,
				'options'        => $options,
			)
		);
	}

	/**
	 * Resolve dynamic field options for a trigger component.
	 *
	 * @param string     $trigger_code Trigger registry code.
	 * @param string     $option_code  Field option identifier.
	 * @param mixed|null $value        Dependent value (when cascading selects are used).
	 * @param array      $context      Optional contextual payload from callers.
	 *
	 * @return array JSON-RPC response.
	 */
	private function resolve_trigger_options( $trigger_code, $option_code, $value = null, $context = array() ) {

		$trigger = Automator()->get_trigger( $trigger_code );

		// Validate trigger and agent.
		if ( empty( $trigger ) ) {
			return Json_Rpc_Response::create_error_response( 'Unknown trigger_code: ' . $trigger_code );
		}

		// Instantiate agent.
		$agent_class = $trigger['agent_class'] ?? null;
		if ( empty( $agent_class ) || ! class_exists( $agent_class ) ) {
			return Json_Rpc_Response::create_success_response(
				'Field options not available - use get_posts or get_taxonomies',
				array(
					'component_type'        => self::TYPE_TRIGGER,
					'trigger_code'          => $trigger_code,
					'option_code'           => $option_code,
					'options_available'     => false,
					'supports_custom_value' => true,
					'message'               => 'This trigger is not yet agentified. Use get_posts tool (for products, subscriptions, courses, memberships, etc.) or get_taxonomies tool (for categories, tags, terms) to fetch available options. If those tools return nothing relevant, ask the user to provide the specific value (name, ID, or slug).',
				)
			);
		}

		$agent = new $agent_class();

		if ( ! $agent instanceof Agent_Tools ) {
			return Json_Rpc_Response::create_error_response( 'Handler does not implement Agent_Tools.' );
		}

		$method_name = 'get_' . strtolower( $option_code );
		if ( ! method_exists( $agent, $method_name ) ) {
			return Json_Rpc_Response::create_error_response( 'Unsupported option_code: ' . $option_code );
		}

		try {
			$options = $agent->{$method_name}( $value, $context );
		} catch ( \Throwable $e ) {
			return Json_Rpc_Response::create_error_response( 'Failed to resolve options: ' . $e->getMessage() );
		}

		return Json_Rpc_Response::create_success_response(
			'Options retrieved successfully',
			array(
				'component_type' => self::TYPE_TRIGGER,
				'trigger_code'   => $trigger_code,
				'option_code'    => $option_code,
				'options'        => $options,
			)
		);
	}
}
