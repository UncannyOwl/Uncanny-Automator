<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Masterstudy_Pro_Helpers;

/**
 * Class Masterstudy_Helpers
 *
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

		add_action(
			'wp_ajax_select_mslms_lesson_from_course_LESSONDONE',
			array(
				$this,
				'select_lesson_from_course_func',
			)
		);

		add_action(
			'wp_ajax_select_mslms_quiz_from_course_QUIZ',
			array(
				$this,
				'select_quiz_from_course_func',
			)
		);
	}

	/**
	 * @param Masterstudy_Helpers $options
	 */
	public function setOptions( Masterstudy_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 * @param Masterstudy_Pro_Helpers $pro
	 */
	public function setPro( Masterstudy_Pro_Helpers $pro ) { // phpcs:ignore
		$this->pro = $pro;
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 *
	 * @param string $include_any
	 */
	public function select_lesson_from_course_func() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array(
			array(
				'value' => '-1',
				'text'  => _x( 'Any lesson', 'MasterStudy LMS', 'uncanny-automator' ),
			),
		);

		$values = automator_filter_input_array( 'values', INPUT_POST );

		if ( empty( $values['MSLMSCOURSE'] ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$mslms_course_id = $values['MSLMSCOURSE'];

		if ( absint( $mslms_course_id ) || '-1' === $mslms_course_id ) {
			global $wpdb;

			$lessons = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title
					FROM $wpdb->posts
					WHERE FIND_IN_SET(
						ID,
						(SELECT meta_value FROM wp_postmeta WHERE post_id = %d AND meta_key = 'curriculum')
					)
					AND post_type = 'stm-lessons'
					ORDER BY post_title ASC",
					absint( $mslms_course_id )
				)
			);

			if ( '-1' === $mslms_course_id ) {
				$lessons = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_title FROM $wpdb->posts WHERE post_type = %s ORDER BY post_title ASC",
						'stm-lessons'
					)
				);
			}

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
		Automator()->utilities->ajax_auth_check();

		$fields = array(
			array(
				'value' => '-1',
				'text'  => _x( 'Any quiz', 'MasterStudy LMS', 'uncanny-automator' ),
			),
		);

		$values = automator_filter_input_array( 'values', INPUT_POST );

		if ( empty( $values['MSLMSCOURSE'] ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$mslms_course_id = $values['MSLMSCOURSE'];

		if ( absint( $mslms_course_id ) || '-1' === $mslms_course_id ) {

			global $wpdb;

			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title
					FROM $wpdb->posts
					WHERE FIND_IN_SET(
						ID,
						(SELECT meta_value FROM wp_postmeta WHERE post_id = %d AND meta_key = 'curriculum')
					)
					AND post_type = 'stm-quizzes'
					ORDER BY post_title ASC
					",
					absint( $mslms_course_id )
				)
			);

			if ( '-1' === $mslms_course_id ) {
				$quizzes = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_title
						FROM $wpdb->posts
						WHERE post_type = %s
						ORDER BY post_title ASC",
						'stm-quizzes'
					)
				);
			}

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
