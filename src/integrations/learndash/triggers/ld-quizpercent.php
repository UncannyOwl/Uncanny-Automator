<?php

namespace Uncanny_Automator;

/**
 * Class LD_QUIZPERCENT
 * @package Uncanny_Automator
 */
class LD_QUIZPERCENT {
	//use Recipe\Triggers;

	/**
	 * LD_QUIZPERCENT constructor.
	 */
	public function __construct() {
		//$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	protected function setup_trigger() {
		$this->set_integration( 'LD' );
		$this->set_trigger_code( 'LD_QUIZPERCENT' );
		$this->set_trigger_meta( 'LDQUIZ' );
		/* Translators: Some information for translators */
		$this->set_sentence( sprintf( esc_attr__( 'A user achieves a percentage {{greater than, less than or equal to:%1$s}} {{a value:%2$s}} on {{a quiz:%3$s}} {{a number of:%4$s}} time(s)', 'uncanny-automator' ), 'NUMBERCOND', 'QUIZPERCENT', $this->get_trigger_meta(), 'NUMTIMES' ) );
		/* Translators: Some informat ion for translators */
		$this->set_readable_sentence( esc_attr__( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on {{a quiz}}', 'uncanny-automator' ) );

		$this->add_action( 'learndash_quiz_completed', 15, 2 );

		$this->set_options(
			array(
				Automator()->helpers->recipe->field->less_or_greater_than(),
				Automator()->helpers->recipe->field->integer_field( 'QUIZPERCENT', esc_attr__( 'Percentage', 'uncanny-automator' ), '' ),
				Automator()->helpers->recipe->learndash->options->all_ld_quiz(),
				Automator()->helpers->recipe->options->number_of_times(),
			)
		);

		$this->register_trigger();

	}

	/**
	 * @param $args
	 *
	 * @return array
	 */
	protected function do_action_args( $args ) {

		return array(
			'data' => $args[0],
			'user' => $args[1],
		);
	}

	/**
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( $args ) {
		if ( ! isset( $args['data'] ) || ! isset( $args['data']['quiz'] ) || empty( $args['data']['quiz'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param $args
	 */
	protected function prepare_to_run( $args ) {
		$this->set_conditional_trigger( true );
	}

	protected function validate_conditions( $args ) {
		$matched_recipe_ids = array();
		automator_log( $args, '$args', true, 'validate_conditions' );
		/*
		 * Get recipes that matches the current trigger.
		 */
		$recipes    = $this->trigger_recipes();
		$quiz       = $args['data']['quiz'];
		$quiz_id    = is_object( $quiz ) ? $quiz->ID : $quiz;
		$percentage = $args['data']['percentage'];
		automator_log( array(
			'$percentage' => $percentage,
			'$quiz_id'    => $quiz_id,
			'$quiz'       => $quiz,
		), '$args', true, 'validate_conditions' );
		if ( empty( $recipes ) ) {
			return $matched_recipe_ids;
		}
		$required_percentage = Automator()->get->meta_from_recipes( $recipes, 'QUIZPERCENT' );
		$required_quiz       = Automator()->get->meta_from_recipes( $recipes, $this->get_trigger_meta() );
		$required_conditions = Automator()->get->meta_from_recipes( $recipes, 'NUMBERCOND' );
		automator_log( array(
			'$required_percentage' => $required_percentage,
			'$required_quiz'       => $required_quiz,
			'$required_conditions' => $required_conditions,
			'$recipes'             => $recipes,
		), '$args', true, 'validate_conditions' );

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( Automator()->utilities->match_condition_vs_number( $required_conditions[ $recipe_id ][ $trigger_id ], $required_percentage[ $recipe_id ][ $trigger_id ], $percentage ) ) {
					$matched_recipe_ids[ $recipe_id ] = $trigger_id;
				}
			}
		}
		automator_log( $matched_recipe_ids, '$matched_recipe_ids', true, 'validate_conditions' );

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				//Any Quiz OR a specific quiz
				$r_quiz = (int) $required_quiz[ $matched_recipe_id['recipe_id'] ][ $matched_recipe_id['trigger_id'] ];
				if ( intval( '-1' ) === $r_quiz || $r_quiz === (int) $quiz_id ) {
					$matched_recipe_ids[ $matched_recipe_id['recipe_id'] ] = $matched_recipe_id['trigger_id'];
				}
			}
		}

		automator_log( $matched_recipe_ids, '$matched_recipe_ids-2', true, 'validate_conditions' );

		return $this->unique_recipes( $matched_recipe_ids, count( $matched_recipe_ids ) );
	}

	/**
	 * @param mixed ...$args
	 *
	 * @return false
	 */
//	protected function trigger_conditions( $args ) {
//		$quiz       = $args['data']['quiz'];
//		$quiz_id    = is_object( $quiz ) ? $quiz->ID : $quiz;
//		$percentage = $args['data']['percentage'];
////		$args1      = [
////			'post_type'      => 'sfwd-quiz',
////			'posts_per_page' => 9999,
////			'orderby'        => 'title',
////			'order'          => 'ASC',
////			'post_status'    => 'publish',
////		];
//		//$quiz_ids   = array_keys( Automator()->helpers->recipe->options->wp_query( $args1, true ) );
//		$this->do_find_any( true );
//		// Match specific condition
//		$this->do_find_this( $this->get_trigger_meta() );
//		$this->do_find_in( $quiz_id );
//		$this->do_compare_this_numb_cond( 'QUIZPERCENT' );
//		$this->do_compare_numb_cond_in( $percentage );
//	}

//	protected function unique_recipes( $matched_recipe_ids, $count ) {
//
//		return $this->match_internal_condition( $matched_recipe_ids );
//	}
//
//	private function match_internal_condition( $match ) {
//		automator_log( $match, '$matched_recipe_ids', true, '$matched_recipe_ids' );
//		automator_log( $this->get_conditions(), 'get_conditions()', true, '$matched_recipe_ids' );
//		$required_conditions = Automator()->get->meta_from_recipes( $recipes, 'NUMBERCOND' );
//		return $match;
//	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 * @param $current_user
	 */
	public function learndash_quiz_completed( $data, $current_user ) {

		if ( empty( $data ) ) {
			return;
		}

		$quiz                = $data['quiz'];
		$quiz_id             = is_object( $quiz ) ? $quiz->ID : $quiz;
		$percentage          = $data['percentage'];
		$recipes             = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_percentage = Automator()->get->meta_from_recipes( $recipes, 'QUIZPERCENT' );
		$required_quiz       = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$required_conditions = Automator()->get->meta_from_recipes( $recipes, 'NUMBERCOND' );
		$matched_recipe_ids  = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( Automator()->utilities->match_condition_vs_number( $required_conditions[ $recipe_id ][ $trigger_id ], $required_percentage[ $recipe_id ][ $trigger_id ], $percentage ) ) {
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				//Any Quiz OR a specific quiz
				$r_quiz = (int) $required_quiz[ $matched_recipe_id['recipe_id'] ][ $matched_recipe_id['trigger_id'] ];
				if ( - 1 === $r_quiz || $r_quiz === (int) $quiz_id ) {
					$args = [
						'code'             => $this->trigger_code,
						'meta'             => $this->trigger_meta,
						'user_id'          => $current_user->ID,
						'recipe_to_match'  => $matched_recipe_id['recipe_id'],
						'trigger_to_match' => $matched_recipe_id['trigger_id'],
						'ignore_post_id'   => true,
						'post_id'          => $quiz_id,
					];

					$result = Automator()->maybe_add_trigger_entry( $args, false );

					if ( $result ) {
						foreach ( $result as $r ) {
							if ( true === $r['result'] ) {
								if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
									$trigger_id     = (int) $r['args']['trigger_id'];
									$user_id        = (int) $r['args']['user_id'];
									$trigger_log_id = (int) $r['args']['get_trigger_id'];
									$run_number     = (int) $r['args']['run_number'];

									$insert = [
										'user_id'        => $user_id,
										'trigger_id'     => $trigger_id,
										'trigger_log_id' => $trigger_log_id,
										'meta_key'       => 'QUIZPERCENT',
										'meta_value'     => $percentage,
										'run_number'     => $run_number,
									];

									Automator()->insert_trigger_meta( $insert );
								}

								Automator()->maybe_trigger_complete( $r['args'] );
							}
						}
					}
				}
			}
		}
	}
}
