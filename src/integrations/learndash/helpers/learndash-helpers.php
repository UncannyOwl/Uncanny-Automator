<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Learndash_Pro_Helpers;

/**
 * Class Learndash_Helpers
 * @package Uncanny_Automator
 */
class Learndash_Helpers {
	/**
	 * @var Learndash_Helpers
	 */
	public $options;

	/**
	 * @var Learndash_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Learndash_Helpers constructor.
	 */
	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action( 'wp_ajax_select_lesson_from_course_LESSONDONE', array( $this, 'select_lesson_from_course_func' ) );
		add_action( 'wp_ajax_select_lesson_from_course_MARKLESSONDONE', array(
			$this,
			'select_lesson_from_course_no_any',
		) );

		add_action( 'wp_ajax_select_lesson_from_course_LD_TOPICDONE', array( $this, 'lesson_from_course_func' ), 15 );
		add_action( 'wp_ajax_select_lesson_from_course_MARKTOPICDONE', array(
			$this,
			'lesson_from_course_func_no_any'
		), 15 );

		add_action( 'wp_ajax_select_topic_from_lesson_MARKTOPICDONE', array(
			$this,
			'topic_from_lesson_func_no_any'
		), 15 );
		add_action( 'wp_ajax_select_topic_from_lesson_LD_TOPICDONE', array( $this, 'topic_from_lesson_func' ), 15 );
	}

	/**
	 * @param Learndash_Helpers $options
	 */
	public function setOptions( Learndash_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Learndash_Pro_Helpers $pro
	 */
	public function setPro( Learndash_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_ld_courses( $label = null, $option_code = 'LDCOURSE', $any_option = true ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		global $uncanny_automator;
		if ( ! $label ) {
			$label =  esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option,  esc_attr__( 'Any course', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Course URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ld_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ld_lessons( $label = null, $any_lesson = true, $option_code = 'LDLESSON' ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		global $uncanny_automator;

		if ( ! $label ) {
			$label =  esc_attr__( 'Lesson', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-lessons',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_lesson,  esc_attr__( 'Any lesson', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Lesson title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Lesson URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ld_lessons', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ld_topics( $label = null, $option_code = 'LDTOPIC' ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		global $uncanny_automator;

		if ( ! $label ) {
			$label =  esc_attr__( 'Topic', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-topic',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, true,  esc_attr__( 'Any topic', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Topic title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Topic ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Topic URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ld_topics', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ld_groups( $label = null, $option_code = 'LDGROUP', $all_label = false, $any_option = true ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		global $uncanny_automator;
		if ( ! $label ) {
			$label =  esc_attr__( 'Group', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'groups',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		if ( $all_label ) {
			$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option,  esc_attr__( 'Any group', 'uncanny-automator' ), $all_label );
		} else {
			$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option,  esc_attr__( 'Any group', 'uncanny-automator' ) );
		}

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Group title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Group ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Group URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ld_groups', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ld_quiz( $label = null, $option_code = 'LDQUIZ', $any_option = true ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		global $uncanny_automator;

		if ( ! $label ) {
			$label =  esc_attr__( 'Quiz', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-quiz',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option,  esc_attr__( 'Any quiz', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Quiz title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Quiz URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ld_quiz', $option );
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function select_lesson_from_course_no_any() {
		$this->select_lesson_from_course_func( false );
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function select_lesson_from_course_func( $include_any = '' ) {

		global $uncanny_automator;

		// Nonce and post object validation
		$uncanny_automator->utilities->ajax_auth_check( $_POST );

		$fields = [];
		if ( isset( $_POST ) ) {

			$args = [
				'post_type'      => 'sfwd-lessons',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'course_id',
						'value'   => absint( $_POST['value'] ),
						'compare' => '=',
					),
					array(
						'key'     => 'ld_course_' . absint( $_POST['value'] ),
						'value'   => absint( $_POST['value'] ),
						'compare' => '=',
					),
				),
				'posts_per_page' => 999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			];

			$include_any = $include_any !== false ? true : false;
			$options     = $uncanny_automator->helpers->recipe->options->wp_query( $args, $include_any,  esc_attr__( 'Any lesson', 'uncanny-automator' ) );

			foreach ( $options as $lesson_id => $lesson_name ) {
				$fields[] = array(
					'value' => $lesson_id,
					'text'  => $lesson_name,
				);
			}
		}

		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function lesson_from_course_func_no_any() {
		$this->lesson_from_course_func( false );
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function lesson_from_course_func( $include_any = '' ) {

		global $uncanny_automator;

		// Nonce and post object validation
		$uncanny_automator->utilities->ajax_auth_check( $_POST );

		$fields = [];

		if ( isset( $_POST ) ) {

			if ( '-1' === absint( $_POST['value'] ) ) {
				$args = [
					'post_type'      => 'sfwd-lessons',
					'posts_per_page' => 999,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'post_status'    => 'publish',
				];
			} else {
				$args = [
					'post_type'      => 'sfwd-lessons',
					'meta_query'     => array(
						'relation' => 'OR',
						array(
							'key'     => 'course_id',
							'value'   => absint( $_POST['value'] ),
							'compare' => '=',
						),
						array(
							'key'     => 'ld_course_' . absint( $_POST['value'] ),
							'value'   => absint( $_POST['value'] ),
							'compare' => '=',
						),
					),
					'posts_per_page' => 999,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'post_status'    => 'publish',
				];
			}
			$include_any = $include_any !== false ? true : false;
			$options     = $uncanny_automator->helpers->recipe->options->wp_query( $args, $include_any,  esc_attr__( 'Any lesson', 'uncanny-automator' ) );

			foreach ( $options as $lesson_id => $lesson_name ) {
				$fields[] = array(
					'value' => $lesson_id,
					'text'  => $lesson_name,
				);
			}
		}

		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function topic_from_lesson_func_no_any() {
		$this->topic_from_lesson_func( false );
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function topic_from_lesson_func( $include_any = '' ) {

		global $uncanny_automator;

		// Nonce and post object validation
		$uncanny_automator->utilities->ajax_auth_check( $_POST );

		$fields      = array();
		$include_any = $include_any !== false ? true : false;
		if ( $include_any ) {
			$fields[] = [
				'value' => - 1,
				'text'  =>  esc_attr__( 'Any topic', 'uncanny-automator' ),
			];
		}

		if ( isset( $_POST ) ) {

			$trigger_id = absint( $_POST['item_id'] );

			if ( $trigger_id ) {

				if ( isset( $_POST['values'] ) && isset( $_POST['values']['LDCOURSE'] ) ) {
					$course_id = absint( $_POST['values']['LDCOURSE'] );
				} else {
					$course_id = 0;
				}

				if ( '-1' === sanitize_text_field( $_POST['value'] ) ) {
					$lesson = null;
					echo wp_json_encode( $fields );
					die();
				} else {
					$lesson = absint( $_POST['value'] );
				}

				$topics = learndash_get_topic_list( $lesson, absint( $course_id ) );

				foreach ( $topics as $topic ) {
					$fields[] = array(
						'value' => $topic->ID,
						'text'  => $topic->post_title,
					);
				}
			}
		}

		echo wp_json_encode( $fields );
		die();
	}
}