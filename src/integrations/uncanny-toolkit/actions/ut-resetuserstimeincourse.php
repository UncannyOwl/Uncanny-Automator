<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator\Integrations\Uncanny_Toolkit;

/**
 * Action: Reset a user's time in a course.
 *
 * @property \Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers $item_helpers
 */
class UT_RESETUSERSTIMEINCOURSE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'UNCANNYTOOLKIT' );
		$this->set_action_code( 'RESETUSERSTIMEINCOURSE' );
		$this->set_action_meta( 'UTRESETUSERSTIMEINCOURSE' );
		// translators: %1$s is a course.
		$this->set_sentence( sprintf( esc_html_x( "Reset a user's time in {{a course:%1\$s}}", 'Uncanny Toolkit', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( "Reset a user's time in {{a course}}", 'Uncanny Toolkit', 'uncanny-automator' ) );
	}

	/**
	 * Check if Toolkit Pro is active.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return defined( 'UNCANNY_TOOLKIT_PRO_VERSION' );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Course', 'Uncanny Toolkit', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'courses_strict' ),
				'options'               => array(),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional args.
	 * @param array $parsed      Parsed field values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$active_modules = get_option( 'uncanny_toolkit_active_classes', true );

		if ( ! isset( $active_modules['uncanny_pro_toolkit\CourseTimer'] ) && empty( $active_modules['uncanny_pro_toolkit\CourseTimer'] ) ) {
			$this->add_log_error( esc_html_x( 'Simple course timer module is not active.', 'Uncanny Toolkit', 'uncanny-automator' ) );
			return false;
		}

		$ut_course_id = isset( $parsed[ $this->get_action_meta() ] ) ? absint( $parsed[ $this->get_action_meta() ] ) : 0;

		if ( empty( $ut_course_id ) ) {
			$this->add_log_error( esc_html_x( 'The selected course is not found.', 'Uncanny Toolkit', 'uncanny-automator' ) );
			return false;
		}

		if ( ! class_exists( '\uncanny_pro_toolkit\CourseTimer' ) ) {
			$this->add_log_error( esc_html_x( 'Simple course timer module is not active.', 'Uncanny Toolkit', 'uncanny-automator' ) );
			return false;
		}

		if ( ! method_exists( '\uncanny_pro_toolkit\CourseTimer', 'delete_user_course_data' ) ) {
			$this->add_log_error( esc_html_x( 'A required method is not available. Please update Uncanny Toolkit Pro to the latest version.', 'Uncanny Toolkit', 'uncanny-automator' ) );
			return false;
		}

		\uncanny_pro_toolkit\CourseTimer::delete_user_course_data( $user_id, $ut_course_id );

		return true;
	}
}
