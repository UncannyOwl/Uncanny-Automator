<?php

namespace Uncanny_Automator;

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
	 * @var \Uncanny_Automator_Pro\Learndash_Pro_Helpers
	 */
	public $pro;

	/**
	 * Learndash_Helpers constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_select_lesson_from_course_LESSONDONE', array( $this, 'select_lesson_from_course_func' ) );
		add_action( 'wp_ajax_select_lesson_from_course_MARKLESSONDONE', array(
			$this,
			'select_lesson_from_course_no_any',
		) );

		add_action( 'wp_ajax_select_lesson_from_course_LD_TOPICDONE', array( $this, 'lesson_from_course_func' ), 15 );
		add_action( 'wp_ajax_select_lesson_from_course_MARKTOPICDONE', array( $this, 'lesson_from_course_func_no_any' ), 15 );

		add_action( 'wp_ajax_select_topic_from_lesson_MARKTOPICDONE', array( $this, 'topic_from_lesson_func_no_any' ), 15 );
		add_action( 'wp_ajax_select_topic_from_lesson_LD_TOPICDONE', array( $this, 'topic_from_lesson_func' ), 15 );
	}

	/**
	 * @param Learndash_Helpers $options
	 */
	public function setOptions( Learndash_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Learndash_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Learndash_Pro_Helpers $pro ) {
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
		global $uncanny_automator;
		if ( ! $label ) {
			$label = __( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any course', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Course URL', 'uncanny-automator' ),
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
		global $uncanny_automator;

		if ( ! $label ) {
			$label = __( 'Lesson', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-lessons',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_lesson, __( 'Any lesson', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Lesson title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Lesson URL', 'uncanny-automator' ),
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
		global $uncanny_automator;

		if ( ! $label ) {
			$label = __( 'Topic', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-topic',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, true, __( 'Any topic', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Topic title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Topic ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Topic URL', 'uncanny-automator' ),
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
		global $uncanny_automator;
		if ( ! $label ) {
			$label = __( 'Group', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'groups',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		if ( $all_label ) {
			$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any group', 'uncanny-automator' ), $all_label );
		} else {
			$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any group', 'uncanny-automator' ) );
		}

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Group title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Group ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Group URL', 'uncanny-automator' ),
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
		global $uncanny_automator;

		if ( ! $label ) {
			$label = __( 'Quiz', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-quiz',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any quiz', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Quiz title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Quiz URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ld_quiz', $option );
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
			$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $include_any, __( 'Any lesson', 'uncanny-automator' ) );

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
	public function select_lesson_from_course_no_any() {
		$this->select_lesson_from_course_func( FALSE );
	}
	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function lesson_from_course_func( $include_any = '') {

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
			$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $include_any, __( 'Any lesson', 'uncanny-automator' ) );

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
	public function topic_from_lesson_func( $include_any = '' ) {

		global $uncanny_automator;

		// Nonce and post object validation
		$uncanny_automator->utilities->ajax_auth_check( $_POST );

		$fields   = array();
		$include_any = $include_any !== false ? true : false;
		if( $include_any ) {
			$fields[] = [
				'value' => - 1,
				'text'  => __( 'Any topic', 'uncanny-automator' ),
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

				if ( '-1' === sanitize_text_field( $_POST['value']) ) {
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

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function topic_from_lesson_func_no_any() {
		$this->topic_from_lesson_func( false );
	}
}