<?php

namespace Uncanny_Automator\Integrations\Uncanny_Toolkit;

/**
 * Trigger: A user's time in a course exceeds a specific number of minutes.
 *
 * @property \Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers $item_helpers
 */
class UT_USERS_TIME_IN_COURSE_EXCEEDS extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'UTUSERSTIMEINCOURSEEXCEEDS', 'UNCANNYTOOLKIT' )
			->trigger_meta( 'UOUSERSTIMEINCOURSEEXCEEDS' )
			->hook( 'uo_course_timer_add_timer', 20, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		// translators: %1$s is a course, %2$s is a number of minutes.
		$this->set_sentence( sprintf( esc_html_x( "A user's time in {{a course:%1\$s}} exceeds {{a specific number of:%2\$s}} minutes", 'Uncanny Toolkit', 'uncanny-automator' ), $this->get_trigger_meta(), $this->get_trigger_meta() . '_COURSEMINUTES' ) );
		$this->set_readable_sentence( esc_html_x( "A user's time in {{a course}} exceeds {{a specific number of}} minutes", 'Uncanny Toolkit', 'uncanny-automator' ) );
	}

	/**
	 * Check if Toolkit Pro and LearnDash are active.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return defined( 'UNCANNY_TOOLKIT_PRO_VERSION' ) && defined( 'LEARNDASH_VERSION' );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Course', 'Uncanny Toolkit', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'remote_data' => $this->item_helpers->remote_data_load_config( 'courses' ),
				'options'     => array(),
			),
			array(
				'option_code' => $this->get_trigger_meta() . '_COURSEMINUTES',
				'label'       => esc_html_x( 'Minutes', 'Uncanny Toolkit', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'token_name'  => esc_html_x( 'Time in minutes', 'Uncanny Toolkit', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			array(
				array(
					'tokenId'   => $this->get_trigger_meta() . '_ID',
					'tokenName' => esc_html_x( 'Course ID', 'Uncanny Toolkit', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => $this->get_trigger_meta() . '_URL',
					'tokenName' => esc_html_x( 'Course URL', 'Uncanny Toolkit', 'uncanny-automator' ),
					'tokenType' => 'url',
				),
				array(
					'tokenId'   => $this->get_trigger_meta() . '_THUMB_ID',
					'tokenName' => esc_html_x( 'Course thumbnail ID', 'Uncanny Toolkit', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => $this->get_trigger_meta() . '_THUMB_URL',
					'tokenName' => esc_html_x( 'Course thumbnail URL', 'Uncanny Toolkit', 'uncanny-automator' ),
					'tokenType' => 'url',
				),
			)
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * The old code uses a custom "greater than" comparison (timer > minutes * 60),
	 * NOT match_condition_vs_number(). We preserve that exact logic.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $course_id, $post_id, $timer_interval ) = $hook_args;

		if ( ! is_numeric( $course_id ) ) {
			return false;
		}

		// Ensure CourseTimer class and method are available.
		if ( ! class_exists( '\uncanny_pro_toolkit\CourseTimer' ) || ! method_exists( '\uncanny_pro_toolkit\CourseTimer', 'get_course_time_in_seconds' ) ) {
			return false;
		}

		$selected_course = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		// Check course match (Any or specific).
		if ( '-1' !== $selected_course && (int) $selected_course !== (int) $course_id ) {
			return false;
		}

		// Get the configured minutes threshold.
		$minutes = isset( $trigger['meta'][ $this->get_trigger_meta() . '_COURSEMINUTES' ] )
			? intval( $trigger['meta'][ $this->get_trigger_meta() . '_COURSEMINUTES' ] )
			: 0;

		// Get actual time spent.
		$user_id = get_current_user_id();
		$timer   = intval( \uncanny_pro_toolkit\CourseTimer::get_course_time_in_seconds( $course_id, $user_id ) );

		// Original logic: timer exceeds threshold (strictly greater than).
		return $timer > ( $minutes * 60 );
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $course_id ) = $hook_args;

		$minutes = isset( $trigger['meta'][ $this->get_trigger_meta() . '_COURSEMINUTES' ] )
			? $trigger['meta'][ $this->get_trigger_meta() . '_COURSEMINUTES' ]
			: 0;

		return array(
			$this->get_trigger_meta()                    => get_the_title( $course_id ),
			$this->get_trigger_meta() . '_ID'            => $course_id,
			$this->get_trigger_meta() . '_COURSEMINUTES' => $minutes,
			$this->get_trigger_meta() . '_URL'           => get_the_permalink( $course_id ),
			$this->get_trigger_meta() . '_THUMB_ID'      => get_post_thumbnail_id( $course_id ),
			$this->get_trigger_meta() . '_THUMB_URL'     => get_the_post_thumbnail_url( $course_id ),
		);
	}
}
