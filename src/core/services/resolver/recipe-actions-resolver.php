<?php
namespace Uncanny_Automator\Services\Resolver;

class Recipe_Actions_Resolver {

	/**
	 * @var int $recipe_id
	 */
	protected $recipe_id = 0;

	/**
	 * @var \Uncanny_Automator\Automator_Functions $automator
	 */
	protected $automator;

	public function __construct( \Uncanny_Automator\Automator_Functions $automator ) {
		$this->automator = $automator;
	}

	/**
	 * Get the value of recipe_id
	 *
	 * @return int
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Set the value of recipe_id
	 *
	 * @param int $recipe_id
	 *
	 * @return self
	 */
	public function set_recipe_id( $recipe_id ) {
		$this->recipe_id = $recipe_id;

		return $this;
	}

	/**
	 * @param mixed[] $recipe_conditions
	 * @param mixed[] $actions_conditions
	 *
	 * @return mixed[] $actions_conditions
	 */
	private function hydrate_actions_conditions( $recipe_conditions, &$actions_conditions ) {

		if ( is_array( $recipe_conditions ) ) {

			foreach ( $recipe_conditions as $index => $recipe_condition ) {

				$condition_actions = isset( $recipe_condition['actions'] ) ? (array) $recipe_condition['actions'] : array();

				if ( ! empty( $condition_actions ) ) {
					foreach ( $condition_actions as $recipe_condition_action ) {
						$actions_conditions[ $recipe_condition_action ] = $recipe_condition['id'];
					}
				} else {
					// Empty actions inside the action conditions block, mark the action id as -1.
					$actions_conditions[ $recipe_condition['id'] ] = null;
				}
			}
		}

		return $actions_conditions;

	}

	/**
	 * @param array<mixed[]> $recipe_actions
	 * @param array<mixed[]> $actions_conditions
	 *
	 * @return mixed[]
	 */
	private function flip_ordered_action_conditions( $recipe_actions, $actions_conditions ) {

		$buckets = array();

		// If there are actions.
		foreach ( $recipe_actions as $recipe_action ) {
			// If action has condition.
			$action_id = $recipe_action['ID'];
			if ( isset( $actions_conditions[ $action_id ] ) ) {
				$condition_id          = $actions_conditions[ $action_id ];
				$buckets[ $action_id ] = $condition_id;
			} else {
				$buckets[ $action_id ] = $action_id;
			}
		}

		$flipped = array_flip( $buckets ); /** @phpstan-ignore-line */

		// If there are action conditions without actions. Insert them manually into the flipped data and put null as actions.
		foreach ( (array) $actions_conditions as $key => $value ) {
			if ( ! is_numeric( $key ) && null === $value ) {
				$flipped[ $key ] = null;
			}
		}

		foreach ( $flipped as $k => $v ) {
			$flipped[ $k ] = implode( ', ', array_keys( $buckets, $k, true ) );
		}

		return $flipped;

	}

	/**
	 * @param bool $show_draft Whether to show the draft or not.
	 *
	 * @return mixed[]
	 */
	public function resolve_recipe_actions( $show_draft = false ) {

		$recipe_id = $this->get_recipe_id();

		// 1. Get all actions order by menu from recipe ID.
		$recipe_actions = $this->automator->get_recipe_actions( $recipe_id, $show_draft );

		// 2. Get recipe conditions
		$recipe_conditions = $this->automator->get_recipe_conditions( $recipe_id, $recipe_id );

		if ( is_array( $recipe_actions ) && is_array( $recipe_conditions ) ) {
			// 3. Hydrate actions that has conditions.
			$this->hydrate_actions_conditions( $recipe_conditions, $actions_conditions );
			// 4. Flip the keys accordingly.
			return $this->flip_ordered_action_conditions( $recipe_actions, $actions_conditions );
		}

		return array();

	}

	/**
	 * @param int $loop_id The ID of the loop.
	 * @param int $recipe_id The ID of the loop.
	 *
	 * @return mixed[]
	 */
	public function resolve_loop_actions( $recipe_id, $loop_id ) {

		// 1. Get all actions order by menu from recipe ID.
		$recipe_actions = $this->automator->get_recipe_actions( $loop_id, true );

		// 2. Get recipe conditions
		$recipe_conditions = $this->automator->get_recipe_conditions( $recipe_id, $loop_id );

		if ( is_array( $recipe_actions ) && is_array( $recipe_conditions ) ) {
			// 3. Hydrate actions that has conditions.
			$this->hydrate_actions_conditions( $recipe_conditions, $actions_conditions );
			// 4. Flip the keys accordingly.
			return $this->flip_ordered_action_conditions( $recipe_actions, $actions_conditions );
		}

		return array();

	}

}

