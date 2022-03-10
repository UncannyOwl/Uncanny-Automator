<?php

namespace Uncanny_Automator;

/**
 * Class LD_QUIZPERCENT
 *
 * @package Uncanny_Automator
 */
class LD_QUIZPERCENT {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LD';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LD_QUIZPERCENT';
		$this->trigger_meta = 'LDQUIZ';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/learndash/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnDash */
			'sentence'            => sprintf( esc_attr__( 'A user achieves a percentage {{greater than, less than or equal to:%1$s}} {{a value:%2$s}} on {{a quiz:%3$s}} {{a number of:%4$s}} time(s)', 'uncanny-automator' ), 'NUMBERCOND', 'QUIZPERCENT', $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  => esc_attr__( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on {{a quiz}}', 'uncanny-automator' ),
			'action'              => 'learndash_quiz_submitted',
			'priority'            => 15,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'learndash_quiz_submitted' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options'             => array(
				Automator()->helpers->recipe->field->less_or_greater_than(),
				Automator()->helpers->recipe->field->int(
					array(
						'option_code' => 'QUIZPERCENT',
						'label'       => esc_attr__( 'Required percentage', 'uncanny-automator' ),
						'placeholder' => esc_attr__( 'Example: 80', 'uncanny-automator' ),
						'default'     => '80',
					)
				),
				Automator()->helpers->recipe->learndash->options->all_ld_quiz(),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 * @param $current_user
	 */
	public function learndash_quiz_submitted( $data, $current_user ) {
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
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}
		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			//Any Quiz OR a specific quiz
			$r_quiz = (int) $required_quiz[ $matched_recipe_id['recipe_id'] ][ $matched_recipe_id['trigger_id'] ];
			if ( intval( '-1' ) !== intval( $r_quiz ) && absint( $r_quiz ) !== (int) $quiz_id ) {
				continue;
			}
			$args   = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $current_user->ID,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'ignore_post_id'   => true,
				'post_id'          => $quiz_id,
			);
			$result = Automator()->maybe_add_trigger_entry( $args, false );
			if ( empty( $result ) ) {
				continue;
			}
			foreach ( $result as $r ) {
				if ( false === $r['result'] ) {
					continue;
				}
				$trigger_id     = (int) $r['args']['trigger_id'];
				$user_id        = (int) $r['args']['user_id'];
				$trigger_log_id = (int) $r['args']['trigger_log_id'];
				$run_number     = (int) $r['args']['run_number'];

				$insert = array(
					'user_id'        => $user_id,
					'trigger_id'     => $trigger_id,
					'trigger_log_id' => $trigger_log_id,
					'meta_key'       => 'LDQUIZ_achieved_percent',
					'meta_value'     => $percentage,
					'run_number'     => $run_number,
				);
				Automator()->insert_trigger_meta( $insert );

				$insert = array(
					'user_id'        => $user_id,
					'trigger_id'     => $trigger_id,
					'trigger_log_id' => $trigger_log_id,
					'meta_key'       => 'LDQUIZ_quiz_passing_percentage',
					'meta_value'     => (int) learndash_get_setting( $quiz_id, 'passingpercentage' ),
					'run_number'     => $run_number,
				);
				Automator()->insert_trigger_meta( $insert );

				$insert['meta_key']   = 'quiz_id';
				$insert['meta_value'] = $quiz_id;
				Automator()->insert_trigger_meta( $insert );

				Automator()->maybe_trigger_complete( $r['args'] );
			}
		}
	}
}
