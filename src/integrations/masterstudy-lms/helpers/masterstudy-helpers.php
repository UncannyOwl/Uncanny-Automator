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
	public function __construct( $load_hooks = true ) {

		if ( ! $load_hooks ) {
			return;
		}

		$this->load_options = true;

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

		// Get all lessons if course id is -1
		if ( '-1' === $mslms_course_id ) {
			global $wpdb;

			$lessons = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title FROM $wpdb->posts WHERE post_type = %s ORDER BY post_title ASC",
					'stm-lessons'
				)
			);
			if ( ! empty( $lessons ) ) {
				$fields = array_merge( $fields, $this->format_posts_to_options( $lessons ) );
			}
		} else {

			// Get lessons by course id
			$lessons = $this->get_lesson_options_by_course_id( absint( $mslms_course_id ) );
			if ( ! empty( $lessons ) ) {
				// Append lessons to fields.
				$fields = array_merge( $fields, $lessons );
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

		// Get all quizzes if course id is -1
		if ( '-1' === $mslms_course_id ) {

			global $wpdb;
			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title
					FROM $wpdb->posts
					WHERE post_type = %s
					ORDER BY post_title ASC",
					'stm-quizzes'
				)
			);

			if ( ! empty( $quizzes ) ) {
				$fields = array_merge( $fields, $this->format_posts_to_options( $quizzes ) );
			}
		} else {

			// Get quizzes by course id
			$quizzes = $this->get_quizz_options_by_course_id( absint( $mslms_course_id ) );
			if ( ! empty( $quizzes ) ) {
				// Append quizzes to fields.
				$fields = array_merge( $fields, $quizzes );
			}
		}

		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Get lesson options by course id.
	 *
	 * @param int $course_id
	 *
	 * @return array - value and text array.
	 */
	public function get_lesson_options_by_course_id( $course_id ) {

		// Use curriculum repository class.
		$curriculum_repo = $this->get_curriculum_repository();
		if ( $curriculum_repo ) {
			return $this->get_curriculum_material_options_by_post_type( $curriculum_repo, absint( $course_id ), 'stm-lessons' );
		}

		// Legacy get lessons from meta_key curriculum.
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
				absint( $course_id )
			)
		);

		return $this->format_posts_to_options( $lessons );
	}

	/**
	 * Get quiz options by course id.
	 *
	 * @param int $course_id
	 *
	 * @return array - value and text array.
	 */
	public function get_quizz_options_by_course_id( $course_id ) {

		$course_id = absint( $course_id );

		// Use curriculum repository class.
		$curriculum_repo = $this->get_curriculum_repository();
		if ( $curriculum_repo ) {
			return $this->get_curriculum_material_options_by_post_type( $curriculum_repo, absint( $course_id ), 'stm-quizzes' );
		}

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
				absint( $course_id )
			)
		);

		return $this->format_posts_to_options( $quizzes );
	}

	/**
	 * Get curriculum options by post type.
	 *
	 * @param \MasterStudy\Lms\Repositories\CurriculumRepository $curriculum_repo
	 * @param int    $course_id
	 * @param string $post_type
	 *
	 * @return array - value and text array.
	 */
	private function get_curriculum_material_options_by_post_type( $curriculum_repo, $course_id, $post_type ) {
		$materials  = array();
		$curriculum = $curriculum_repo->get_curriculum( absint( $course_id ) );
		if ( ! empty( $curriculum ) && is_array( $curriculum ) && isset( $curriculum['materials'] ) ) {
			if ( ! empty( $curriculum['materials'] ) && is_array( $curriculum['materials'] ) ) {
				foreach ( $curriculum['materials'] as $material ) {
					if ( $material['post_type'] === $post_type ) {
						$materials[] = array(
							'value' => $material['post_id'],
							'text'  => $material['title'],
						);
					}
				}
			}
		}
		return $materials;
	}

	/**
	 * Get course curriculum materials.
	 *
	 * @param int $course_id
	 *
	 * @return array
	 */
	public function get_course_curriculum_materials( $course_id ) {
		$materials = array();

		// Use curriculum repository class.
		$curriculum_repo = $this->get_curriculum_repository();
		if ( $curriculum_repo ) {
			$curriculum = $curriculum_repo->get_curriculum( absint( $course_id ) );
			if ( ! empty( $curriculum ) && is_array( $curriculum ) && isset( $curriculum['materials'] ) ) {
				if ( ! empty( $curriculum['materials'] ) && is_array( $curriculum['materials'] ) ) {
					foreach ( $curriculum['materials'] as $material ) {
						$materials[] = array(
							'title'     => $material['title'],
							'post_id'   => $material['post_id'],
							'post_type' => $material['post_type'],
						);
					}
				}
			}
			return $materials;
		}

		// No materials found, try to get them from meta_key curriculum.
		$curriculum = get_post_meta( absint( $course_id ), 'curriculum', true );
		if ( ! empty( $curriculum ) ) {
			$curriculum       = \STM_LMS_Helpers::only_array_numbers( explode( ',', $curriculum ) );
			$curriculum_posts = get_posts(
				array(
					'post__in'       => $curriculum,
					'posts_per_page' => 999,
					'post_type'      => array( 'stm-lessons', 'stm-quizzes' ),
					'post_status'    => 'publish',
				)
			);
			if ( ! empty( $curriculum_posts ) ) {
				foreach ( $curriculum_posts as $material ) {
					$materials[] = array(
						'title'     => $material->post_title,
						'post_id'   => $material->ID,
						'post_type' => $material->post_type,
					);
				}
			}
		}

		return $materials;
	}

	/**
	 * Check if curriculum repository class exists.
	 *
	 * @return mixed bool|\MasterStudy\Lms\Repositories\CurriculumRepository
	 */
	private function get_curriculum_repository() {
		static $curriculum_repository = null;
		if ( is_null( $curriculum_repository ) ) {
			if ( class_exists( '\MasterStudy\Lms\Repositories\CurriculumRepository' ) ) {
				$curriculum_repository = new \MasterStudy\Lms\Repositories\CurriculumRepository();
			} else {
				$curriculum_repository = false;
			}
		}
		return $curriculum_repository;
	}

	/**
	 * Format posts object to options array.
	 *
	 * @param array $posts
	 *
	 * @return array
	 */
	private function format_posts_to_options( $posts ) {
		$options = array();
		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$options[] = array(
					'value' => $post->ID,
					'text'  => $post->post_title,
				);
			}
		}
		return $options;
	}

}
