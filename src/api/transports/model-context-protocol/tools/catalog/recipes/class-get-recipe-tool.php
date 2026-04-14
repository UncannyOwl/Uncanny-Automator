<?php
/**
 * Comprehensive recipe inspection tool.
 *
 * Returns the full recipe tree from Automator()->get_recipe_object() with
 * UI-only fields stripped. Preserves the runtime execution order (_ui_order)
 * and condition group wrapping.
 *
 * Absorbs: list_actions, loop_list, loop_get, loop_filter_list, loop_filter_get,
 *          list_conditions, get_user_selector.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Get Recipe Tool — comprehensive.
 *
 * Single call returns everything the agent needs to understand recipe state.
 * Uses the recipe object which already computes execution order and condition wrapping.
 *
 * @since 7.0.0 (rewritten 7.1.0)
 */
class Get_Recipe_Tool extends Abstract_MCP_Tool {

	/**
	 * Fields to strip from the recipe object. UI-only, not useful for the agent.
	 */
	private const STRIP_TOP_LEVEL = array(
		'is_pro_active',
		'has_pro_item',
		'_config',
	);

	private const STRIP_MISC = array(
		'url_duplicate_recipe',
		'url_trash_recipe',
		'url_logs',
		'url_download_recipe',
		'can_edit',
		'has_loop_running',
		'recipe_is_running',
	);

	private const STRIP_ACTION_ITEM = array(
		'backup',
		'sentence_human_readable_html',
	);

	private const STRIP_TRIGGER_ITEM = array(
		'backup',
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'get_recipe';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Get a recipe by ID with complete details: triggers, actions (in execution order), '
			. 'loops (with filters), conditions (wrapping their gated actions), and user selector. '
			. 'Returns the full recipe tree in one call. '
			. 'Actions items are sorted by execution_order — conditions wrap their gated actions inline.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the recipe to get.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		// Mirrors the TypeScript Recipe interface at:
		// src/assets/src/features/recipe-builder/types/recipe/structure.d.ts
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'     => array( 'type' => 'integer' ),
				'is_recipe_on'  => array( 'type' => 'boolean' ),
				'title'         => array( 'type' => 'string' ),
				'recipe_type'   => array(
					'type' => 'string',
					'enum' => array( 'user', 'anonymous' ),
				),
				'stats'         => array(
					'type'       => 'object',
					'properties' => array(
						'total_runs' => array( 'type' => 'integer' ),
					),
				),
				'miscellaneous' => array( 'type' => 'object' ),
				'triggers'      => array(
					'type'       => 'object',
					'properties' => array(
						'logic' => array(
							'type' => 'string',
							'enum' => array( 'any', 'all' ),
						),
						'items' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'type'             => array( 'type' => 'string' ),
									'id'               => array( 'type' => 'integer' ),
									'is_item_on'       => array( 'type' => 'boolean' ),
									'integration_code' => array( 'type' => 'string' ),
									'code'             => array( 'type' => 'string' ),
									'miscellaneous'    => array( 'type' => 'object' ),
									'fields'           => array( 'type' => 'object' ),
									'tokens'           => array( 'type' => 'array' ),
								),
							),
						),
					),
				),
				'actions'       => array(
					'type'       => 'object',
					'properties' => array(
						'run_on' => array( 'type' => array( 'object', 'null' ) ),
						'items'  => array(
							'type'        => 'array',
							'description' => 'Execution-ordered. type=action|filter|loop. filter items wrap gated actions. Sorted by _ui_order.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'type'             => array( 'type' => 'string' ),
									'_ui_order'        => array( 'type' => 'integer' ),
									'id'               => array( 'type' => array( 'integer', 'string' ) ),
									'is_item_on'       => array( 'type' => 'boolean' ),
									'integration_code' => array( 'type' => 'string' ),
									'code'             => array( 'type' => 'string' ),
									'miscellaneous'    => array( 'type' => 'object' ),
									'fields'           => array( 'type' => 'object' ),
									'tokens'           => array( 'type' => 'array' ),
									'logic'            => array( 'type' => 'string' ),
									'conditions'       => array( 'type' => 'array' ),
									'items'            => array( 'type' => 'array' ),
								),
							),
						),
					),
				),
			),
			'required'   => array( 'recipe_id', 'title', 'recipe_type', 'triggers', 'actions' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute_tool( User_Context $user_context, array $params ) {

		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameter recipe_id must be a positive integer. Use list_recipes to find recipe IDs.' );
		}

		try {
			$recipe = Automator()->get_recipe_object( $recipe_id, ARRAY_A );

			if ( empty( $recipe ) || ! is_array( $recipe ) ) {
				return Json_Rpc_Response::create_error_response(
					sprintf( 'Recipe not found with ID %d. Use list_recipes to find valid recipe IDs.', $recipe_id )
				);
			}

			// Strip UI-only top-level fields.
			foreach ( self::STRIP_TOP_LEVEL as $key ) {
				unset( $recipe[ $key ] );
			}

			// Strip UI-only miscellaneous fields and fix empty array serialization.
			if ( isset( $recipe['miscellaneous'] ) && is_array( $recipe['miscellaneous'] ) ) {
				foreach ( self::STRIP_MISC as $key ) {
					unset( $recipe['miscellaneous'][ $key ] );
				}
				if ( isset( $recipe['miscellaneous']['recipe_throttle'] ) ) {
					$recipe['miscellaneous']['recipe_throttle'] = $this->ensure_object( $recipe['miscellaneous']['recipe_throttle'] );
				}
				$recipe['miscellaneous'] = $this->ensure_object( $recipe['miscellaneous'] );
			}

			// Clean trigger items.
			if ( isset( $recipe['triggers']['items'] ) && is_array( $recipe['triggers']['items'] ) ) {
				$recipe['triggers']['items'] = array_map(
					function ( $trigger ) {
						foreach ( self::STRIP_TRIGGER_ITEM as $key ) {
							unset( $trigger[ $key ] );
						}
						$trigger['fields'] = $this->ensure_object( $trigger['fields'] ?? array() );
						return $trigger;
					},
					$recipe['triggers']['items']
				);
			}

			// Clean action items recursively (handles condition group nesting).
			if ( isset( $recipe['actions']['items'] ) && is_array( $recipe['actions']['items'] ) ) {
				$recipe['actions']['items'] = array_map(
					array( $this, 'clean_action_item' ),
					$recipe['actions']['items']
				);
			}

			return Json_Rpc_Response::create_success_response( 'Recipe retrieved successfully', $recipe );

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response( 'Failed to retrieve recipe: ' . $e->getMessage() );
		}
	}

	/**
	 * Clean an action item — strip UI fields, recurse into nested items (condition groups).
	 *
	 * @param array $item Action, loop, or condition group item.
	 * @return array Cleaned item.
	 */
	private function clean_action_item( array $item ): array {
		foreach ( self::STRIP_ACTION_ITEM as $key ) {
			unset( $item[ $key ] );
		}

		// Fix empty arrays that should be objects.
		if ( isset( $item['fields'] ) ) {
			$item['fields'] = $this->ensure_object( $item['fields'] );
		}

		// Recurse into nested items (condition groups wrap actions).
		if ( isset( $item['items'] ) && is_array( $item['items'] ) ) {
			$item['items'] = array_map( array( $this, 'clean_action_item' ), $item['items'] );
		}

		return $item;
	}
}
