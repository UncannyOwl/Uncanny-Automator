<?php
/**
 * MCP router tool for deleting any recipe component.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;
use Uncanny_Automator\Api\Services\User_Selector\User_Selector_Service;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;

/**
 * Delete Recipe Component Tool.
 *
 * A single router tool that deletes any recipe component type: trigger, action,
 * loop, loop_filter, condition_group, condition_item, or user_selector.
 *
 * @since 7.0.0
 */
class Delete_Recipe_Component_Tool extends Abstract_MCP_Tool {

	/**
	 * Supported component types.
	 *
	 * @var string[]
	 */
	private const SUPPORTED_TYPES = array(
		'trigger',
		'action',
		'loop',
		'loop_filter',
		'condition_group',
		'condition_item',
		'user_selector',
	);

	/**
	 * @var Trigger_CRUD_Service
	 */
	private $trigger_service;

	/**
	 * @var Action_CRUD_Service
	 */
	private $action_service;

	/**
	 * @var Loop_CRUD_Service
	 */
	private $loop_service;

	/**
	 * @var Filter_CRUD_Service
	 */
	private $filter_service;

	/**
	 * @var Recipe_Condition_Service
	 */
	private $condition_service;

	/**
	 * @var User_Selector_Service
	 */
	private $user_selector_service;

	/**
	 * Constructor.
	 *
	 * @param Trigger_CRUD_Service|null    $trigger_service       Optional trigger service.
	 * @param Action_CRUD_Service|null     $action_service        Optional action service.
	 * @param Loop_CRUD_Service|null       $loop_service          Optional loop service.
	 * @param Filter_CRUD_Service|null     $filter_service        Optional filter service.
	 * @param Recipe_Condition_Service|null $condition_service     Optional condition service.
	 * @param User_Selector_Service|null   $user_selector_service Optional user selector service.
	 */
	public function __construct(
		?Trigger_CRUD_Service $trigger_service = null,
		?Action_CRUD_Service $action_service = null,
		?Loop_CRUD_Service $loop_service = null,
		?Filter_CRUD_Service $filter_service = null,
		?Recipe_Condition_Service $condition_service = null,
		?User_Selector_Service $user_selector_service = null
	) {
		$this->trigger_service       = $trigger_service;
		$this->action_service        = $action_service;
		$this->loop_service          = $loop_service;
		$this->filter_service        = $filter_service;
		$this->condition_service     = $condition_service;
		$this->user_selector_service = $user_selector_service;
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'delete_recipe_component';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Delete a component from a recipe. Supported types and their side-effects:'
			. "\n- trigger: Permanently deletes the trigger post. The recipe will no longer fire on this event."
			. "\n- action: Permanently deletes the action post and its configuration."
			. "\n- loop: Deletes the loop and all its filters. Actions inside the loop are moved back to the recipe (not deleted)."
			. "\n- loop_filter: Deletes a single filter from a loop. The loop and its actions are unaffected."
			. "\n- condition_group: Removes the entire condition group including all its condition rules. Actions assigned to the group become unconditional (they will always run)."
			. "\n- condition_item: Removes one condition rule from a group. The group and its other rules remain. Requires group_id."
			. "\n- user_selector: Clears user selector settings (source type, matching criteria, fallback, user data). Actions in anonymous recipes will have no user context."
			. "\n\nUse get_recipe to find component IDs before deleting.";
	}

	/**
	 * Define the input schema.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'component_id' => array(
					'type'        => 'string',
					'description' => 'The component identifier. Post ID (as string) for trigger, action, loop, loop_filter. UUID for condition_group or condition_item. Ignored for user_selector (uses recipe_id).',
					'minLength'   => 1,
				),
				'type'         => array(
					'type'        => 'string',
					'description' => 'The type of component to delete. loop: also deletes all filters; child actions are reparented to recipe. condition_group: removes the group and all its rules; assigned actions become unconditional.',
					'enum'        => self::SUPPORTED_TYPES,
				),
				'recipe_id'    => array(
					'type'        => 'integer',
					'description' => 'The parent recipe ID.',
					'minimum'     => 1,
				),
				'group_id'     => array(
					'type'        => 'string',
					'description' => 'Required only when type=condition_item. The condition group UUID containing the condition to remove.',
					'minLength'   => 1,
				),
			),
			'required'   => array( 'component_id', 'type', 'recipe_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters.
	 * @return array MCP response.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$component_id = $params['component_id'] ?? '';
		$type         = $params['type'] ?? '';
		$recipe_id    = (int) ( $params['recipe_id'] ?? 0 );
		$group_id     = $params['group_id'] ?? '';

		// Validate type.
		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Invalid type "%s". Must be one of: %s', $type, implode( ', ', self::SUPPORTED_TYPES ) )
			);
		}

		// Validate recipe exists and is a recipe post type.
		$recipe_post = get_post( $recipe_id );
		if ( ! $recipe_post || 'uo-recipe' !== $recipe_post->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe not found with ID %d. Use list_recipes to find valid recipe IDs.', $recipe_id )
			);
		}

		// Validate group_id for condition_item.
		if ( 'condition_item' === $type && empty( $group_id ) ) {
			return Json_Rpc_Response::create_error_response(
				'group_id is required when type=condition_item. Use list_conditions to find the group_id.'
			);
		}

		// Route to the appropriate handler.
		switch ( $type ) {
			case 'trigger':
				return $this->delete_trigger( (int) $component_id, $recipe_id );
			case 'action':
				return $this->delete_action( (int) $component_id, $recipe_id );
			case 'loop':
				return $this->delete_loop( (int) $component_id, $recipe_id );
			case 'loop_filter':
				return $this->delete_loop_filter( (int) $component_id, $recipe_id );
			case 'condition_group':
				return $this->delete_condition_group( $component_id, $recipe_id );
			case 'condition_item':
				return $this->delete_condition_item( $component_id, $group_id, $recipe_id );
			case 'user_selector':
				return $this->delete_user_selector( $recipe_id );
			default:
				return Json_Rpc_Response::create_error_response( 'Unsupported component type.' );
		}
	}

	/**
	 * Delete a trigger from a recipe.
	 *
	 * @param int $component_id Trigger post ID.
	 * @param int $recipe_id    Recipe ID.
	 * @return array MCP response.
	 */
	private function delete_trigger( int $component_id, int $recipe_id ): array {
		$result = $this->get_trigger_service()->remove_from_recipe( $recipe_id, $component_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			'Trigger deleted successfully.',
			array(
				'trigger_id' => $component_id,
				'recipe_id'  => $recipe_id,
				'links'      => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}

	/**
	 * Delete an action from a recipe.
	 *
	 * @param int $component_id Action post ID.
	 * @param int $recipe_id    Recipe ID.
	 * @return array MCP response.
	 */
	private function delete_action( int $component_id, int $recipe_id ): array {
		$result = $this->get_action_service()->delete_action( $component_id, true );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			'Action deleted successfully.',
			array(
				'action_id' => $component_id,
				'recipe_id' => $recipe_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}

	/**
	 * Delete a loop from a recipe.
	 *
	 * @param int $component_id Loop post ID.
	 * @param int $recipe_id    Recipe ID.
	 * @return array MCP response.
	 */
	private function delete_loop( int $component_id, int $recipe_id ): array {
		$result = $this->get_loop_service()->delete_loop( $component_id, true );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			'Loop deleted successfully. Actions inside the loop were moved back to the recipe.',
			array(
				'loop_id'   => $component_id,
				'recipe_id' => $recipe_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}

	/**
	 * Delete a loop filter.
	 *
	 * @param int $component_id Filter post ID.
	 * @param int $recipe_id    Recipe ID.
	 * @return array MCP response.
	 */
	private function delete_loop_filter( int $component_id, int $recipe_id ): array {
		$result = $this->get_filter_service()->delete_filter( $component_id, true );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			'Loop filter deleted successfully.',
			array(
				'filter_id' => $component_id,
				'recipe_id' => $recipe_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}

	/**
	 * Delete a condition group from a recipe.
	 *
	 * @param string $component_id Condition group UUID.
	 * @param int    $recipe_id    Recipe ID.
	 * @return array MCP response.
	 */
	private function delete_condition_group( string $component_id, int $recipe_id ): array {
		$result = $this->get_condition_service()->remove_condition_group( $recipe_id, $component_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			'Condition group deleted successfully.',
			array(
				'group_id'  => $component_id,
				'recipe_id' => $recipe_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}

	/**
	 * Delete a single condition from a group.
	 *
	 * @param string $component_id Condition UUID.
	 * @param string $group_id     Condition group UUID.
	 * @param int    $recipe_id    Recipe ID.
	 * @return array MCP response.
	 */
	private function delete_condition_item( string $component_id, string $group_id, int $recipe_id ): array {
		$result = $this->get_condition_service()->remove_condition_from_group( $component_id, $group_id, $recipe_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			'Condition removed from group successfully.',
			array(
				'condition_id' => $component_id,
				'group_id'     => $group_id,
				'recipe_id'    => $recipe_id,
				'links'        => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}

	/**
	 * Delete user selector from a recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array MCP response.
	 */
	private function delete_user_selector( int $recipe_id ): array {
		$result = $this->get_user_selector_service()->delete_user_selector( $recipe_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			'User selector deleted successfully.',
			array(
				'recipe_id' => $recipe_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}

	/**
	 * Get trigger service with lazy initialization.
	 *
	 * @return Trigger_CRUD_Service
	 */
	private function get_trigger_service(): Trigger_CRUD_Service {
		if ( null === $this->trigger_service ) {
			$this->trigger_service = Trigger_CRUD_Service::instance();
		}
		return $this->trigger_service;
	}

	/**
	 * Get action service with lazy initialization.
	 *
	 * @return Action_CRUD_Service
	 */
	private function get_action_service(): Action_CRUD_Service {
		if ( null === $this->action_service ) {
			$this->action_service = Action_CRUD_Service::instance();
		}
		return $this->action_service;
	}

	/**
	 * Get loop service with lazy initialization.
	 *
	 * @return Loop_CRUD_Service
	 */
	private function get_loop_service(): Loop_CRUD_Service {
		if ( null === $this->loop_service ) {
			$this->loop_service = Loop_CRUD_Service::instance();
		}
		return $this->loop_service;
	}

	/**
	 * Get filter service with lazy initialization.
	 *
	 * @return Filter_CRUD_Service
	 */
	private function get_filter_service(): Filter_CRUD_Service {
		if ( null === $this->filter_service ) {
			$this->filter_service = Filter_CRUD_Service::instance();
		}
		return $this->filter_service;
	}

	/**
	 * Get condition service with lazy initialization.
	 *
	 * @return Recipe_Condition_Service
	 */
	private function get_condition_service(): Recipe_Condition_Service {
		if ( null === $this->condition_service ) {
			$this->condition_service = Recipe_Condition_Service::instance();
		}
		return $this->condition_service;
	}

	/**
	 * Get user selector service with lazy initialization.
	 *
	 * @return User_Selector_Service
	 */
	private function get_user_selector_service(): User_Selector_Service {
		if ( null === $this->user_selector_service ) {
			$this->user_selector_service = User_Selector_Service::instance();
		}
		return $this->user_selector_service;
	}
}
