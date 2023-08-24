<?php

namespace Uncanny_Automator\Services\Recipe\Structure\Actions;

use Uncanny_Automator\Services\Recipe\Structure;
use Uncanny_Automator\Services\Recipe\Common;
use Uncanny_Automator\Services\Recipe\Structure\Actions\User_Selector;
use Uncanny_Automator\Services\Recipe\Structure\Actions\Action;
use Uncanny_Automator\Services\Structure\Actions\Item\Loop\Loop_Db;

/**
 * Object representation of the actions object insde the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure\Actions
 */
final class Actions implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;
	use Common\Trait_Setter_Getter;

	protected $run_on = null;
	protected $items  = null;

	/**
	 * @var \Uncanny_Automator\Services\Recipe\Structure
	 */
	private static $recipe = null;

	public function __construct( \Uncanny_Automator\Services\Recipe\Structure $recipe, $meta = array() ) {

		self::$recipe = $recipe;

		$this->fill_run_on( $meta );

		$this->fill_items();

	}

	/**
	 * Fills the "run on" object.
	 *
	 * @param mixed[] $meta
	 *
	 * @return void
	 */
	private function fill_run_on( $meta ) {

		$user_selector = new User_Selector();

		$user_selector->accept( $meta );

		$this->run_on = $user_selector->retrieve();
	}

	/**
	 * Fills the items object in the main recipe object.
	 *
	 * @return void
	 */
	private function fill_items() {

		$recipe_id = self::$recipe->get_recipe_id();

		$loop_db = new Loop_Db();

		$actions = Automator()->get_recipe_data( 'uo-action', $recipe_id );

		$loops = $loop_db->find_recipe_loops( $recipe_id );

		$action_items = array();

		foreach ( $actions as $action_item ) {

			$action = new Item\Action( self::$recipe );
			$action->hydrate_from( $action_item );

			$action_items[] = $action;

		}

		/**
		 * Conditions are implemented as post_meta instead of post type so we cannot retrieve
		 * the items directly from db as a part of query that looks up the child items of the recipe.
		 *
		 * We have to resolve the actions, and loops first, then modify the whole object
		 * to resolve the conditions.
		 *
		 * The conditions are hooked into 'automator_recipe_main_object_action_items'.
		 * We're querying the record from the database and wrap the action into its condition.
		 *
		 * @see Item/Conditions_Pluggable ~item/conditions-pluggable.php
		 */
		$items = apply_filters( 'automator_recipe_main_object_action_items', $action_items, self::$recipe, $this );

		$recipe_closure = Automator()->get_recipe_closure( self::$recipe->get_recipe_id() );

		if ( ! empty( $recipe_closure ) ) {
			$items[] = new Structure\Closure( self::$recipe, $recipe_closure );
		}

		// Loops
		foreach ( $loops as $loop ) {
			$items[] = new Structure\Actions\Item\Loop( self::$recipe, $this, $loop['ID'] );
		}
		// End Loops.

		// Sort by ui_order.
		$items = json_decode( wp_json_encode( $items ), true );

		usort( $items, array( $this, 'sort_by_ui_order' ) );

		$this->items = $items;
	}

	/**
	 * Sort by timings
	 *
	 * @param object $a
	 * @param object $b
	 *
	 * @return int -1 if $a[key] is less than $b[key]. 1 if $a > $b, otherwise 0;
	 */
	private function sort_by_ui_order( $a, $b ) {

		if ( $a['_ui_order'] === $b['_ui_order'] ) {
			return 0;
		}

		return ( $a['_ui_order'] < $b['_ui_order'] ) ? -1 : 1;

	}

	/**
	 * Does linear search and return the action object that matches the ID.
	 *
	 * @param int $action_id
	 * @param mixed[] $action_items
	 *
	 * @return bool|Action\Action
	 */
	public function find_by_id( $action_id, $action_items ) {

		foreach ( $action_items as $action_item ) {

			// Handle filter types. Filter types are pluggable and they are normal array. Filter here refers to action conditions.
			if ( is_array( $action_item ) && 'filter' === $action_item['type'] ) {
				foreach ( $action_item['items'] as $condition_action_item ) {
					if ( absint( $action_id ) === absint( $condition_action_item->get( 'id' ) ) ) {
						return $condition_action_item;
					}
				}
			}

			// Action is already an instance of.
			if ( $action_item instanceof Structure\Actions\Item\Action && absint( $action_id ) === absint( $action_item->get( 'id' ) ) ) {
				return $action_item;
			}
		}

		return false;
	}

}

