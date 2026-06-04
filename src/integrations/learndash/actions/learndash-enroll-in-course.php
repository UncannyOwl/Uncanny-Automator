<?php

namespace Uncanny_Automator\Integrations\Learndash;

/**
 * Class LD_ENRLCOURSE_A
 *
 * @package Uncanny_Automator\Integrations\Learndash
 *
 * @property \Uncanny_Automator\Integrations\Learndash\Ld_Helpers $item_helpers
 */
class LD_ENRLCOURSE_A extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Set up the action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'LD' );
		$this->set_action_code( 'ENRLCOURSE-A' );
		$this->set_action_meta( 'LDCOURSE' );

		$this->set_sentence(
			sprintf(
				esc_html_x( 'Enroll the user in {{a course:%1$s}}', 'LearnDash', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Enroll the user in {{a course}}', 'LearnDash', 'uncanny-automator' )
		);

	}

	/**
	 * Define action tokens.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {
		$tokens_class = new Ld_Tokens_New_Framework();
		return Ld_Tokens_New_Framework::to_action_tokens( $tokens_class->course_tokens() );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'              => $this->get_action_meta(),
				'label'                    => esc_html_x( 'Course', 'LearnDash', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'options'                  => array(),
				'supports_custom_value'    => true,
				'custom_value_description' => esc_html_x( 'Course ID', 'LearnDash', 'uncanny-automator' ),
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'courses_strict' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! function_exists( 'ld_update_course_access' ) ) {
			$this->add_log_error( 'The function ld_update_course_access does not exist' );

			return false;
		}

		$course_id = absint( $parsed[ $this->get_action_meta() ] );

		// Enroll to New Course.
		ld_update_course_access( $user_id, $course_id );

		// Hydrate Action Tokens.
		$tokens_class = new Ld_Tokens_New_Framework();
		$this->hydrate_tokens( $tokens_class->hydrate_course_tokens( $course_id, $user_id ) );

		return true;
	}
}
