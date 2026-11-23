<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Sensei_User_Submits_Quiz
 *
 * Listens to Quiz_Submitted_Dispatcher, which announces each submission once,
 * whatever the result, including the one that completes a course. The learner is
 * always the one submitting, in their own session.
 *
 * @property Sensei_Lms_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 * @since 8.1.0
 */
class Sensei_User_Submits_Quiz extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'SENSEI_USER_SUBMITS_QUIZ', 'SENSEI_LMS' )
			->trigger_meta( 'SENSEI_QUIZ' )
			->hook( 'automator_sensei_lms_quiz_submitted', 10, 5 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Quiz.
				esc_html_x( 'A user submits {{a quiz:%1$s}}', 'Sensei LMS', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user submits {{a quiz}}', 'Sensei LMS', 'uncanny-automator' )
		);
	}

	/**
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Quiz', 'Sensei LMS', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'quizzes' ),
			),
		);
	}

	/**
	 * @param array $trigger The trigger definition.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens_class = new Sensei_Lms_Tokens();

		return array_merge(
			$tokens,
			$tokens_class->course_tokens(),
			$tokens_class->lesson_tokens(),
			$tokens_class->quiz_tokens()
		);
	}

	/**
	 * @param array $trigger   The trigger row.
	 * @param array $hook_args $user_id, $quiz_id, $grade, $pass_percentage, $grade_type.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $quiz_id ) = array_pad( (array) $hook_args, 2, null );

		$user_id = $this->item_helpers->positive_id( $user_id );
		$quiz_id = $this->item_helpers->positive_id( $quiz_id );

		if ( 0 === $user_id || 0 === $quiz_id ) {
			return false;
		}

		$selected = $this->item_helpers->get_selected_value( $trigger, $this->get_trigger_meta() );

		if ( ! $this->item_helpers->matches_selection( $selected, $quiz_id ) ) {
			return false;
		}

		if ( $this->item_helpers->is_temporary_user( $user_id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * @param array $trigger   The trigger row.
	 * @param array $hook_args $user_id, $quiz_id, $grade, $pass_percentage, $grade_type.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( , $quiz_id, $grade, $pass_mark, $type ) = array_pad( (array) $hook_args, 5, null );

		$quiz_id   = $this->item_helpers->positive_id( $quiz_id );
		$lesson_id = $this->item_helpers->get_lesson_id_from_quiz( $quiz_id );

		$tokens_class = new Sensei_Lms_Tokens();

		return array_merge(
			$tokens_class->hydrate_course_tokens( $this->item_helpers->get_course_id_from_lesson( $lesson_id ) ),
			$tokens_class->hydrate_lesson_tokens( $lesson_id ),
			$tokens_class->hydrate_quiz_tokens( $quiz_id, $grade, $pass_mark, (string) $type )
		);
	}
}
