<?php
namespace Uncanny_Automator\Services\Recipe\Structure\Pluggable;

use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Services\Recipe\Structure\Actions\Item\Action;
use Uncanny_Automator\Services\Resolver\Recipe_Actions_Resolver;

/**
 * Serves as a pluggable class for the conditions.
 *
 * Alters the main recipe object to wrap the actions inside its conditions.
 *
 * @since 5.0
 */
final class Conditions_Pluggable {

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register_recipe_action_hook() {

		// For recipe action conditions.
		add_filter( 'automator_recipe_main_object_action_items', array( $this, 'alter_recipe_object_conditions' ), 10, 3 );

		// For loop action conditions.
		add_filter( 'automator_recipe_main_object_loop_action_items', array( $this, 'alter_loop_action_conditions' ), 10, 4 );

	}

	/**
	 * Callback method to filter 'automator_recipe_main_object_action_items'.
	 *
	 * Hooks into the said filter and resolves the conditions object. Handles the internal actions ordering under it.
	 *
	 * @param Uncanny_Automator\Services\Recipe\Structure\Item\Action[] $action_items
	 * @param Uncanny_Automator\Services\Recipe\Structure $recipe
	 * @param Uncanny_Automator\Services\Recipe\Structure\Actions\Actions $structure_actions
	 *
	 * @return mixed[]
	 */
	public function alter_recipe_object_conditions( $action_items, $recipe, $structure_actions ) {

		// Flag the recipe as pro.
		$recipe->set( 'has_pro_item', true );

		$recipe_id = $recipe->get( 'recipe_id' );

		$resolver = new Recipe_Actions_Resolver( Automator_Functions::get_instance() );
		$resolver->set_recipe_id( $recipe_id );

		$action_flow = $resolver->resolve_recipe_actions( true );

		$flow = $this->resolve_action_flow( $action_flow, $structure_actions, $action_items, $recipe_id );

		return $flow;

	}

	/**
	 * Alters the loops actions if they have conditions.
	 *
	 * @param Uncanny_Automator\Services\Recipe\Structure\Item\Action[] $action_items
	 * @param Uncanny_Automator\Services\Recipe\Structure $recipe
	 * @param Uncanny_Automator\Services\Recipe\Structure\Actions\Actions $structure_actions
	 *
	 * @return mixed[]
	 */
	public function alter_loop_action_conditions( $action_items, $recipe, $loop, $structure_actions ) {

		$loop_id   = $loop->get( 'id' );
		$recipe_id = $recipe->get( 'recipe_id' );

		$resolver    = new Recipe_Actions_Resolver( Automator_Functions::get_instance() );
		$action_flow = $resolver->resolve_loop_actions( $recipe_id, $loop_id );

		$flow = $this->resolve_action_flow( $action_flow, $structure_actions, $action_items, $recipe_id );

		return $flow;
	}

	/**
	 * Resolves the Action flow.
	 */
	public function resolve_action_flow( $action_flow, $structure_actions, $action_items, $recipe_id ) {

		$flow = array();

		if ( is_array( $action_flow ) && ! empty( $action_flow ) ) {

			foreach ( $action_flow as $key => $item ) {

				// This is an action.
				if ( is_numeric( $key ) ) {

					$action_id = $key;

					$action_item = $structure_actions->find_by_id( $action_id, $action_items );

					if ( false !== $action_item ) {
						$flow[] = $action_item;
					}
				} else {

					$group = (array) $this->find_group_by_id( $recipe_id, $key );

					// Its a condition block.
					$action_ids = explode( ',', $item );

					// Render the items.
					$condition_action_item = array();

					foreach ( $action_ids as $action_id ) {
						$action = $structure_actions->find_by_id( $action_id, $action_items );
						if ( false !== $action ) {
							$condition_action_item[] = $action;
						}
					}

					usort( $condition_action_item, array( $this, 'sort_by_ui_order' ) );

					// The first index of the actions should have the lowest delay or menu order.
					$filter__ui_order = 0;

					if ( isset( $condition_action_item[0] ) && method_exists( $condition_action_item[0], 'get' ) ) {
						$filter__ui_order = $condition_action_item[0]->get( '_ui_order' );
					}

					$flow[] = array(
						'type'       => 'filter', // Filter refers to the condition group.
						'_ui_order'  => $filter__ui_order,
						'id'         => $key,
						'logic'      => isset( $group['mode'] ) ? $group['mode'] : null,
						'conditions' => $this->restructure_conditions( isset( $group['conditions'] ) ? $group['conditions'] : null ),
						'items'      => $condition_action_item,
					);
				}
			}
		}

		return $flow;

	}

	/**
	 * Restructures the given conditions.
	 *
	 * Accepts ['conditions'] key from action_conditions post meta.
	 *
	 * @param mixed[] $conditions
	 *
	 * @return mixed[]
	 */
	public function restructure_conditions( $conditions ) {

		$conditions_restructured = array();

		foreach ( (array) $conditions as $condition ) {

			$fields = $condition['fields'];

			// Start of fields restructuring.
			$fields_restructured = array();

			foreach ( $fields as $option_code => $field ) {

				// Skip _readable fields.
				if ( false !== strpos( $option_code, '_readable' )
				|| false !== strpos( $option_code, '_custom' )
				|| false !== strpos( $option_code, '_label' ) ) {
					continue; // Skip _readable fields.
				}

				$custom_value = isset( $fields[ $option_code . '_custom' ] ) ? $fields[ $option_code . '_custom' ] : '';

				$fields_restructured[ $option_code ] = array(
					'value'    => 'automator_custom_value' === $field ? $custom_value : $field,
					'readable' => isset( $fields[ $option_code . '_readable' ] ) ? $fields[ $option_code . '_readable' ] : '',
					'custom'   => $custom_value,
				);

			}
			// End of fields restructuring.

			$conditions_restructured[] = array(
				'id'               => $condition['id'],
				'integration_code' => $condition['integration'],
				'code'             => $condition['condition'],
				'fields'           => $fields_restructured,
				'backup'           => array(
					'integration_name' => $condition['backup']['integrationName'],
					'sentence'         => $condition['backup']['nameDynamic'],
					'sentence_html'    => $condition['backup']['titleHTML'],
				),
			);
		}

		return $conditions_restructured;

	}



	/**
	 * Sort by _ui_order property if its an Action object.
	 *
	 * @return bool
	 */
	private function sort_by_ui_order( $a, $b ) {

		if ( ! $a instanceof Action || ! $b instanceof Action ) {
			return 0;
		}

		if ( $a->get( '_ui_order' ) === $b->get( '_ui_order' ) ) {
			return 0;
		}

		return ( $a->get( '_ui_order' ) < $b->get( '_ui_order' ) ) ? -1 : 1;

	}

	/**
	 * Find group by recipe ID and group ID.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param string $group_id The condition's filter_id or group_id.
	 *
	 * @return false|mixed[] False if no condition group found. Otherwise, the assoc arr of condition group.
	 */
	public function find_group_by_id( $recipe_id, $group_id ) {

		$conditions_groups = (array) json_decode(
			get_post_meta( $recipe_id, 'actions_conditions', true ),
			true
		);

		foreach ( $conditions_groups as $condition_group ) {
			if ( (string) $group_id === (string) $condition_group['id'] ) {
				return $condition_group;
			}
		}

		return false;

	}

}
