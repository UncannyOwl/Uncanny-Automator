<?php
/**
 * Consolidated delete tool.
 *
 * Replaces: delete_recipe_component, remove_condition, loop_delete,
 *           loop_filter_delete, loop_filter_delete_all, delete_user_selector.
 *
 * Routes deletion by component type. Adds all_loop_filters support
 * beyond what delete_recipe_component handled.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Filter_Condition_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Runnability_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\User_Selector_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Delete Tool.
 *
 * Deletes any recipe component by type + ID. component_id is a string
 * carrying the post ID (trigger/action/loop/loop_filter/block/filter_condition).
 *
 * @since 7.1.0
 */
class Delete_Tool extends Abstract_MCP_Tool {

	private const SUPPORTED_TYPES = array(
		'trigger',
		'action',
		'loop',
		'loop_filter',
		'block',
		'filter_condition',
		'all_loop_filters',
		'user_selector',
	);

	/**
	 * Component types whose component_id is a WordPress post ID.
	 *
	 * These are delete tool component type strings, not WordPress post type names.
	 */
	private const POST_ID_TYPES = array(
		'trigger',
		'action',
		'loop',
		'loop_filter',
		'block',
		'filter_condition',
		'all_loop_filters',
	);

	/** WordPress post type required by each post-backed dispatch target. */
	private const EXPECTED_POST_TYPES = array(
		'trigger'          => AUTOMATOR_POST_TYPE_TRIGGER,
		'action'           => AUTOMATOR_POST_TYPE_ACTION,
		'loop'             => AUTOMATOR_POST_TYPE_BLOCK,
		'loop_filter'      => AUTOMATOR_POST_TYPE_BLOCK_MODIFIER,
		'all_loop_filters' => AUTOMATOR_POST_TYPE_BLOCK,
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'delete';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Delete a component from a recipe. Supported types: '
			. 'trigger, action, loop (also deletes filters; by default the steps inside move to the loop\'s position, or are deleted if delete_content is true), '
			. 'loop_filter, block (filter/delay_schedule; by default the steps inside move to the block\'s position, or are deleted if delete_content is true; conditions are always deleted; in both modes a step that cannot be used at its new position is removed), '
			. 'filter_condition (one condition off a filter block), '
			. 'all_loop_filters (deletes all filters from a loop), '
			. 'user_selector (clears user selector config). '
			. 'For loop and block, a call without confirm=true deletes nothing and returns what the delete would remove (steps that cannot be used at their new position, with the reason); show that to the user, then call again with confirm=true. '
			. 'Set confirm=true for all_loop_filters and user_selector. Use get_recipe to find component IDs when that root tool is listed; otherwise use rest_discover and rest_request for the native automator/get-recipe operation.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'idempotentHint'  => true,
			'openWorldHint'   => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'type'           => array(
					'type'        => 'string',
					'description' => 'Component type to delete.',
					'enum'        => self::SUPPORTED_TYPES,
				),
				'component_id'   => array(
					'type'        => 'string',
					'description' => 'Component identifier. Required for all types except user_selector. Post ID (as string) for trigger/action/loop/loop_filter/block/filter_condition. Loop ID for all_loop_filters.',
				),
				'recipe_id'      => array(
					'type'        => 'integer',
					'description' => 'Parent recipe ID.',
					'minimum'     => 1,
				),
				'confirm'        => array(
					'type'        => 'boolean',
					'description' => 'Required for destructive operations (all_loop_filters, loop, block, user_selector). For loop and block, omitting it returns a preview of what would be removed and deletes nothing.',
					'default'     => false,
				),
				'delete_content' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'For type "block" and "loop": true deletes the steps inside with it; false (the default) keeps them and moves them to its position.',
				),
			),
			'required'   => array( 'type', 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array( 'type' => 'integer' ),
				'loop_id'   => array( 'type' => 'integer' ),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$type           = $params['type'] ?? '';
		$component_id   = $params['component_id'] ?? '';
		$recipe_id      = (int) ( $params['recipe_id'] ?? 0 );
		$confirm        = ! empty( $params['confirm'] );
		$delete_content = ! empty( $params['delete_content'] );

		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Invalid type "%s". Must be one of: %s', $type, implode( ', ', self::SUPPORTED_TYPES ) )
			);
		}

		// Validate recipe exists.
		$recipe_post = get_post( $recipe_id );
		if ( ! $recipe_post || AUTOMATOR_POST_TYPE_RECIPE !== $recipe_post->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe not found with ID %d. Use list_recipes to find valid recipe IDs.', $recipe_id )
			);
		}

		// component_id required for all types except user_selector.
		if ( 'user_selector' !== $type && empty( $component_id ) ) {
			return Json_Rpc_Response::create_error_response( 'component_id is required for type=' . $type . '.' );
		}

		// Validate confirm for destructive operations.
		// loop and block preview instead of refusing, once ownership is known.
		if ( in_array( $type, array( 'all_loop_filters', 'user_selector' ), true ) && ! $confirm ) {
			return Json_Rpc_Response::create_error_response( 'confirm=true is required for this destructive operation.' );
		}

		if ( in_array( $type, self::POST_ID_TYPES, true ) ) {
			$post_id_validation = $this->validate_post_component_id( (string) $component_id, $type );
			if ( null !== $post_id_validation ) {
				return $post_id_validation;
			}
		}

		switch ( $type ) {
			case 'trigger':
				return $this->delete_trigger( (int) $component_id, $recipe_id );
			case 'action':
				return $this->delete_action( (int) $component_id, $recipe_id );
			case 'loop':
				return $this->delete_loop( (int) $component_id, $recipe_id, $delete_content, $confirm );
			case 'loop_filter':
				return $this->delete_loop_filter( (int) $component_id, $recipe_id );
			case 'all_loop_filters':
				return $this->delete_all_loop_filters( (int) $component_id, $recipe_id );
			case 'block':
				return $this->delete_block( (int) $component_id, $recipe_id, $delete_content, $confirm );
			case 'filter_condition':
				return $this->delete_filter_condition( (int) $component_id, $recipe_id );
			case 'user_selector':
				return $this->delete_user_selector( $recipe_id );
			default:
				return Json_Rpc_Response::create_error_response( 'Unsupported component type.' );
		}
	}

	/**
	 * Validate post-backed component IDs before casting.
	 *
	 * @param string $component_id Component ID from params.
	 * @param string $type         Component type.
	 * @return array|null Error response if invalid, null if valid.
	 */
	private function validate_post_component_id( string $component_id, string $type ): ?array {
		if ( 1 !== preg_match( '/^[0-9]+$/D', $component_id ) || (int) $component_id <= 0 ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'component_id must be a positive integer string for type=%s.', $type )
			);
		}

		return null;
	}

	/**
	 * Delete trigger.
	 *
	 * @param int $trigger_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_trigger( int $trigger_id, int $recipe_id ): array {
		$ownership = $this->verify_component_ownership( $trigger_id, $recipe_id, 'trigger' );
		if ( null !== $ownership ) {
			return $ownership;
		}
		$result = Trigger_CRUD_Service::instance()->remove_from_recipe( $recipe_id, $trigger_id );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		$demoted = Recipe_Runnability_Service::instance()->demote_if_empty( $recipe_id );
		$msg     = $demoted ? 'Trigger deleted. Recipe demoted to draft.' : 'Trigger deleted';
		return Json_Rpc_Response::create_success_response( $msg, array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete action.
	 *
	 * @param int $action_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_action( int $action_id, int $recipe_id ): array {
		$ownership = $this->verify_component_ownership( $action_id, $recipe_id, 'action' );
		if ( null !== $ownership ) {
			return $ownership;
		}
		$result = Action_CRUD_Service::instance()->delete_action( $action_id, true );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		// delete_action() demotes the recipe itself when it empties out.
		return Json_Rpc_Response::create_success_response( 'Action deleted', array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete loop.
	 *
	 * @param int  $loop_id        The ID.
	 * @param int  $recipe_id      The ID.
	 * @param bool $delete_content Whether to delete the steps inside the loop.
	 * @param bool $confirm        False returns a preview and deletes nothing.
	 *
	 * @return array
	 */
	private function delete_loop( int $loop_id, int $recipe_id, bool $delete_content = false, bool $confirm = false ): array {
		$ownership = $this->verify_component_ownership( $loop_id, $recipe_id, 'loop' );
		if ( null !== $ownership ) {
			return $ownership;
		}
		if ( ! $confirm ) {
			return $this->delete_preview( 'loop', $loop_id, $recipe_id, $delete_content );
		}
		$service = Loop_CRUD_Service::instance();
		$result  = $service->delete_loop( $loop_id, true, $delete_content );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$message = $delete_content
			? 'Loop deleted, along with the steps inside it.'
			: 'Loop deleted. The steps inside it moved to the loop\'s position; any that cannot be used there were removed.';

		return Json_Rpc_Response::create_success_response( $message, array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete loop filter.
	 *
	 * @param int $filter_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_loop_filter( int $filter_id, int $recipe_id ): array {
		$ownership = $this->verify_component_ownership( $filter_id, $recipe_id, 'loop_filter' );
		if ( null !== $ownership ) {
			return $ownership;
		}
		$service = Filter_CRUD_Service::instance();
		$result  = $service->delete_filter( $filter_id, true );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response( 'Loop filter deleted', array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete all loop filters.
	 *
	 * @since 7.2.4 Enforces loop-to-recipe ownership before bulk filter deletion.
	 *
	 * @param int $loop_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_all_loop_filters( int $loop_id, int $recipe_id ): array {
		$ownership = $this->verify_component_ownership( $loop_id, $recipe_id, 'all_loop_filters' );
		if ( null !== $ownership ) {
			return $ownership;
		}
		$service = Filter_CRUD_Service::instance();
		$result  = $service->delete_loop_filters( $loop_id, true );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response(
			'All loop filters deleted',
			array(
				'recipe_id' => $recipe_id,
				'loop_id'   => $loop_id,
			)
		);
	}
	/**
	 * Delete a block (filter or delay_schedule).
	 *
	 * By default, child actions re-parent to the block's parent; set
	 * $delete_content to true to delete them instead. The block's own
	 * filter conditions are deleted with it. Ownership rides the store's
	 * recipe_id link (blocks can nest, so post_parent isn't enough).
	 *
	 * @param int  $block_id       The ID.
	 * @param int  $recipe_id      The ID.
	 * @param bool $delete_content Whether to delete the steps inside the block.
	 * @param bool $confirm        False returns a preview and deletes nothing.
	 *
	 * @return array
	 */
	private function delete_block( int $block_id, int $recipe_id, bool $delete_content = false, bool $confirm = false ): array {
		if ( is_wp_error( Block_Crud_Service::instance()->verify_block_recipe( $block_id, $recipe_id ) ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Block %d does not belong to recipe %d.', $block_id, $recipe_id )
			);
		}
		if ( ! $confirm ) {
			return $this->delete_preview( 'block', $block_id, $recipe_id, $delete_content );
		}
		$result = Block_Crud_Service::instance()->delete_block( $block_id, $delete_content );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$message = $delete_content
			? 'Block deleted, along with the steps inside it.'
			: 'Block deleted. The steps inside it moved to the block\'s position; any that cannot be used there were removed.';

		return Json_Rpc_Response::create_success_response( $message, array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * What a block or loop delete would remove, before anything is deleted —
	 * the MCP counterpart of the builder's confirmation dialog, so an agent
	 * can show the user which steps go and why, then call again to confirm.
	 *
	 * @param string $label          'block' or 'loop', for the message.
	 * @param int    $block_id       Block or loop post ID (loops are block rows).
	 * @param int    $recipe_id      Recipe ID, already verified as the owner.
	 * @param bool   $delete_content Whether the delete would take every step inside.
	 *
	 * @return array
	 */
	private function delete_preview( string $label, int $block_id, int $recipe_id, bool $delete_content ): array {
		$plan = Block_Crud_Service::instance()->plan_delete( $block_id, $delete_content );
		if ( is_wp_error( $plan ) ) {
			return Json_Rpc_Response::create_error_response( $plan->get_error_message() );
		}

		$lines = array();
		foreach ( $plan['would_remove'] as $step ) {
			$lines[] = sprintf(
				'%s %d (%s): %s',
				'block' === $step['entity_type'] ? 'Block' : 'Action',
				$step['id'],
				$step['code'],
				'' !== $step['reason'] ? $step['reason'] : 'it cannot be used at its new position'
			);
		}

		if ( $delete_content ) {
			$message = sprintf( 'Nothing has been deleted yet. This deletes the %1$s and the %2$d action(s) inside it. Show this to the user, then call again with confirm=true to proceed.', $label, $plan['step_count'] );
		} elseif ( empty( $lines ) ) {
			$message = sprintf( 'Nothing has been deleted yet. This deletes the %1$s and moves its %2$d step(s) to its position. Call again with confirm=true to proceed.', $label, $plan['step_count'] );
		} else {
			$message = sprintf( 'Nothing has been deleted yet. This deletes the %1$s and moves its steps to its position, except these, which cannot be used there and would be deleted: %2$s. Show this to the user, then call again with confirm=true to proceed.', $label, implode( '; ', $lines ) );
		}

		return Json_Rpc_Response::create_success_response(
			$message,
			array(
				'recipe_id'             => $recipe_id,
				'requires_confirmation' => true,
				'would_remove'          => $plan['would_remove'],
			)
		);
	}

	/**
	 * Delete a single filter condition off a filter block.
	 *
	 * Ownership resolves through the condition's parent block to the
	 * store's recipe_id link.
	 *
	 * @param int $condition_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_filter_condition( int $condition_id, int $recipe_id ): array {
		$condition = Filter_Condition_Crud_Service::instance()->get_condition( $condition_id );
		if ( is_wp_error( $condition ) ) {
			return Json_Rpc_Response::create_error_response( $condition->get_error_message() );
		}
		$block = Block_Crud_Service::instance()->get_block( (int) $condition['block_id'] );
		if ( is_wp_error( $block ) || (int) ( $block['recipe_id'] ?? 0 ) !== $recipe_id ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Filter condition %d does not belong to recipe %d.', $condition_id, $recipe_id )
			);
		}
		$result = Filter_Condition_Crud_Service::instance()->delete_condition( $condition_id );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response( 'Filter condition deleted', array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete user selector.
	 *
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_user_selector( int $recipe_id ): array {
		$service = User_Selector_Service::instance();
		$result  = $service->delete_user_selector( $recipe_id );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response( 'User selector cleared', array( 'recipe_id' => $recipe_id ) );
	}

	/**
	 * Verify a component (post) belongs to the specified recipe.
	 *
	 * Walks up the post_parent chain to confirm the component is a
	 * direct or indirect child of the recipe. Prevents cross-recipe
	 * modifications.
	 *
	 * @since 7.1.0
	 *
	 * @param int    $component_id   Component post ID.
	 * @param int    $recipe_id      Expected parent recipe ID.
	 * @param string $component_type Declared delete component type.
	 * @return array|null Error response if ownership fails, null if OK.
	 */
	private function verify_component_ownership( int $component_id, int $recipe_id, string $component_type ) {
		$post = get_post( $component_id );
		if ( ! $post ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Component %d not found.', $component_id )
			);
		}

		$expected_post_type = self::EXPECTED_POST_TYPES[ $component_type ] ?? '';
		if ( '' === $expected_post_type || $expected_post_type !== $post->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf(
					'Component %d is post type %s, not the post type required for type=%s.',
					$component_id,
					$post->post_type,
					$component_type
				)
			);
		}

		if ( in_array( $component_type, array( 'loop', 'all_loop_filters' ), true ) ) {
			$loop = Block_Crud_Service::instance()->get_block( $component_id );
			if ( is_wp_error( $loop ) || 'loop' !== ( $loop['block_type'] ?? '' ) || (int) ( $loop['recipe_id'] ?? 0 ) !== $recipe_id ) {
				return Json_Rpc_Response::create_error_response( sprintf( 'Component %d is not a loop belonging to recipe %d.', $component_id, $recipe_id ) );
			}
			return null;
		}

		if ( 'loop_filter' === $component_type ) {
			$modifier = Database::get_block_modifier_store()->get( $component_id );
			if ( null === $modifier || 'loop_filter' !== ( $modifier['block_modifier_type'] ?? '' ) ) {
				return Json_Rpc_Response::create_error_response( sprintf( 'Component %d is not a loop filter.', $component_id ) );
			}
		}

		// Resolve the owning recipe. A container parent (uo-block, any
		// nesting depth) resolves through the block store's recipe_id link.
		$parent_id = (int) $post->post_parent;
		$parent    = $parent_id > 0 ? get_post( $parent_id ) : null;

		if ( $parent && AUTOMATOR_POST_TYPE_BLOCK === $parent->post_type ) {
			$block     = Block_Crud_Service::instance()->get_block( $parent_id );
			$parent_id = is_wp_error( $block ) ? 0 : (int) ( $block['recipe_id'] ?? 0 );
		} elseif ( $parent && AUTOMATOR_POST_TYPE_RECIPE !== $parent->post_type ) {
			$parent_id = (int) $parent->post_parent;
		}

		if ( $parent_id !== $recipe_id ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Component %d does not belong to recipe %d.', $component_id, $recipe_id )
			);
		}

		return null;
	}
}
