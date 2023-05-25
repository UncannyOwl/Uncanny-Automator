<?php

namespace Uncanny_Automator\Recipe;

/**
 * Trait Trigger_Recipe_Filters
 *
 * @since 4.2
 * @package Uncanny_Automator\Recipe\Trigger_Recipe_Filters
 */
trait Trigger_Recipe_Filters {

	/**
	 * The recipes object to iterate.
	 *
	 * @var array $recipes
	 */
	protected $recipes = array();

	/**
	 * The conditions format.
	 *
	 * @var array $conditions_format
	 */
	protected $conditions_format = array();

	/**
	 * The conditions to match against.
	 *
	 * @var array $match_conditions
	 */
	protected $match_conditions = array();

	/**
	 * There `where` conditions set by ->where( 'OPTIONS_FIELD_1', 'OPTION_FIELD_2' ) ).
	 *
	 * @var array $where_conditions
	 */
	protected $where_conditions = array();

	/**
	 * The actual values of the fields from specified option codes using ->where() method.
	 *
	 * @var array $actual_where_values
	 */
	protected $actual_where_values = array();

	/**
	 * Property `$compare` holds the value of symbols to compare (e.g. `>=`, `<=`, `>`, `<`, `=`, `!=`).
	 *
	 * @var array $match_conditions
	 */
	protected $compare = array();

	/**
	 * The default callable function to format values from `$actual_where_values`.
	 *
	 * @var array $default_callable_format
	 */
	protected $default_callable_format = 'sanitize_text_field';

	/**
	 * The default comparison symbol to use. Defaults to `=` if not specified.
	 *
	 * @var string $default_notation
	 */
	protected $default_notation = '=';

	/**
	 * The number condition value to compare.
	 *
	 * @var int $number_condition_value_to_compare
	 */
	protected $number_condition_value_to_compare = 0;

	/**
	 * The number condition value to compare from.
	 *
	 * @var int $number_condition_value_from_field
	 */
	protected $number_condition_value_from_field = 0;

	/**
	 * Property logs holds the specific information collected using $this->push_log.
	 *
	 * @var $log array
	 */
	private $log = array();

	/**
	 * Retrieves all recipes to iterate from.
	 *
	 * @return array The recipe array set using `->find_all()` method.
	 */
	public function get_recipes() {

		return $this->recipes;

	}

	/**
	 * Retrieves all conditions format.
	 *
	 * @return array The conditions form set using `->format()` method.
	 */
	public function get_conditions_format( $index = null ) {

		if ( empty( $this->conditions_format ) ) {
			throw new \InvalidArgumentException( 'Empty format. Pass array to $this->format( array $args ).' );
		}

		if ( is_numeric( $index ) ) {
			return $this->conditions_format[ $index ];
		}

		return $this->conditions_format;

	}

	/**
	 * Retrieves all match conditions set from `->match()` method.
	 *
	 * @return array The matching conditions from user input or action hook.
	 */
	public function get_match_conditions() {

		return $this->match_conditions;

	}

	/**
	 * Retrieves all where conditions set from `->where()` method.
	 *
	 * @return array The where conditions specified using field option codes.
	 */
	public function get_where_conditions() {

		return $this->where_conditions;

	}

	/**
	 * Retrieves all actual where values from specified where conditions using field option code.
	 *
	 * @return array The actual values coming from `->where()` method.
	 */
	public function get_actual_where_values() {

		return $this->actual_where_values;

	}

	/**
	 * Sets the property `$recipes`.
	 *
	 * @param array $recipes The recipes to iterate from.
	 *
	 * @return \Uncanny_Automator\Trigger_Recipe_Filters The filters object.
	 */
	public function find_all( $recipes = array() ) {

		$this->recipes = $recipes;

		return $this;

	}

	/**
	 * Sets the property `$where_conditions`.
	 *
	 * @param array $conditions The conditions. Pass list of option code.
	 *
	 * @return \Uncanny_Automator\Trigger_Recipe_Filters The filters object.
	 */
	public function where( $conditions = array() ) {

		$this->where_conditions = $conditions;

		return $this;

	}

	/**
	 * Sets the property `$match_conditions`.
	 *
	 * @param array $conditions The conditions. Pass list of primitive values.
	 *
	 * @return \Uncanny_Automator\Trigger_Recipe_Filters The filters object.
	 */
	public function equals( $conditions = array() ) {

		$this->match_conditions = $conditions;

		return $this;

	}

	/**
	 * Wrapper method for `->equals`.
	 *
	 * @param array $conditions The conditions. Pass list of primitive values.
	 *
	 * @return \Uncanny_Automator\Trigger_Recipe_Filters The filters object.
	 */
	public function match( $conditions ) {

		$this->equals( $conditions );

		return $this;

	}

	/**
	 * Push new comparison symbol to property `$compare`.
	 *
	 * @param array $compare Example call `$this->compare('1',2,'hello');`
	 *
	 * @return \Uncanny_Automator\Trigger_Recipe_Filters The filters object.
	 */
	public function compare( $compare = array() ) {

		$n_where = count( $this->get_where_conditions() );

		for ( $i = 0; $i < $n_where; $i ++ ) {

			// Set default format to default callable format.
			if ( empty( $compare[ $i ] ) ) {
				$compare[ $i ] = $this->default_notation;
			}
		}

		$this->compare = $compare;

		return $this;

	}

	/**
	 * Pushes new callable method to property $conditions_format.
	 *
	 * @param array $conditions_format The conditions format (e.g. `$this->format( array( 'absint', 'trim' ) )`)
	 *
	 * @return \Uncanny_Automator\Trigger_Recipe_Filters The filters object.
	 */
	public function format( $conditions_format = array() ) {

		$n_where = count( $this->get_where_conditions() );

		for ( $i = 0; $i < $n_where; $i ++ ) {

			// Set default format to default callable format.
			if ( ! isset( $conditions_format[ $i ] ) ) {
				$conditions_format[ $i ] = $this->default_callable_format;
			}
		}

		$this->conditions_format = $conditions_format;

		return $this;

	}

	/**
	 * Iterates through the given recipe set using `$this->find_all()` method.
	 *
	 * Analyzes the recipes with matching triggers and returns an array collection
	 * of Recipes with Triggers in the following format:
	 *
	 * `array( 123 => array( 456, 789 ) )`
	 *
	 * Where 123 is the ID of the recipe, and both 456 and 789 are the matching triggers.
	 *
	 * @return array The matching recipe and trigger ids.
	 */
	public function get() {

		$matched_recipe_ids = array();

		// Check if has number conidtions.
		if ( $this->has_number_condition() ) {
			// If it has, store in in required comparison symbols.
			$required_comparison_symbols = Automator()->get->meta_from_recipes( $this->get_recipes(), 'NUMBERCOND' );
		}

		// With all the configuration set, iterate to all recipes and triggers.
		foreach ( $this->get_recipes() as $recipe_id => $recipe ) {

			foreach ( $recipe['triggers'] as $trigger ) {

				$trigger_id = absint( $trigger['ID'] );

				// Evaluate number conditions first if required comparison symbols is set.
				if ( isset( $required_comparison_symbols ) ) {

					// Skip processing if number conditions already failing. Proceed to next trigger.
					if ( false === $this->has_number_condition_matched( $trigger_id, $recipe_id, $required_comparison_symbols ) ) {
						continue;
					}
				}

				// Validate if trigger option fields are empty or not.
				if ( ! $this->is_trigger_values_set( $trigger_id, $recipe_id ) ) {
					continue;
				}

				// Start counting matched value at zero.
				$matched_value = 0;

				$where_values = (array) $this->get_where_values( $trigger_id, $recipe_id );

				// Compare each where values with conditions.
				foreach ( $where_values as $i => $where_value ) {

					if ( $this->conditions_matched( $this->get_compare( $i ), $where_value, $this->match_conditions[ $i ], $recipe_id ) ) {

						// Increment the count of matched value per matching result.
						$matched_value ++;

					}
				}

				// If the total where values equals matched values. It means all of our where conditions evaluated to true.
				if ( count( $where_values ) === $matched_value ) {

					$matched_recipe_ids[ $recipe_id ] = $trigger_id;

				}
			}
		}

		return $matched_recipe_ids;

	}

	/**
	 * Determines whether the conditions matches for given comparison symbol vs where.
	 *
	 * @param string $notation The comparison symbol (e.g. `>`, `<`, `=`, etc ).
	 * @param mixed $where The value to compare from.
	 * @param mixed $condition The value to compare to.
	 * @param string $recipe_id The recipe ID. Use for logging.
	 *
	 * @return boolean True if conditions matches. Otherwise, false.
	 */
	private function conditions_matched( $notation, $where, $condition, $recipe_id ) {

		// Process non-integer inputs.
		if ( ! empty( $this->get_compare() ) && ( ! is_numeric( $where ) || ! is_numeric( $condition ) ) ) {

			$condition_matched = ( $where === $condition );

			$assert_message = gettype( $where ) . ":{$where} {$notation} " . gettype( $condition ) . ":$condition | Result: " . ( $condition_matched ? 'Matched' : 'Failed' );

			// Special string_contains notation.
			// @since 4.4
			if ( 'string_contains' === $notation ) {

				$add_slashes = apply_filters( 'automator_escape_matching_characters', '/._-:\\' );

				$condition_matched = preg_match( '/(' . addcslashes( $where, $add_slashes ) . ')/i', $condition );

				$this->push_log( 'Asserting ' . $assert_message, 'assertions', $recipe_id );

				return $condition_matched;

			}

			$this->push_log( 'Asserting (non-numeric)' . $assert_message, 'assertions', $recipe_id );

			return $condition_matched;

		}

		// Otherwise, process with equality sign.
		$condition_matched = $this->match_condition( $notation, $where, $condition );

		/**
		 * Allow external developers to manipulate the true/false based on specific criteria.
		 *
		 * WARNING: THIS IS A CORE FUNCTIONALITY THAT MATCHES A VALUE BASED ON A CRITERIA, E.X.,
		 * A USER ENTERED A SPECIFIC VALUE IN A FIELD.
		 * OVERRIDING THE LOGIC INCORRECTLY WILL RESULT IN FAILED RECIPE RUNS. USE IT WISELY!
		 *
		 * add_filter( 'automator_trigger_filter_condition_matched', function($condition_matched, $notation, $where, $condition, $recipe_id) {
		 *     // doing something;
		 *     return $condition_matched; // Must return boolean True or False.
		 * }, 99, 5 );
		 *
		 * @since v4.8
		 * @author Saad S.
		 * @author Joseph G.
		 */
		$condition_matched = apply_filters( 'automator_trigger_filter_condition_matched_' . $recipe_id, $condition_matched, $notation, $where, $condition, $recipe_id );

		$this->push_log(
			'Asserting field option with value ' . gettype( $where ) . ":{$where} {$notation} " .
			'given value ' . gettype( $condition ) . ":$condition | Result: " .
			( $condition_matched ? 'Matched (with ' . gettype( $condition ) . ' data type converted to int)' : 'Failed' ),
			'assertions',
			$recipe_id
		);

		return $condition_matched;

	}

	/**
	 * Matches the where and the condition with given comparison (notation).
	 *
	 * @param string $notation The comparison symbol (e.g. `>`, `<`, `=`, etc ).
	 * @param mixed $where The value to compare from.
	 * @param mixed $condition The value to compare to.
	 *
	 * @return boolean True if condition matches. Otherwise, false.
	 */
	private function match_condition( $notation, $where, $condition ) {

		return Automator()->utilities->match_condition_vs_number( $notation, $where, $condition );

	}

	/**
	 * Determine whether the field values of the triggers are empty or not.
	 *
	 * @param int $trigger_id The Trigger ID.
	 * @param int $recipe_id The Recipe ID.
	 *
	 * @return boolean True if option fields from the Trigger are not empty. Otherwise, false.
	 */
	private function is_trigger_values_set( $trigger_id, $recipe_id ) {

		$is_valid = true;

		$where_conditions = (array) $this->get_where_conditions();

		if ( empty( $where_conditions ) ) {

			return false;

		}

		foreach ( $where_conditions as $trigger_option_code ) {

			$trigger_meta = Automator()->get->meta_from_recipes( $this->get_recipes(), $trigger_option_code );

			if ( ! isset( $trigger_meta[ $recipe_id ][ $trigger_id ] ) ) {
				$is_valid = false;
			}
		}

		return $is_valid;

	}

	/**
	 * Populates the `actual_where_values` properties with the actual values from option fields.
	 *
	 * @param int $trigger_id The Trigger ID.
	 * @param int $recipe_id The Recipe ID.
	 *
	 * @return array The actual where values.
	 */
	protected function get_where_values( $trigger_id, $recipe_id ) {

		// Reset the values. Hooks that executed multiple times can fill the where values.
		$this->actual_where_values = array();

		foreach ( $this->get_where_conditions() as $i => $trigger_option_code ) {

			$trigger_meta = Automator()->get->meta_from_recipes( $this->get_recipes(), $trigger_option_code );

			$where_value = $trigger_meta[ $recipe_id ][ $trigger_id ];

			$this->push_log( "with trigger id: {$trigger_id} from option_code: {$trigger_option_code}", 'recipe_trigger_field_values', $recipe_id );

			/**
			 * Ability for Trigger to overwrite this option in case zero is a valid option.
			 *
			 * @since 4.15.1
			 */
			$use_zero_as_any = apply_filters(
				'automator_recipe_filters_get_where_values_zero_as_any',
				true,
				$trigger_id,
				$recipe_id,
				$trigger_meta,
				$where_value
			);

			// Determine whethere to use zero as any and if the where_value is zero or not.
			$where_value_is_zero_as_any = ( true === $use_zero_as_any )
				// Make sure to check if where value is numeric as intval will cast string to 0.
				&& ( is_numeric( $where_value ) && 0 === intval( $where_value ) );

			if ( $where_value_is_zero_as_any || intval( - 1 ) === intval( $where_value ) ) {

				// Make the where value equals to match condition automatically so it becomes valid.
				$where_value = $this->match_conditions[ $i ];

				$this->push_log = sprintf( 'Comparing %s:%s as "Any"', gettype( $this->match_conditions[ $i ] ), $this->match_conditions[ $i ], 'any_assertions' );

			}

			$this->actual_where_values[] = $this->value_format( $where_value, $this->get_conditions_format( $i ) );

		}

		return $this->actual_where_values;

	}

	/**
	 * Determine if the number has matched the condition.
	 *
	 * @param int $trigger_id
	 * @param int $recipe_id
	 * @param string $required_comparison_symbol The comparison symbol (e.g. <, >, <=, !=).
	 *
	 * @return boolean True if condition matches. Otherwise, false
	 */
	private function has_number_condition_matched( $trigger_id, $recipe_id, $required_comparison_symbols ) {

		$required_comparison_symbol = ! empty( $required_comparison_symbols[ $recipe_id ][ $trigger_id ] ) ?
			$required_comparison_symbols[ $recipe_id ][ $trigger_id ] :
			false;

		if ( ! empty( $required_comparison_symbol ) ) {

			$compare_to = $this->get_number_condition_value_to_compare();

			$condition_from_field = Automator()->get->meta_from_recipes(
				$this->get_recipes(),
				$this->get_number_condition_value_from_field()
			);

			/**
			 * Fix issue catch by unit-test.
			 *
			 * @test-case Trait_Triggers_Recipe_Filters_Test::test_has_number_condition_matched_should_return_false
			 */
			$option_field_value = isset( $condition_from_field[ $recipe_id ][ $trigger_id ] ) ? $condition_from_field[ $recipe_id ][ $trigger_id ] : null;

			if ( ! empty( $option_field_value ) ) {

				$result = $this->match_condition(
					$required_comparison_symbol,
					intval( $option_field_value ),
					$compare_to
				);

				$this->push_log(
					sprintf(
						'Is %d %s %d ? ',
						$option_field_value,
						$required_comparison_symbol,
						$compare_to
					) . ( true === $result ? 'Matched' : 'False' ),
					'number_conditions',
					$recipe_id
				);

				return $result;

			}
		}

		return false;

	}

	/**
	 * Sets both `number_condition_value_to_compare` and `number_condition_value_from_field`.
	 *
	 * @param mixed $value_to_compare The value to compare to.
	 * @param mixed $value_from_field The value to compare from.
	 *
	 * @return \Uncanny_Automator\Trigger_Recipe_Filters The filters object.
	 */
	public function with_number_condition( $value_to_compare, $value_from_field ) {

		$this->number_condition_value_to_compare = intval( $value_to_compare );

		$this->number_condition_value_from_field = $value_from_field;

		return $this;

	}

	protected function get_number_condition_value_to_compare() {

		return $this->number_condition_value_to_compare;

	}

	protected function get_number_condition_value_from_field() {

		return $this->number_condition_value_from_field;

	}

	/**
	 * Determines whether both `number_condition_value_to_compare` and `number_condition_value_from_field` is set or not.
	 *
	 * @return boolean True if number conditions are set. Otherwise, false.
	 */
	protected function has_number_condition() {

		return ! empty( $this->get_number_condition_value_from_field() ) &&
			   ! empty( $this->get_number_condition_value_to_compare() );

	}

	protected function value_format( $value, callable $format = null ) {

		return $format( $value );

	}

	protected function explain() {

		$explain = array(
			'where_conditions_values' => $this->get_actual_where_values(),
			'match_conditions_values' => $this->get_match_conditions(),
			'compare'                 => $this->get_compare(),
			'conditions_format'       => $this->get_conditions_format(),
		);

		$explain['process'] = '<empty>';

		if ( ! empty( $this->log ) ) {
			$explain['process'] = $this->log;
		}

		return $explain;

	}

	/**
	 * Push specific log into the log object.
	 *
	 * @param string $mixed The text to log.
	 * @param string $key The logging key.
	 * @param int $recipe_id The recipe id.
	 *
	 * @return void
	 */
	private function push_log( $mixed = '', $key = '', $recipe_id = 0 ) {

		if ( ! empty( $key ) && ! empty( $recipe_id ) ) {

			$this->log[ $key ][ 'with_recipe_id{' . $recipe_id . '}' ][] = $mixed;

		}

	}

	/**
	 * Get the comparison symbol from specified index.
	 *
	 * @param mixed $index Array key to use to retrieve the symbol.
	 *
	 * @return string The comparison symbol.
	 */
	private function get_compare( $index = null ) {

		// If compare is not set, set equality sign to first index.
		// This would automatically make all compare have equal sign.
		if ( empty( $this->compare ) ) {
			$this->compare( array( '=' ) );
		}

		if ( is_numeric( $index ) && ! empty( $this->compare[ $index ] ) ) {
			return $this->compare[ $index ];
		}

		return $this->compare;

	}

}
