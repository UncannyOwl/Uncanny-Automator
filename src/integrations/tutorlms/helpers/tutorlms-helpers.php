<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Tutorlms_Pro_Helpers;
use function tutor;
use function tutor_utils;

/**
 * Class Tutorlms_Helpers
 *
 * @package Uncanny_Automator
 */
class Tutorlms_Helpers {
	/**
	 * @var Tutorlms_Helpers
	 */
	public $options;

	/**
	 * @var Tutorlms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * @var bool
	 */
	public $load_any_options = true;

	/**
	 * Tutorlms_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		add_action( 'wp_ajax_select_lesson_from_course_LESSONCOMPLETED', array( $this, 'select_lesson_from_course_func' ) );
	}

	/**
	 * @param Tutorlms_Helpers $options
	 */
	public function setOptions( Tutorlms_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Tutorlms_Pro_Helpers $pro
	 */
	public function setPro( Tutorlms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Creates options for trigger.
	 *
	 * @since 2.4.0
	 */
	public function all_tutorlms_lessons( $label = null, $option_code = 'TUTORLMSLESSON', $any_option = false ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Lesson', 'uncanny-automator' );
		}

		// post query arguments.
		$args = array(
			'post_type'      => tutor()->lesson_post_type,
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',

		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any lesson', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Lesson title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Lesson URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Lesson featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Lesson featured image URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_tutorlms_lessons', $option );

	}

	/**
	 * Creates options for trigger.
	 *
	 * @since 2.4.0
	 */
	public function all_tutorlms_courses( $label = null, $option_code = 'TUTORLMSCOURSE', $all_label = false, $any_option = false ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Course', 'uncanny-automator' );
		}

		// post query arguments.
		$args = array(
			'post_type'      => tutor()->course_post_type,
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',

		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any course', 'uncanny-automator' ), $all_label );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Course URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_tutorlms_courses', $option );

	}

	/**
	 * Creates options for trigger.
	 *
	 * @since 2.4.0
	 */
	public function all_tutorlms_quizzes( $label = null, $option_code = 'TUTORLMSQUIZ', $any_option = false ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Quiz', 'uncanny-automator' );
		}

		// post query arguments.
		$args = array(
			'post_type'      => 'tutor_quiz',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',

		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any quiz', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code          => esc_attr__( 'Quiz title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Quiz URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_tutorlms_quizzes', $option );

	}

	/**
	 * Checks if a quiz attempt was successful.
	 *
	 * @param $attempt object.
	 *
	 * @since 2.4.0
	 */
	public function was_quiz_attempt_successful( $attempt ) {

		// if the earned grade is less than or equal to zero, they failed.
		if ( $attempt->earned_marks <= 0 ) {
			return false;
		}

		// return pass or fail based on whether the required score was met.
		return ( $this->get_percentage_scored( $attempt ) >= $this->get_percentage_required( $attempt ) );
	}

	/**
	 * Calculates percentage scored.
	 *
	 * @param object $attempt The quiz attempt object.
	 *
	 * @return int
	 * @since 2.4.0
	 */
	public function get_percentage_scored( $attempt ) {
		return number_format( ( $attempt->earned_marks * 100 ) / $attempt->total_marks );
	}

	/**
	 * Retrieves required percentage.
	 *
	 * @param object $attempt The quiz attempt object.
	 *
	 * @return int
	 * @since 2.4.0
	 */
	public function get_percentage_required( $attempt ) {
		return (int) tutor_utils()->get_quiz_option( $attempt->quiz_id, 'passing_grade', 0 );
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 *
	 */
	public function select_lesson_from_course_func() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();
		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$post_value  = automator_filter_input( 'value', INPUT_POST );
		$post_values = automator_filter_input_array( 'values', INPUT_POST );

		if ( 'automator_custom_value' === (string) $post_value && '-1' !== absint( $post_value ) ) {
			$course_id = isset( $post_values['TUTORLMSCOURSE_custom'] ) ? absint( $post_values['TUTORLMSCOURSE_custom'] ) : 0;
		} else {
			$course_id = absint( $post_values['TUTORLMSCOURSE'] );
		}

		if ( absint( '-1' ) === absint( $course_id ) || true === (bool) $this->load_any_options ) {
			$fields[] = array(
				'value' => '-1',
				'text'  => 'Any lesson',
			);
		}

		if ( absint( '-1' ) !== absint( $course_id ) ) {
			$lesson_ids = tutor_utils()->get_course_content_ids_by( tutor()->lesson_post_type, tutor()->course_post_type, $course_id );

			if ( ! empty( $lesson_ids ) ) {
				$args = array(
					'post_type'      => tutor()->lesson_post_type,
					'posts_per_page' => 999,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'post_status'    => 'publish',
					'post__in'       => $lesson_ids,
				);

				$lessons = get_posts( $args );

				foreach ( $lessons as $lesson ) {
					$fields[] = array(
						'value' => $lesson->ID,
						'text'  => $lesson->post_title,
					);
				}
			}
		}

		echo wp_json_encode( $fields );
		die();
	}
}
