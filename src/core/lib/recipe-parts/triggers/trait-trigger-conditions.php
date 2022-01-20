<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Trigger_Conditions
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */


namespace Uncanny_Automator\Recipe;

use \Uncanny_Automator\Automator_Exception;
use stdClass;

/**
 * Trait Trigger_Conditions
 *
 * @package Uncanny_Automator
 */
trait Trigger_Conditions {

	/**
	 * Set this to true if triggers has to be conditionally filtered.
	 *
	 * @var bool
	 */
	private $conditional_trigger = false;

	/**
	 * Holds a value to be matched from.
	 *
	 * @var mixed
	 */
	protected $find_in = array();

	/**
	 * Holds a value to be matched in.
	 *
	 * @var array
	 */
	protected $find_this = array();

	/**
	 * @var bool
	 */
	protected $find_any = false;

	/**
	 * @var array
	 */
	protected $compare_this_numb_cond = array();
	/**
	 * @var array
	 */
	protected $compare_numb_cond_in = array();

	/**
	 * @return array
	 */
	public function get_compare_this_numb_cond() {
		return $this->compare_this_numb_cond;
	}

	/**
	 * @param $compare_this_numb_cond
	 */
	public function do_compare_this_numb_cond( $compare_this_numb_cond ) {
		$this->compare_this_numb_cond[] = $compare_this_numb_cond;
	}

	/**
	 * @return array
	 */
	public function get_compare_numb_cond_in() {
		return $this->compare_numb_cond_in;
	}

	/**
	 * @param $compare_numb_cond_in
	 */
	public function do_compare_numb_cond_in( $compare_numb_cond_in ) {
		$this->compare_numb_cond_in[] = $compare_numb_cond_in;
	}

	/**
	 * @return mixed
	 */
	public function get_find_any() {
		return $this->find_any;
	}

	/**
	 * @param mixed $find_any
	 */
	public function do_find_any( $find_any ) {
		$this->find_any = $find_any;
	}

	/**
	 * @return array
	 */
	protected function get_find_in() {
		return $this->find_in;
	}

	/**
	 * @param mixed $find_in
	 */
	protected function do_find_in( $find_in ) {
		$this->find_in[] = $find_in;
	}

	/**
	 * @return array
	 */
	protected function get_find_this() {
		return $this->find_this;
	}

	/**
	 * @param mixed $find_this
	 */
	protected function do_find_this( $find_this ) {
		$this->find_this[] = $find_this;
	}

	/**
	 * return empty array() if it's not a conditional trigger OR no condition are to be matched.
	 * Use it as follows:
	 *
	 * @param mixed ...$args
	 *
	 * @see \Uncanny_Automator\Trigger_Conditions do_find_in( $this->get_trigger_meta() );
	 * @see \Uncanny_Automator\Trigger_Conditions do_find_this( $this->get_post_id() );
	 */
	protected function trigger_conditions( ...$args ) {
		// Placeholder function
	}

	/**
	 * @return bool
	 */
	protected function is_conditional_trigger() {
		return $this->conditional_trigger;
	}

	/**
	 * @return object
	 * @throws \Exception
	 */
	protected function get_conditions() {
		$match_value    = $this->get_find_in();
		$match_value_in = $this->get_find_this();

		// $match_value and $match_value_in counts doesn't match. Throw an exception for developers.
		if ( count( $match_value ) !== count( $match_value_in ) ) {
			throw new Automator_Exception( 'Trigger conditions miss-matched. Please pass values in both $this->do_find_this() and $this->do_find_in().' );
		}

		$match_conditions = new stdClass();
		$count            = count( $match_value );
		$xyz              = 0;
		while ( $xyz < $count ) {
			$match_conditions->$xyz = (object) array(
				'match'    => $match_value[ $xyz ],
				'match_in' => $match_value_in[ $xyz ],
			);
			$xyz ++;
		}

		return $match_conditions;
	}

	/**
	 * @param $conditional_trigger
	 */
	protected function set_conditional_trigger( $conditional_trigger ) {
		$this->conditional_trigger = $conditional_trigger;
	}

	/**
	 * Match trigger conditions. For example, if Any option is suppose to run, or specific meta has to match. This
	 * function either has to return true to continue processing trigger or false to bailout.
	 *
	 * @param $args
	 *
	 * @return array
	 * @throws \Exception
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function validate_conditions( $args ) {
		$matched_recipe_ids = array();
		/*
		 * Get recipes that matches the current trigger.
		 */
		$recipes = $this->trigger_recipes();

		if ( empty( $recipes ) ) {
			return $matched_recipe_ids;
		}

		/**
		 * Get all the conditions set for a trigger.
		 */
		$trigger_conditions = $this->get_conditions();
		/*
		 * No trigger conditions found. Return unfiltered trigger recipe ids.
		 */
		if ( empty( $trigger_conditions ) || ! is_object( $trigger_conditions ) ) {
			return $matched_recipe_ids;
		}

		foreach ( $trigger_conditions as $key => $condition ) {
			$match    = $condition->match;
			$match_in = $condition->match_in;

			$matched_condition          = $this->required_condition_in_trigger_meta( $recipes, $match_in );

			$matched_recipe_ids[ $key ] = $this->find_value_in_trigger_meta( $match, $matched_condition, $recipes );
		}

		return $this->unique_recipes( $matched_recipe_ids, count( (array) $trigger_conditions ) );
	}

	/**
	 * @param $matched_recipe_ids
	 * @param $count
	 *
	 * @return mixed
	 */
	protected function unique_recipes( $matched_recipe_ids, $count ) {

		/*
		 * If it's only one recipe, just return the first recipe
		 */
		if ( 1 === (int) $count ) {
			$matched_recipe_ids = array_shift( $matched_recipe_ids );
		}

		return $matched_recipe_ids;
	}

	/**
	 * Return recipes that matches with trigger code.
	 *
	 * @return array
	 */
	protected function trigger_recipes() {
		return Automator()->get->recipes_from_trigger_code( $this->get_trigger_code() );
	}

	/**
	 * Further drill down recipes that has the required meta in them.
	 *
	 * @param        $recipes
	 * @param $required_condition
	 *
	 * @return array
	 */
	protected function required_condition_in_trigger_meta( $recipes, $required_condition = '' ) {
		$required_condition = empty( $required_condition ) ? $this->get_trigger_meta() : $required_condition;

		return Automator()->get->meta_from_recipes( $recipes, $required_condition );
	}

	/**
	 * Pass a value to match and return matched recipe/trigger IDs.
	 *
	 * @param $value
	 * @param $match_in
	 * @param $recipes
	 *
	 * @return array
	 */
	protected function find_value_in_trigger_meta( $value, $match_in, $recipes ) {
		$matched = array();
		if ( empty( $recipes ) ) {
			return $matched;
		}
		// Add where option is set to Any product.
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				// Recipe ID does not exist in $match_in.
				if ( ! isset( $match_in[ $recipe_id ] ) ) {
					continue;
				}

				$trigger_id = absint( $trigger['ID'] );
				// Trigger ID does not exist in $match_in.
				if ( ! isset( $match_in[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}

				// $value set is -1 (Any).
				if ( true === $this->get_find_any() && intval( '-1' ) === intval( $match_in[ $recipe_id ][ $trigger_id ] ) ) {
					$matched[ $recipe_id ] = $trigger_id;
				}
				// If value is not an array
				if ( ! is_array( $value ) && $value === $match_in[ $recipe_id ][ $trigger_id ] ) {
					$matched[ $recipe_id ] = $trigger_id;
				}
				// if value is of type array
				if ( is_array( $value ) && in_array( (int) $match_in[ $recipe_id ][ $trigger_id ], array_map( 'absint', $value ), true ) ) {
					$matched[ $recipe_id ] = $trigger_id;
				}
			}
		}

		return $matched;
	}

	/**
	 * @param $value
	 * @param $match_in
	 * @param $recipes
	 *
	 * @return array
	 * TODO: Write match condition vs number function here
	 */
	protected function find_value_in_number_cond( $value, $match_in, $recipes ) {
		$matched = array();
		//      if ( empty( $recipes ) ) {
		//          return $matched;
		//      }
		//      // Add where option is set to Any product.
		//      foreach ( $recipes as $recipe_id => $recipe ) {
		//          foreach ( $recipe['triggers'] as $trigger ) {
		//              // Recipe ID does not exist in $match_in.
		//              if ( ! isset( $match_in[ $recipe_id ] ) ) {
		//                  continue;
		//              }
		//
		//              $trigger_id = absint( $trigger['ID'] );
		//              // Trigger ID does not exist in $match_in.
		//              if ( ! isset( $match_in[ $recipe_id ][ $trigger_id ] ) ) {
		//                  continue;
		//              }
		//
		//              // $value set is -1 (Any).
		//              if ( true === $this->get_find_any() && intval( '-1' ) === intval( $match_in[ $recipe_id ][ $trigger_id ] ) ) {
		//                  $matched[ $recipe_id ] = $trigger_id;
		//              }
		//              // If value is not an array
		//              if ( ! is_array( $value ) && (int) $value === (int) $match_in[ $recipe_id ][ $trigger_id ] ) {
		//                  $matched[ $recipe_id ] = $trigger_id;
		//              }
		//              // if value is of type array
		//              if ( is_array( $value ) && in_array( (int) $match_in[ $recipe_id ][ $trigger_id ], array_map( 'absint', $value ), true ) ) {
		//                  $matched[ $recipe_id ] = $trigger_id;
		//              }
		//          }
		//      }

		return $matched;
	}
}
