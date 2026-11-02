<?php

namespace Uncanny_Automator\Services\Recipe\Structure\Actions;

use Uncanny_Automator\App\Recipe_Builder\Block\Resolvers\Block_Resolver;
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

	/**
	 * Bind this actions collection to the recipe structure that owns it.
	 *
	 * The recipe is held statically so nested item objects built during
	 * hydration can reach it without threading it through every level.
	 *
	 * @param \Uncanny_Automator\Services\Recipe\Structure $recipe Owning recipe structure.
	 * @param array                                        $meta   Raw action meta rows.
	 */
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

		$actions = Automator()->get_recipe_data( AUTOMATOR_POST_TYPE_ACTION, $recipe_id );

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
		 * Filter wrappers are produced by Legacy_Frontend_Translator; the
		 * Conditions_Pluggable class that used to build them is retired.
		 */
		$items = apply_filters( 'automator_recipe_main_object_action_items', $action_items, self::$recipe, $this );

		// Loops — only create legacy Loop objects if Block_Resolver didn't resolve them.
		if ( ! Block_Resolver::loops_resolved() ) {
			foreach ( $loops as $loop ) {
				$items[] = new Structure\Actions\Item\Loop( self::$recipe, $this, $loop['ID'] );
			}
		}

		// Sort by ui_order.
		$items = json_decode( wp_json_encode( $items ), true );

		usort( $items, array( $this, 'sort_by_ui_order' ) );

		// Convert empty arrays to objects for specific keys after JSON round-trip.
		$this->items = $this->normalize_empty_arrays( $items );
	}

	/**
	 * Recursively normalizes the items array for frontend consumption.
	 *
	 * This method:
	 * 1. Converts empty 'fields' arrays to stdClass for {} JSON serialization
	 * 2. Removes '_ui_order' keys (used internally for sorting, not needed by frontend)
	 *
	 * @param mixed $data The data to normalize.
	 *
	 * @return mixed The normalized data.
	 */
	private function normalize_empty_arrays( $data ) {

		if ( ! is_array( $data ) ) {
			return $data;
		}

		// Remove _ui_order - it's only needed for sorting, not for frontend.
		unset( $data['_ui_order'] );

		// Only 'fields' should be {} when empty (key-value map).
		// Properties like 'tokens', 'filters', 'items' are lists and should stay [].
		$object_keys = array( 'fields' );

		foreach ( $data as $key => $value ) {
			// Convert empty arrays to stdClass for specific keys.
			if ( in_array( $key, $object_keys, true ) && is_array( $value ) && empty( $value ) ) {
				$data[ $key ] = new \stdClass();
			} elseif ( is_array( $value ) ) {
				// Recursively process nested arrays.
				$data[ $key ] = $this->normalize_empty_arrays( $value );
			}
		}

		return $data;
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
