<?php

namespace Uncanny_Automator;

/**
 * Class LD_COURSE_PROGRESS_PERCENTAGE
 * @package Uncanny_Automator
 */
class LD_COURSE_PROGRESS_PERCENTAGE extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * Setup trigger
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'LD' );
		$this->set_trigger_code( 'LD_COURSE_PROGRESS_PERCENTAGE' );
		$this->set_trigger_meta( 'LD_COURSE_PROGRESS_PERCENTAGE_META' );

		$this->set_sentence(
			sprintf(
			// translator: %1$s: percentage, %2$s: course
				esc_attr_x( 'A user has completed {{percentage:%1$s}}% of {{a course:%2$s}}', 'Learndash', 'uncanny-automator' ),
				'PERCENTAGE:' . $this->get_trigger_meta(),
				'COURSE:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'A user has completed {{X%}} of {{a course}}', 'Learndash', 'uncanny-automator' ) );

		$this->add_action( 'learndash_update_user_activity', 20, 1 );
	}

	/**
	 * @return array[]
	 */
	public function options() {

		$options = Automator()->helpers->recipe->learndash->options->all_ld_courses( '', $this->get_trigger_meta() );

		$course_options = array();
		foreach ( $options['options'] as $key => $option ) {
			$course_options[] = array(
				'text'  => $option,
				'value' => $key,
			);
		}
		return array(
			array(
				'input_type'            => 'int',
				'option_code'           => 'PERCENTAGE',
				'label'                 => esc_html_x( 'Percentage', 'LearnDash', 'uncanny-automator' ),
				'required'              => true,
				'default_value'         => 50,
				'supports_custom_value' => false,

			),
			array(
				'input_type'            => 'select',
				'option_code'           => 'COURSE',
				'label'                 => esc_html_x( 'Course', 'LearnDash', 'uncanny-automator' ),
				'required'              => true,
				'supports_custom_value' => false,
				'options'               => $course_options,
			),
		);
	}

	/**
	 * Validate whether the trigger conditions are met for course progress.
	 *
	 * @param array $trigger The trigger definition and metadata.
	 * @param array $hook_args Arguments from the learndash_update_user_activity hook.
	 *
	 * @return bool True if conditions are met and trigger should fire.
	 */
	public function validate( $trigger, $hook_args ) {

		if ( empty( $hook_args ) || ! is_array( $hook_args ) || ! isset( $hook_args[0] ) ) {
			return false;
		}

		$activity = $hook_args[0];

		if ( empty( $activity['user_id'] ) ) {
			return false;
		}
		if ( empty( $activity['course_id'] ) ) {
			return false;
		}
		if ( ! isset( $activity['activity_status'] ) || '' === $activity['activity_status'] ) {
			return false;
		}

		if ( 1 !== intval( $activity['activity_status'] ) ) {
			return false;
		}

		$user_id   = absint( $activity['user_id'] );
		$course_id = absint( $activity['course_id'] );

		$this->set_user_id( $user_id );

		// prevent duplicate triggers within a short window
		$key = 'automator_course_progressed_' . $user_id . '_' . $course_id;
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, 10 ); // 10 seconds throttle

		$selected_course     = isset( $trigger['meta']['COURSE'] ) ? intval( $trigger['meta']['COURSE'] ) : -1;
		$required_percentage = floatval( $trigger['meta']['PERCENTAGE'] ?? 0 );

		if ( -1 !== $selected_course && $selected_course !== $course_id ) {
			return false;
		}

		$progress = learndash_course_progress(
			array(
				'user_id'   => $user_id,
				'course_id' => $course_id,
				'array'     => true,
			)
		);

		if ( empty( $progress['percentage'] ) ) {
			return false;
		}

		$current_percentage = floatval( $progress['percentage'] );

		if ( $current_percentage >= $required_percentage ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param $trigger
	 * @param $tokens
	 * @return array
	 */
	public function define_tokens( $trigger, $hook_args ) {
		return array(

			'CURRENT_PERCENTAGE' => array(
				'tokenId'   => 'CURRENT_PERCENTAGE',
				'tokenName' => esc_html_x( 'Current percentage', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'number',
			),
			'COURSE_ID'          => array(
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			'COURSE_TITLE'       => array(
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			'COURSE_URL'         => array(
				'tokenId'   => 'COURSE_URL',
				'tokenName' => esc_html_x( 'Course URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$activity = $hook_args[0];

		$user_id            = absint( $activity['user_id'] ?? 0 );
		$course_id          = absint( $activity['course_id'] ?? 0 );
		$current_percentage = 0;

		// re-calculate the progress
		if ( $user_id && $course_id ) {
			$progress           = learndash_course_progress(
				array(
					'user_id'   => $user_id,
					'course_id' => $course_id,
					'array'     => true,
				)
			);
			$current_percentage = floatval( $progress['percentage'] ?? 0 );
		}

		return array(
			'CURRENT_PERCENTAGE' => $current_percentage,
			'COURSE_ID'          => $course_id,
			'COURSE_TITLE'       => ! empty( $course_id ) ? get_the_title( $course_id ) : '',
			'COURSE_URL'         => ! empty( $course_id ) ? get_permalink( $course_id ) : '',
			'PERCENTAGE'         => $trigger['meta']['PERCENTAGE'] ?? '',
		);
	}
}
