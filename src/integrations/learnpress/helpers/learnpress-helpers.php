<?php


namespace Uncanny_Automator;

use LearnPress\Models\UserItems\UserItemModel;
use LearnPress\Models\UserItems\UserLessonModel;
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
	public $load_options = true;

	/**
	 * Learnpress_Helpers constructor.
	 */
	public function __construct() {

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
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_lp_courses( $label = null, $option_code = 'LPCOURSE', $any_option = true ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_html_x( 'Course', 'Learnpress', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'lp_course',
			'posts_per_page' => 999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_html_x( 'Any course', 'Learnpress', 'uncanny-automator' ) );

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
				$option_code          => esc_html_x( 'Course title', 'Learnpress', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_html_x( 'Course ID', 'Learnpress', 'uncanny-automator' ),
				$option_code . '_URL' => esc_html_x( 'Course URL', 'Learnpress', 'uncanny-automator' ),
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
			$label = esc_html_x( 'Lesson', 'Learnpress', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'lp_lesson',
			'posts_per_page' => 9999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_html_x( 'Any lesson', 'Learnpress', 'uncanny-automator' ) );

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
				$option_code          => esc_html_x( 'Lesson title', 'Learnpress', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_html_x( 'Lesson ID', 'Learnpress', 'uncanny-automator' ),
				$option_code . '_URL' => esc_html_x( 'Lesson URL', 'Learnpress', 'uncanny-automator' ),
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
			global $wpdb;
			$sections = $wpdb->get_results( $wpdb->prepare( "SELECT section_id, section_name FROM {$wpdb->prefix}learnpress_sections WHERE section_course_id=%d", $value ) );

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

	/**
	 * @param $user_id
	 * @param $lesson_id
	 * @param $course_id
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function insert_user_item_model( $user_id, $lesson_id, $course_id ) {
		$lp_user_item_db = \LP_User_Items_DB::getInstance();

		return $lp_user_item_db->insert_data(
			array(
				'item_type'  => LP_LESSON_CPT,
				'ref_type'   => LP_COURSE_CPT,
				'user_id'    => $user_id,
				'item_id'    => $lesson_id,
				'start_time' => gmdate( 'Y-m-d H:i:s', time() ),
				'ref_id'     => $course_id,
			)
		);
	}

	/**
	 * @param $user_id
	 * @param $lesson_id
	 * @param $course_id
	 *
	 * @return false|UserItemModel|UserLessonModel
	 */
	public function get_user_lesson_model( $user_id, $lesson_id, $course_id ) {
		return UserLessonModel::find_user_item(
			$user_id,
			$lesson_id,
			LP_LESSON_CPT,
			$course_id,
			LP_COURSE_CPT,
			true
		);
	}
}
