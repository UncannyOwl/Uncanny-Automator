<?php
/**
 * Consolidated condition group upsert tool.
 *
 * Replaces: create_condition_group, update_condition_group,
 *           add_action_to_condition_group, remove_action_from_condition_group.
 *
 * group_id absent = create, group_id present = update.
 * action_ids present = full replacement of linked actions.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\HasValidParent;

/**
 * Save Condition Group Tool — upsert.
 *
 * Create: omit group_id. Requires recipe_id, parent_type, parent_id.
 * Update: include group_id. Requires recipe_id, group_id. mode/priority optional.
 * action_ids: full replacement. Present = set exactly these. Empty array = unlink all. Omit = no change.
 *
 * @since 7.1.0
 */
class Save_Condition_Group_Tool extends Abstract_MCP_Tool {

	use HasValidParent;

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'save_condition_group';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Create or update a condition group. Omit group_id to create, include group_id to update. '
			. 'Create requires recipe_id, parent_type, and parent_id. '
			. 'Update requires recipe_id and group_id; mode and priority are optional. '
			. 'Pass action_ids to set which actions are gated by this group (full replacement). '
			. 'Empty action_ids array = unlink all actions. Omit action_ids = no change to links.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'idempotentHint'  => false,
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
				'recipe_id'   => array(
					'type'        => 'integer',
					'description' => 'Recipe ID. Required for both create and update.',
					'minimum'     => 1,
				),
				'group_id'    => array(
					'type'        => 'string',
					'description' => 'Existing condition group ID to update. Omit to create a new group.',
					'minLength'   => 1,
				),
				'parent_type' => array(
					'type'        => 'string',
					'enum'        => array( 'recipe', 'loop' ),
					'description' => 'Where to place the group. Required for create. "recipe" = recipe-level conditions. "loop" = conditions inside a loop.',
				),
				'parent_id'   => array(
					'type'        => 'integer',
					'description' => 'Parent ID matching parent_type. Required for create. If parent_type=recipe, use recipe_id. If parent_type=loop, use loop_id.',
					'minimum'     => 1,
				),
				'mode'        => array(
					'type'        => 'string',
					'enum'        => array( 'any', 'all' ),
					'default'     => 'any',
					'description' => 'Evaluation mode. "any" = OR (any condition passes). "all" = AND (all must pass).',
				),
				'priority'    => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 100,
					'default'     => 20,
					'description' => 'Execution priority. Higher numbers execute first.',
				),
				'action_ids'  => array(
					'type'        => 'array',
					'description' => 'Full replacement: sets exactly these actions on the group. Empty array = unlink all. Omit = no change.',
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'uniqueItems' => true,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'   => array( 'type' => 'integer' ),
				'group_id'    => array( 'type' => 'string' ),
				'parent_type' => array( 'type' => 'string' ),
				'parent_id'   => array( 'type' => 'integer' ),
				'mode'        => array(
					'type' => 'string',
					'enum' => array( 'any', 'all' ),
				),
				'priority'    => array( 'type' => 'integer' ),
				'action_ids'  => array(
					'type' => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			'required'   => array( 'recipe_id', 'group_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$recipe_id = (int) ( $params['recipe_id'] ?? 0 );
		$group_id  = isset( $params['group_id'] ) ? (string) $params['group_id'] : null;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'recipe_id is required.' );
		}

		$condition_service = Recipe_Condition_Service::instance();

		// Route: update if group_id present, create if absent.
		if ( null !== $group_id && '' !== $group_id ) {
			return $this->update_group( $condition_service, $recipe_id, $group_id, $params );
		}

		return $this->create_group( $condition_service, $recipe_id, $params );
	}

	// ──────────────────────────────────────────────────────────────────
	// CREATE PATH — port of Create_Condition_Group_Tool
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Create a new condition group.
	 *
	 * @param Recipe_Condition_Service $service   Condition service.
	 * @param int                      $recipe_id Recipe ID.
	 * @param array                    $params    Tool parameters.
	 *
	 * @return array JSON-RPC response.
	 */
	private function create_group( Recipe_Condition_Service $service, int $recipe_id, array $params ): array {

		$parent_type = $params['parent_type'] ?? null;
		$parent_id   = isset( $params['parent_id'] ) ? (int) $params['parent_id'] : 0;
		$mode        = (string) ( $params['mode'] ?? 'any' );
		$priority    = (int) ( $params['priority'] ?? 20 );

		// Validate create-specific requirements.
		if ( empty( $parent_type ) ) {
			return Json_Rpc_Response::create_error_response( 'parent_type is required for creating a condition group. Use "recipe" or "loop".' );
		}

		if ( $parent_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'parent_id is required for creating a condition group.' );
		}

		// Validate parent matches.
		$parent_error = $this->validate_parent( $parent_type, $parent_id, $recipe_id );
		if ( null !== $parent_error ) {
			return $parent_error;
		}

		if ( ! in_array( $mode, array( 'any', 'all' ), true ) ) {
			return Json_Rpc_Response::create_error_response( 'mode must be "any" or "all".' );
		}

		if ( $priority < 1 ) {
			$priority = 1;
		}

		try {
			$result = $service->add_empty_condition_group( $recipe_id, $mode, $priority, $parent_id );

			if ( is_wp_error( $result ) ) {
				return Json_Rpc_Response::create_error_response( $result->get_error_message() );
			}

			$group_id = (string) ( $result['group_id'] ?? '' );

			// If action_ids provided, link them to the new group.
			if ( isset( $params['action_ids'] ) && is_array( $params['action_ids'] ) && ! empty( $params['action_ids'] ) ) {
				$action_ids  = array_map( 'intval', $params['action_ids'] );
				$link_result = $service->add_actions_to_condition_group( $recipe_id, $group_id, $action_ids );
				if ( is_wp_error( $link_result ) ) {
					// Roll back the empty group to prevent orphan accumulation.
					$service->remove_condition_group( $recipe_id, $group_id );
					return Json_Rpc_Response::create_error_response(
						'Failed to link actions: ' . $link_result->get_error_message()
							. '. The empty group was rolled back. Retry with valid action_ids.'
					);
				}
			}

			$payload = array(
				'recipe_id'   => $recipe_id,
				'parent_type' => $parent_type,
				'parent_id'   => $parent_id,
				'group_id'    => $group_id,
				'mode'        => $result['mode'] ?? $mode,
				'priority'    => isset( $result['priority'] ) ? (int) $result['priority'] : $priority,
				'action_ids'  => isset( $params['action_ids'] ) ? array_map( 'intval', $params['action_ids'] ) : array(),
			);

			return Json_Rpc_Response::create_success_response( 'Condition group created', $payload );

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response( 'Failed to create condition group: ' . $e->getMessage() );
		}
	}

	// ──────────────────────────────────────────────────────────────────
	// UPDATE PATH — port of Update_Condition_Group_Tool + action linking
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Update an existing condition group.
	 *
	 * @param Recipe_Condition_Service $service   Condition service.
	 * @param int                      $recipe_id Recipe ID.
	 * @param string                   $group_id  Group ID.
	 * @param array                    $params    Tool parameters.
	 *
	 * @return array JSON-RPC response.
	 */
	private function update_group( Recipe_Condition_Service $service, int $recipe_id, string $group_id, array $params ): array {

		$mode           = isset( $params['mode'] ) ? (string) $params['mode'] : null;
		$priority       = isset( $params['priority'] ) ? (int) $params['priority'] : null;
		$has_action_ids = array_key_exists( 'action_ids', $params );

		// Must provide at least one thing to update.
		if ( null === $mode && null === $priority && ! $has_action_ids ) {
			return Json_Rpc_Response::create_error_response( 'Provide at least one of: mode, priority, or action_ids.' );
		}

		if ( null !== $mode && ! in_array( $mode, array( 'any', 'all' ), true ) ) {
			return Json_Rpc_Response::create_error_response( 'mode must be "any" or "all".' );
		}

		if ( null !== $priority && $priority < 1 ) {
			$priority = 1;
		}

		try {
			// Update mode/priority if provided.
			if ( null !== $mode || null !== $priority ) {
				$result = $service->update_condition_group( $recipe_id, $group_id, $mode, $priority );
				if ( is_wp_error( $result ) ) {
					return Json_Rpc_Response::create_error_response( $result->get_error_message() );
				}
			}

			// Handle action_ids full replacement if provided.
			if ( $has_action_ids ) {
				$replace_error = $this->replace_group_actions( $service, $recipe_id, $group_id, $params['action_ids'] );
				if ( null !== $replace_error ) {
					return $replace_error;
				}
			}

			$payload = array(
				'recipe_id' => $recipe_id,
				'group_id'  => $group_id,
			);

			if ( null !== $mode ) {
				$payload['mode'] = $mode;
			}
			if ( null !== $priority ) {
				$payload['priority'] = $priority;
			}
			if ( $has_action_ids ) {
				$payload['action_ids'] = is_array( $params['action_ids'] ) ? array_map( 'intval', $params['action_ids'] ) : array();
			}

			return Json_Rpc_Response::create_success_response( 'Condition group updated', $payload );

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response( 'Failed to update condition group: ' . $e->getMessage() );
		}
	}

	/**
	 * Replace all actions linked to a condition group.
	 *
	 * Full replacement via diff: remove stale links, add new links.
	 * Uses existing add/remove service methods (no set_group_actions method exists).
	 *
	 * WARNING: This is NOT atomic. A mid-flight failure (remove succeeds, add fails)
	 * can leave action links partially applied. The error message includes the group_id
	 * so the caller can retry.
	 *
	 * @param Recipe_Condition_Service $service    Condition service.
	 * @param int                      $recipe_id  Recipe ID.
	 * @param string                   $group_id   Group ID.
	 * @param array|null               $action_ids New action IDs (empty array = unlink all).
	 *
	 * @return array|null Error response or null on success.
	 */
	private function replace_group_actions( Recipe_Condition_Service $service, int $recipe_id, string $group_id, $action_ids ): ?array {

		$new_ids = is_array( $action_ids ) ? array_map( 'intval', $action_ids ) : array();

		// Get current actions linked to this group.
		$conditions_result = $service->get_recipe_conditions( $recipe_id );

		if ( is_wp_error( $conditions_result ) ) {
			return Json_Rpc_Response::create_error_response( 'Failed to read current group actions: ' . $conditions_result->get_error_message() );
		}

		// Find current action_ids for this group.
		$current_ids = array();
		$groups      = $conditions_result['condition_groups'] ?? array();

		foreach ( $groups as $group ) {
			$gid = (string) ( $group['group_id'] ?? $group['id'] ?? '' );
			if ( $gid === $group_id ) {
				$current_ids = array_map( 'intval', $group['actions'] ?? $group['action_ids'] ?? array() );
				break;
			}
		}

		// Remove current actions that are not in the new set.
		$to_remove = array_diff( $current_ids, $new_ids );
		if ( ! empty( $to_remove ) ) {
			$remove_result = $service->remove_actions_from_condition_group( $recipe_id, $group_id, array_values( $to_remove ) );
			if ( is_wp_error( $remove_result ) ) {
				return Json_Rpc_Response::create_error_response(
					'Failed to unlink actions from group ' . $group_id . ': ' . $remove_result->get_error_message()
						. '. No changes were made. Retry or use get_recipe to verify current state.'
				);
			}
		}

		// Add new actions that are not already linked.
		$to_add = array_diff( $new_ids, $current_ids );
		if ( ! empty( $to_add ) ) {
			$add_result = $service->add_actions_to_condition_group( $recipe_id, $group_id, array_values( $to_add ) );
			if ( is_wp_error( $add_result ) ) {
				// Attempt to restore removed actions to minimize partial state.
				if ( ! empty( $to_remove ) ) {
					$service->add_actions_to_condition_group( $recipe_id, $group_id, array_values( $to_remove ) );
				}
				return Json_Rpc_Response::create_error_response(
					'Failed to link new actions to group ' . $group_id . ': ' . $add_result->get_error_message()
						. '. Attempted to restore previous links. Use get_recipe to verify current state.'
				);
			}
		}

		return null;
	}

}
