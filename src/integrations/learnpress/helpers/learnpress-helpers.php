<?php


namespace Uncanny_Automator;

use LP_Course_CURD;
use LP_Section_CURD;
use Uncanny_Automator_Pro\Learnpress_Pro_Helpers;

/**
 * Class Learnpress_Helpers
 *
 * @package Uncanny_Automator
 */
class Learnpress_Helpers {
	/**
	 * @var Learnpress_Helpers
	 */
	public $options;
	/**
	 * @var Learnpress_Pro_Helpers
	 */
	public $pro;
	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Learnpress_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action(
			'wp_ajax_select_section_from_course_LPMARKLESSONDONE',
			array(
				$this,
				'select_section_from_course_func',
			)
		);
		add_action(
			'wp_ajax_select_lesson_from_section_LPMARKLESSONDONE',
			array(
				$this,
				'select_lesson_from_section_func',
			)
		);
		add_action(
			'wp_ajax_select_section_from_course_LPMARKLESSONDONE',
			array(
				$this,
				'select_section_from_course_func',
			)
		);
	}

	/**
	 * @param Learnpress_Helpers $options
	 */
	public function setOptions( Learnpress_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 * @param Learnpress_Pro_Helpers $pro
	 */
	public function setPro( Learnpress_Pro_Helpers $pro ) { // phpcs:ignore
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool   $any_option
	 *
	 * @return mixed
	 */
	public function all_lp_courses( $label = null, $option_code = 'LPCOURSE', $any_option = true ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'lp_course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any course', 'uncanny-automator' ) );

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
				$option_code          => esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Course URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_lp_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lp_lessons( $label = null, $option_code = 'LPLESSON', $any_option = true ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Lesson', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'lp_lesson',
			'posts_per_page' => 9999,
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
				$option_code          => esc_attr__( 'Lesson title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Lesson URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_lp_lessons', $option );
	}

	/**
	 * Return all the sections of course ID provided in ajax call
	 */
	public function select_section_from_course_func() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		$value = absint( automator_filter_input( 'value', INPUT_POST ) );

		if ( $value > 0 ) {
			$course_curd = new LP_Course_CURD();
			$sections    = $course_curd->get_course_sections( $value );

			foreach ( $sections as $section ) {
				$fields[] = array(
					'value' => $section->section_id,
					'text'  => $section->section_name,
				);
			}
		}

		echo wp_json_encode( $fields );

		die();
	}

	/**
	 * Return all the lessons of section ID provided in ajax call
	 */
	public function select_lesson_from_section_func() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		$values = automator_filter_input_array( 'values', INPUT_POST );

		$course_id = absint( $values['LPCOURSE'] );
		if ( $course_id > 0 ) {
			$course_curd = new LP_Section_CURD( $course_id );
			$lessons     = $course_curd->get_section_items( absint( automator_filter_input( 'value', INPUT_POST ) ) );

			foreach ( $lessons as $lesson ) {
				$fields[] = array(
					'value' => $lesson['id'],
					'text'  => $lesson['title'],
				);
			}
		}

		echo wp_json_encode( $fields );
		die();
	}
}
