<?php


namespace Uncanny_Automator;

/**
 * Class Learnpress_Helpers
 * @package Uncanny_Automator
 */
class Learnpress_Helpers {
	/**
	 * Learnpress_Helpers constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_select_section_from_course_LPMARKLESSONDONE', array(
			$this,
			'select_section_from_course_func'
		) );
		add_action( 'wp_ajax_select_lesson_from_section_LPMARKLESSONDONE', array(
			$this,
			'select_lesson_from_section_func'
		) );
		add_action( 'wp_ajax_select_section_from_course_LPMARKLESSONDONE', [
			$this,
			'select_section_from_course_func'
		] );
	}

	/**
	 * @var Learnpress_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Learnpress_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Learnpress_Helpers $options
	 */
	public function setOptions( Learnpress_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Learnpress_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Learnpress_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_lp_courses( $label = null, $option_code = 'LPCOURSE', $any_option = true ) {

		if ( ! $label ) {
			$label = __( 'Select a Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'lp_course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, 'course' );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Course Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Course URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lp_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lp_lessons( $label = null, $option_code = 'LPLESSON', $any_option = true ) {

		if ( ! $label ) {
			$label = __( 'Select a Lesson', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'lp_lesson',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, 'lesson' );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Lesson Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Lesson URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lp_lessons', $option );
	}

	/**
	 * Return all the sections of course ID provided in ajax call
	 */
	public function select_section_from_course_func() {
		global $uncanny_automator;

		// Nonce and post object validation
		$uncanny_automator->utilities->ajax_auth_check( $_POST );

		$fields = [];
		if ( isset( $_POST ) ) {

			$course_curd = new \LP_Course_CURD();
			$sections    = $course_curd->get_course_sections( absint( $_POST['value'] ) );

			foreach ( $sections as $section ) {
				$fields[] = [
					'value' => $section->section_id,
					'text'  => $section->section_name,
				];
			}
		}

		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Return all the lessons of section ID provided in ajax call
	 */
	public function select_lesson_from_section_func() {
		global $uncanny_automator;

		// Nonce and post object validation
		$uncanny_automator->utilities->ajax_auth_check( $_POST );

		$fields = [];
		if ( isset( $_POST ) ) {
			$course_curd = new \LP_Section_CURD( absint( $_POST['values']['LPCOURSE'] ) );
			$lessons     = $course_curd->get_section_items( absint( $_POST['value'] ) );

			foreach ( $lessons as $lesson ) {
				$fields[] = [
					'value' => $lesson['id'],
					'text'  => $lesson['title'],
				];
			}
		}

		echo wp_json_encode( $fields );
		die();
	}
}