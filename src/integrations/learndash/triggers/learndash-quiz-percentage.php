<?php

namespace Uncanny_Automator\Integrations\Learndash;

/**
 * Class LD_QUIZPERCENT
 *
 * @property \Uncanny_Automator\Integrations\Learndash\Ld_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Learndash
 */
class LD_QUIZPERCENT extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'LD_QUIZPERCENT', 'LD' )
			->trigger_meta( 'LDQUIZ' )
			->hook( 'learndash_quiz_submitted', 15, 2 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user achieves a percentage {{greater than, less than or equal to:%1$s}} {{a value:%2$s}} on {{a quiz:%3$s}}', 'LearnDash', 'uncanny-automator' ),
				'NUMBERCOND:' . $this->get_trigger_meta(),
				'QUIZPERCENT:' . $this->get_trigger_meta(),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on {{a quiz}}', 'LearnDash', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			Ld_Helpers::comparison_field(),
			array(
				'option_code' => 'QUIZPERCENT',
				'label'       => esc_html_x( 'Required percentage', 'LearnDash', 'uncanny-automator' ),
				'input_type'  => 'int',
				'required'    => true,
				'placeholder' => esc_html_x( 'Example: 80', 'LearnDash', 'uncanny-automator' ),
				'default'     => '',
			),
			array(
				'option_code'              => $this->get_trigger_meta(),
				'label'                    => esc_html_x( 'Quiz', 'LearnDash', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'options'                  => array(),
				'custom_value_description' => esc_html_x( 'Quiz ID', 'LearnDash', 'uncanny-automator' ),
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'quizzes' ),
			),
		);
	}

	/**
	 * Define additional tokens for this trigger.
	 *
	 * @param array $trigger The trigger definition.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens_class = new Ld_Tokens_New_Framework();

		return array_merge(
			$tokens,
			$tokens_class->quiz_tokens(),
			$tokens_class->quiz_percent_tokens()
		);
	}

	/**
	 * Validate whether the trigger should fire.
	 *
	 * @param array $trigger   The trigger definition and metadata.
	 * @param array $hook_args The arguments from the WP hook.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $data, $current_user ) = $hook_args;

		if ( empty( $data ) ) {
			return false;
		}

		$quiz       = $data['quiz'] ?? null;
		$quiz_id    = is_object( $quiz ) ? $quiz->ID : $quiz;
		$percentage = $data['percentage'] ?? 0;
		$user       = $current_user;

		if ( empty( $user ) ) {
			$user = wp_get_current_user();
		}

		$this->set_user_id( $user->ID );

		$condition           = $trigger['meta']['NUMBERCOND'] ?? null;
		$required_percentage = $trigger['meta']['QUIZPERCENT'] ?? 0;
		$selected            = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		// Check condition first.
		if ( ! Automator()->utilities->match_condition_vs_number( $condition, $required_percentage, $percentage ) ) {
			return false;
		}

		// Check quiz match.
		if ( intval( '-1' ) !== intval( $selected ) && absint( $selected ) !== absint( $quiz_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hydrate tokens with actual values.
	 *
	 * @param array $trigger   The trigger definition.
	 * @param array $hook_args The arguments from the WP hook.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $data, $current_user ) = $hook_args;

		$quiz       = $data['quiz'] ?? null;
		$quiz_id    = is_object( $quiz ) ? $quiz->ID : $quiz;
		$percentage = $data['percentage'] ?? 0;

		$tokens_class       = new Ld_Tokens_New_Framework();
		$passing_percentage = (int) learndash_get_setting( $quiz_id, 'passingpercentage' );

		return array_merge(
			$tokens_class->hydrate_quiz_tokens( $quiz_id, $data ),
			$tokens_class->hydrate_quiz_percent_tokens( $percentage, $passing_percentage ),
			array( $this->get_trigger_meta() => get_the_title( $quiz_id ) )
		);
	}
}
