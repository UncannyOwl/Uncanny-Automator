<?php

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

			foreach ( $recipe_conditions as $recipe_condition ) {
				if ( is_array( $recipe_condition['actions'] ) ) {
					foreach ( $recipe_condition['actions'] as $recipe_condition_action ) {
						$actions_conditions[ $recipe_condition_action ] = $recipe_condition['id'];
					}
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

		foreach ( $flipped as $k => $v ) {
			$flipped[ $k ] = implode( ', ', array_keys( $buckets, $k, true ) );
		}

		return $flipped;

	}

	/**
	 * @return mixed[]
	 */
	public function resolve_recipe_actions() {

		// 1. Get all actions order by menu from recipe ID.
		$recipe_actions = $this->automator->get_recipe_actions( $this->get_recipe_id() );

		// 2. Get recipe conditions
		$recipe_conditions = $this->automator->get_recipe_conditions( $this->get_recipe_id() );

		if ( is_array( $recipe_actions ) && is_array( $recipe_conditions ) ) {
			// 3. Hydrate actions that has conditions.
			$this->hydrate_actions_conditions( $recipe_conditions, $actions_conditions );
			// 4. Flip the keys accordingly.
			return $this->flip_ordered_action_conditions( $recipe_actions, $actions_conditions );
		}

		return array();

	}

}

