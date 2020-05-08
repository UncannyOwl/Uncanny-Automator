<?php

namespace Uncanny_Automator;

/**
 * Class LD_QUIZPERCENT
 * @package uncanny_automator
 */
class LD_QUIZPERCENT {

	/**
	 * Integration code
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

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnDash */
			'sentence'            => sprintf( __( 'A user achieves a percentage {{greater than, less than or equal:%1$s}} to {{a value:%2$s}} on {{a quiz:%3$s}} {{a number of:%4$s}} times', 'uncanny-automator' ), 'NUMBERCOND', 'QUIZPERCENT', $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  => __( 'A user achieves a percentage {{greater than, less than or equal}} to {{a value}} on {{a quiz}}', 'uncanny-automator' ),
			'action'              => 'learndash_quiz_completed',
			'priority'            => 15,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'learndash_quiz_completed' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options'             => [
				$uncanny_automator->helpers->recipe->field->less_or_greater_than(),
				$uncanny_automator->helpers->recipe->field->integer_field( 'QUIZPERCENT', __( 'Percentage', 'uncanny-automator' ), '' ),
				$uncanny_automator->helpers->recipe->learndash->options->all_ld_quiz(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

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

		global $uncanny_automator;

		$quiz                = $data['quiz'];
		$quiz_id             = is_object( $quiz ) ? $quiz->ID : $quiz;
		$percentage          = $data['percentage'];
		$recipes             = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		$required_percentage = $uncanny_automator->get->meta_from_recipes( $recipes, 'QUIZPERCENT' );
		$required_quiz       = $uncanny_automator->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$required_conditions = $uncanny_automator->get->meta_from_recipes( $recipes, 'NUMBERCOND' );
		$matched_recipe_ids  = [];

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( $uncanny_automator->utilities->match_condition_vs_number( $required_conditions[ $recipe_id ][ $trigger_id ], $required_percentage[ $recipe_id ][ $trigger_id ], $percentage ) ) {
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
					];
					$uncanny_automator->maybe_add_trigger_entry( $args );
				}
			}
		}
	}
}
