<?php
/**
 * Automator Component Schema Tool.
 *
 * Unified MCP tool for retrieving trigger, action, and condition schemas.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Action_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Recipe_Builder\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Catalog;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Action_Availability_Policy;
use WP_Ability;

/**
 * Automator component schema tool.
 *
 * @since 7.0.0
 */
class Get_Component_Schema_Tool extends Abstract_MCP_Tool {

	private const TYPE_TRIGGER   = 'trigger';
	private const TYPE_ACTION    = 'action';
	private const TYPE_CONDITION = 'condition';
	private const TYPE_ABILITY   = 'ability';

	private const TYPE_LOOP_FILTER = 'loop_filter';

	/**
	 * Site-owner switches and the banned list.
	 *
	 * @var Action_Availability_Policy
	 */
	private Action_Availability_Policy $availability;

	/**
	 * WordPress abilities the site owner switched on.
	 *
	 * @var Ability_Catalog
	 */
	private Ability_Catalog $abilities;

	/**
	 * @param Action_Availability_Policy|null $availability Availability policy; defaults to the stored switches.
	 * @param Ability_Catalog|null            $abilities    Ability catalog; defaults to the stored switches.
	 */
	public function __construct( ?Action_Availability_Policy $availability = null, ?Ability_Catalog $abilities = null ) {
		$this->availability = $availability ?? new Action_Availability_Policy();
		$this->abilities    = $abilities ?? new Ability_Catalog();
	}

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
		return 'Get field schema for a trigger, action, condition, or loop filter by code or instance ID. '
			. 'Shows required fields, types, and defaults. If a field has supports_custom_value: false, '
			. 'use get_field_options to resolve its dropdown choices. '
			. 'If a field or repeater sub-field is readOnly, copy the provided value exactly; do not rewrite or overwrite it. '
			. 'Note: Required fields may have token-based defaults (e.g., {{admin_email}}). '
			. 'For one-time executions, replace these tokens with absolute values; for recipes, tokens are resolved at runtime. '
			. 'For a WordPress ability switched on for AI apps, pass component_type "ability" and the ability name as code; the result carries its inputSchema and outputSchema for run_ability.';
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
					'enum'        => array( self::TYPE_TRIGGER, self::TYPE_ACTION, self::TYPE_CONDITION, self::TYPE_LOOP_FILTER, self::TYPE_ABILITY ),
					'description' => 'Component type to retrieve (trigger, action, condition, loop_filter, or ability). Condition schemas feed save_filter_condition on a filter block. Loop filter schemas feed save_loop_filter, where each field is {value, label}.',
				),
				'code'                 => array(
					'type'        => 'string',
					'description' => 'Component code returned by the catalog (e.g., "LOGIN", "SENDEMAIL"), or the ability name for component_type "ability" (e.g., "woocommerce/products-query"). Required unless retrieving a trigger/action instance by ID.',
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
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'component_type' => array( 'type' => 'string' ),
				'component'      => array( 'type' => 'object' ),
				'meta'           => array(
					'type'       => 'object',
					'properties' => array(
						'source'           => array( 'type' => 'string' ),
						'trigger_code'     => array( 'type' => 'string' ),
						'trigger_id'       => array( 'type' => 'integer' ),
						'action_code'      => array( 'type' => 'string' ),
						'action_id'        => array( 'type' => 'integer' ),
						'condition_code'   => array( 'type' => 'string' ),
						'filter_code'      => array( 'type' => 'string' ),
						'integration_code' => array( 'type' => 'string' ),
						'ability_name'     => array( 'type' => 'string' ),
					),
				),
			),
			'required'   => array( 'component_type', 'component', 'meta' ),
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

		$valid_types = array( self::TYPE_TRIGGER, self::TYPE_ACTION, self::TYPE_CONDITION, self::TYPE_LOOP_FILTER, self::TYPE_ABILITY );
		if ( ! in_array( $type, $valid_types, true ) ) {
			return array(
				'success' => false,
				'error'   => 'Invalid component_type. Expected trigger, action, condition, loop_filter, or ability.',
			);
		}

		if ( self::TYPE_ABILITY === $type && '' === $code ) {
			return array(
				'success' => false,
				'error'   => 'Ability name is required as code.',
			);
		}

		if ( self::TYPE_LOOP_FILTER === $type && '' === $code ) {
			return array(
				'success' => false,
				'error'   => 'Loop filter code is required.',
			);
		}

		// Type-specific validation
		if ( self::TYPE_CONDITION !== $type && self::TYPE_ABILITY !== $type && '' === $code && $id <= 0 ) {
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
				// A schema is only useful to a caller who may save or run the action.
				if ( '' !== $validated['code'] && $validated['id'] <= 0 && ! $this->availability->is_available( $validated['code'], $user_context ) ) {
					return Json_Rpc_Response::create_error_response( $this->availability->get_refusal_message( $validated['code'] ) );
				}

				return $this->handle_action_request( $validated['code'], $validated['id'] );

			case self::TYPE_CONDITION:
				return $this->handle_condition_request( $validated['code'], $validated['include_condition_fields'] );

			case self::TYPE_LOOP_FILTER:
				return $this->handle_loop_filter_request( $validated['code'] );

			case self::TYPE_ABILITY:
				return $this->handle_ability_request( $validated['code'], $user_context );
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

		$result = Trigger_Registry_Service::instance()->get_trigger_definition( $code, true );

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

			$definition['availability'] = Trigger_Registry_Service::instance()->check_trigger_integration_availability(
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

		$registry_service = Condition_Registry_Service::instance();

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
	 * One loop filter this site has, with the fields save_loop_filter takes.
	 *
	 * @since 8.0.0
	 *
	 * @param string $code Loop filter code.
	 * @return array JSON-RPC response.
	 */
	public function handle_loop_filter_request( string $code ): array {
		$definition = Filter_Registry_Service::instance()->get_filter_definition( $code );

		if ( ! is_array( $definition ) ) {
			return Json_Rpc_Response::create_error_response( sprintf( 'Loop filter %s is not available on this site. Its integration may not be active.', $code ) );
		}

		$integration_code = (string) ( $definition['integration_code'] ?? $definition['integration'] ?? '' );
		$fields           = Field_Registry_Service::instance()->get_block_modifier_fields( $integration_code, Block_Modifier_Types::LOOP_FILTER, $code );

		if ( is_wp_error( $fields ) ) {
			return Json_Rpc_Response::create_error_response( $fields->get_error_message() );
		}

		return $this->build_success_response(
			self::TYPE_LOOP_FILTER,
			array(
				'integration_code' => $integration_code,
				'sentence'         => (string) ( $definition['sentence_readable'] ?? $definition['sentence'] ?? $code ),
				'iteration_types'  => $definition['iteration_types'] ?? array(),
				'fields'           => $fields,
			),
			array(
				'source'           => 'registry',
				'filter_code'      => $code,
				'integration_code' => $integration_code,
			)
		);
	}

	/**
	 * One switched-on WordPress ability with its schemas, for Agent and connected AI apps.
	 *
	 * @param string       $name         Ability name.
	 * @param User_Context $user_context Caller.
	 * @return array
	 */
	public function handle_ability_request( string $name, User_Context $user_context ): array {
		$ability = $this->abilities->find_enabled( $name );

		if ( ! $ability instanceof WP_Ability ) {
			return Json_Rpc_Response::create_error_response( $this->abilities->refusal_for( $name ) );
		}

		return $this->build_success_response(
			self::TYPE_ABILITY,
			$this->abilities->schema_for( $ability ),
			array(
				'source'       => 'abilities_api',
				'ability_name' => $name,
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
