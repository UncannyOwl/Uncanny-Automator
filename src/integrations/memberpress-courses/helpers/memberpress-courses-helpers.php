<?php

namespace Uncanny_Automator;

use memberpress\courses\lib as lib;
use memberpress\courses\models as models;
use Uncanny_Automator_Pro\Memberpress_Pro_Helpers;

/**
 * Class Memberpress_Courses_Helpers
 *
 * @package Uncanny_Automator
 */
class Memberpress_Courses_Helpers {
	/**
	 * @var Memberpress_Courses_Helpers
	 */
	public $options;

	/**
	 * @var Memberpress_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Memberpress_Courses_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action( 'wp_ajax_select_lesson_from_course_LESSONDONE', array( $this, 'select_lesson_from_course_func' ) );
		add_action(
			'wp_ajax_select_lesson_from_course_MARKLESSONDONE',
			array(
				$this,
				'select_lesson_from_course_no_any',
			)
		);
	}

	/**
	 * @param Memberpress_Courses_Helpers $options
	 */
	public function setOptions( Memberpress_Courses_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Memberpress_Pro_Helpers $pro
	 */
	public function setPro( Memberpress_Courses_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function select_lesson_from_course_no_any() {
		$this->load_any_options = false;
		$this->select_lesson_from_course_func( 'yes' );
		$this->load_any_options = true;
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 *
	 * @param string $include_any
	 */
	public function select_lesson_from_course_func() {
		global $wpdb;

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();
		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$mpcs_post_value  = automator_filter_input( 'value', INPUT_POST );
		$mpcs_post_values = automator_filter_input_array( 'values', INPUT_POST );

		if ( 'automator_custom_value' === (string) $mpcs_post_value && '-1' !== absint( $mpcs_post_value ) ) {
			$mpcs_course_id = isset( $mpcs_post_values['MPCOURSE_custom'] ) ? absint( $mpcs_post_values['MPCOURSE_custom'] ) : 0;
		} else {
			$mpcs_course_id = absint( $mpcs_post_values['MPCOURSE'] );
		}

		if ( absint( '-1' ) !== absint( $mpcs_course_id ) ) {

			$course          = new models\Course( $mpcs_course_id );
			$course_sections = (array) $course->sections();

			foreach ( $course_sections as $section ) {

				$curriculum['sections'][ $section->uuid ] = array(
					'id'        => $section->uuid,
					'title'     => $section->title,
					'lessonIds' => array(),
				);

				$section_lessons = $section->lessons();
				foreach ( $section_lessons as $lesson ) {

					$fields[] = array(
						'value' => $lesson->ID,
						'text'  => ( $section->title ) ? $section->title . ' -> ' . $lesson->post_title : $lesson->post_title,
					);
				}
			}
		}

		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_mp_courses( $label = null, $option_code = 'MPCOURSE', $any_option = true ) {
		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => models\Course::$cpt,
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => array(
				$option_code                => esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Course URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
			),
			'custom_value_description' => _x( 'Course ID', 'Memberpress', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_all_mp_courses', $option );
	}


	/**
	 * Find all by course
	 *
	 * @param integer $course_id
	 *
	 * @return Section[] Array of Section objects ordered by section_order
	 */
	public function find_all_by_course( $course_id ) {
		$db = new lib\Db();

		$records = $db->get_records( $db->sections, compact( 'course_id' ), 'section_order' );

		$sections = array();
		foreach ( $records as $rec ) {
			$sections[] = $rec->id;
		}

		return $sections;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function find_all_by_section( $section_id ) {
		global $wpdb;
		$post_types_string = models\Lesson::lesson_cpts();
		$post_types_string = implode( "','", $post_types_string );

		$query = $wpdb->prepare(
			"SELECT ID, post_type FROM {$wpdb->posts} AS p
	        JOIN {$wpdb->postmeta} AS pm
	          ON p.ID = pm.post_id
	         AND pm.meta_key = %s
	         AND pm.meta_value = %s
	        JOIN {$wpdb->postmeta} AS pm_order
	          ON p.ID = pm_order.post_id
	         AND pm_order.meta_key = %s
	       WHERE p.post_type in ( %s ) AND p.post_status <> 'trash'
	       ORDER BY pm_order.meta_value * 1",
			models\Lesson::$section_id_str,
			$section_id,
			models\Lesson::$lesson_order_str,
			stripcslashes( $post_types_string )
		);

		$db_lessons = $wpdb->get_results( stripcslashes( $query ) ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$lessons    = array();

		foreach ( $db_lessons as $lesson ) {
			if ( models\Quiz::$cpt === $lesson->post_type ) {
				$lessons[] = $lesson->ID;
			} else {
				$lessons[] = $lesson->ID;
			}
		}

		return $lessons;
	}

}
