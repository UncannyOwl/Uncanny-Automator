<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Masterstudy_Pro_Helpers;

/**
 * Class Masterstudy_Helpers
 * @package Uncanny_Automator
 */
class Masterstudy_Helpers {
	/**
	 * @var MasterstudyHelpers
	 */
	public $options;

	/**
	 * @var Masterstudy_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	public $load_any_options = true;

	/**
	 * Masterstudy_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action( 'wp_ajax_select_mslms_lesson_from_course_LESSONDONE', array(
			$this,
			'select_lesson_from_course_func',
		) );

		add_action( 'wp_ajax_select_mslms_quiz_from_course_QUIZ', array(
			$this,
			'select_quiz_from_course_func',
		) );
	}

	/**
	 * @param Masterstudy_Helpers $options
	 */
	public function setOptions( Masterstudy_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Masterstudy_Pro_Helpers $pro
	 */
	public function setPro( Masterstudy_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 *
	 * @param string $include_any
	 */
	public function select_lesson_from_course_func() {



		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check( $_POST );

		$fields = [
			[
				'value' => '-1',
				'text'  => _x( 'Any lesson', 'MasterStudy LMS', 'uncanny-automator' ),
			],
		];

		if ( ! isset( $_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$mslms_course_id = $_POST['values']['MSLMSCOURSE'];

		if ( absint( $mslms_course_id ) || '-1' === $mslms_course_id ) {
			global $wpdb;

			$course_lessons_q =
				"Select ID, post_title
				FROM $wpdb->posts
				WHERE FIND_IN_SET(
					ID,
					(SELECT meta_value FROM wp_postmeta WHERE post_id = %d AND meta_key = 'curriculum')
				)
				AND post_type = 'stm-lessons'
				ORDER BY post_title ASC";

			$course_lessons_p = $wpdb->prepare( $course_lessons_q, absint( $mslms_course_id ) );

			if ( '-1' === $mslms_course_id ) {
				$course_lessons_p =
					"Select ID, post_title FROM $wpdb->posts WHERE post_type = 'stm-lessons' ORDER BY post_title ASC";
			}


			$lessons = $wpdb->get_results( $course_lessons_p );

			foreach ( $lessons as $lesson ) {
				$fields[] = array(
					'value' => $lesson->ID,
					'text'  => $lesson->post_title,
				);
			}

		}

		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 *
	 * @param string $include_any
	 */
	public function select_quiz_from_course_func() {



		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check( $_POST );

		$fields = [
			[
				'value' => '-1',
				'text'  => _x( 'Any quiz', 'MasterStudy LMS', 'uncanny-automator' ),
			],
		];

		if ( ! isset( $_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$mslms_course_id = $_POST['values']['MSLMSCOURSE'];

		if ( absint( $mslms_course_id ) || '-1' === $mslms_course_id ) {
			global $wpdb;

			$course_quiz_q =
				"Select ID, post_title
				FROM $wpdb->posts
				WHERE FIND_IN_SET(
					ID,
					(SELECT meta_value FROM wp_postmeta WHERE post_id = %d AND meta_key = 'curriculum')
				)
				AND post_type = 'stm-quizzes'
				ORDER BY post_title ASC";

			$course_quiz_p = $wpdb->prepare( $course_quiz_q, absint( $mslms_course_id ) );

			if ( '-1' === $mslms_course_id ) {
				$course_quiz_p =
					"Select ID, post_title
				FROM $wpdb->posts
				WHERE post_type = 'stm-quizzes'
				ORDER BY post_title ASC";
			}

			$quizzes = $wpdb->get_results( $course_quiz_p );

			foreach ( $quizzes as $lesson ) {
				$fields[] = array(
					'value' => $lesson->ID,
					'text'  => $lesson->post_title,
				);
			}
		}

		echo wp_json_encode( $fields );
		die();
	}
}
