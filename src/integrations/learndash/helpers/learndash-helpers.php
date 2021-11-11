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

	public $load_any_options = true;

	/**
	 * Learndash_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action( 'wp_ajax_select_lesson_from_course_LESSONDONE', array( $this, 'select_lesson_from_course_func' ) );
		add_action( 'wp_ajax_select_lesson_from_course_MARKLESSONDONE', array(
			$this,
			'select_lesson_from_course_no_any',
		) );

		add_action( 'wp_ajax_select_lesson_from_course_LD_TOPICDONE', array( $this, 'lesson_from_course_func' ), 15 );
		add_action( 'wp_ajax_select_lesson_from_course_MARKTOPICDONE', array(
			$this,
			'lesson_from_course_func_no_any',
		), 15 );

		add_action( 'wp_ajax_select_topic_from_lesson_MARKTOPICDONE', array(
			$this,
			'topic_from_lesson_func_no_any',
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
	 * @param bool   $any_option
	 *
	 * @return mixed
	 */
	public function all_ld_courses( $label = null, $option_code = 'LDCOURSE', $any_option = true ) {
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}


		if ( ! $label ) {
			$label = esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$option = [
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => [
				$option_code          => esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Course URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
			],
			'custom_value_description' => _x( 'Course ID', 'LearnDash', 'uncanny-automator' ),
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


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}



		if ( ! $label ) {
			$label = esc_attr__( 'Lesson', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-lessons',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_lesson, esc_attr__( 'Any lesson', 'uncanny-automator' ) );

		$option = [
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => [
				$option_code          => esc_attr__( 'Lesson title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Lesson URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Lesson featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Lesson featured image URL', 'uncanny-automator' ),
			],
			'custom_value_description' => _x( 'Lesson ID', 'LearnDash', 'uncanny-automator' ),
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


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}



		if ( ! $label ) {
			$label = esc_attr__( 'Topic', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-topic',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any topic', 'uncanny-automator' ) );

		$option = [
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => [
				$option_code          => esc_attr__( 'Topic title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Topic ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Topic URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Topic featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Topic featured image URL', 'uncanny-automator' ),
			],
			'custom_value_description' => _x( 'Topic ID', 'LearnDash', 'uncanny-automator' ),
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


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}


		if ( ! $label ) {
			$label = esc_attr__( 'Group', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'groups',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		if ( $all_label ) {
			$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any group', 'uncanny-automator' ), $all_label );
		} else {
			$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any group', 'uncanny-automator' ) );
		}

		$option = [
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => [
				$option_code          => esc_attr__( 'Group title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Group ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Group URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Group featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Group featured image URL', 'uncanny-automator' ),
			],
			'custom_value_description' => _x( 'Group ID', 'LearnDash', 'uncanny-automator' ),
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


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}



		if ( ! $label ) {
			$label = esc_attr__( 'Quiz', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'sfwd-quiz',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any quiz', 'uncanny-automator' ) );

		$option = [
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => [
				$option_code          => esc_attr__( 'Quiz title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Quiz URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Quiz featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Quiz featured image URL', 'uncanny-automator' ),
			],
			'custom_value_description' => _x( 'Quiz ID', 'LearnDash', 'uncanny-automator' ),
		];

		return apply_filters( 'uap_option_all_ld_quiz', $option );
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



		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();
		if ( ! isset( $_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$ld_post_value = sanitize_text_field( $_POST['value'] );

		if ( 'automator_custom_value' === (string) $ld_post_value && '-1' !== absint( $ld_post_value ) ) {
			$ld_course_id = isset( $_POST['values']['LDCOURSE_custom'] ) ? absint( $_POST['values']['LDCOURSE_custom'] ) : 0;
		} else {
			$ld_course_id = absint( $_POST['values']['LDCOURSE'] );
		}

		if ( absint( '-1' ) === absint( $ld_course_id ) || true === (bool) $this->load_any_options ) {
			$fields[] = array(
				'value' => '-1',
				'text'  => 'Any lesson',
			);
		}

		if ( absint( '-1' ) !== absint( $ld_course_id ) ) {
			$lessons = learndash_get_lesson_list( $ld_course_id, array( 'num' => 0 ) );

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
	 */
	public function lesson_from_course_func_no_any() {
		$this->load_any_options = false;
		$this->lesson_from_course_func();
		$this->load_any_options = true;
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function lesson_from_course_func() {



		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		if ( ! isset( $_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$ld_post_value = sanitize_text_field( $_POST['value'] );


		$ld_course_id = sanitize_text_field( $_POST['value'] );

		if ( 'automator_custom_value' === $ld_post_value && '-1' !== absint( $ld_post_value ) ) {
			if ( 'automator_custom_value' === (string) $ld_course_id ) {
				$ld_course_id = isset( $_POST['values']['LDCOURSE_custom'] ) ? absint( $_POST['values']['LDCOURSE_custom'] ) : 0;
			} else {
				$ld_course_id = absint( $ld_course_id );
			}
		}

		if ( absint( '-1' ) === absint( $ld_course_id ) || true === $this->load_any_options ) {
			$fields[] = array(
				'value' => '-1',
				'text'  => 'Any lesson',
			);
		}
		//$options     = Automator()->helpers->recipe->options->wp_query( $args, $include_any, esc_attr__( 'Any lesson', 'uncanny-automator' ) );

		$lessons = learndash_get_lesson_list( $ld_course_id, array( 'num' => 0 ) );

		foreach ( $lessons as $lesson ) {
			$fields[] = array(
				'value' => $lesson->ID,
				'text'  => $lesson->post_title,
			);
		}


		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function topic_from_lesson_func_no_any() {
		$this->load_any_options = false;
		$this->topic_from_lesson_func();
		$this->load_any_options = true;
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 *
	 */
	public function topic_from_lesson_func() {



		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields      = array();
		$include_any = $this->load_any_options;
		if ( $include_any ) {
			$fields[] = [
				'value' => - 1,
				'text'  => esc_attr__( 'Any topic', 'uncanny-automator' ),
			];
		}

		if ( ! isset( $_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$trigger_id = absint( $_POST['item_id'] );
		if ( ! $trigger_id ) {
			echo wp_json_encode( $fields );
			die();
		}

		if ( ! isset( $_POST['values'] ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$post_value = sanitize_text_field( $_POST['value'] );

		if ( 'automator_custom_value' === $post_value ) {
			$course_id = isset( $_POST['values']['LDCOURSE_custom'] ) ? absint( $_POST['values']['LDCOURSE_custom'] ) : 0;
		} else {
			$course_id = absint( $_POST['values']['LDCOURSE'] );
		}

		if ( '-1' === sanitize_text_field( $_POST['value'] ) ) {
			$lesson = null;
			echo wp_json_encode( $fields );
			die();
		} else {
			if ( 'automator_custom_value' === $post_value ) {
				$lesson = isset( $_POST['values']['LDLESSON_custom'] ) ? absint( $_POST['values']['LDLESSON_custom'] ) : 0;
			} else {
				$lesson = absint( $_POST['value'] );
			}
		}

		$topics = learndash_get_topic_list( $lesson, absint( $course_id ) );

		foreach ( $topics as $topic ) {
			$fields[] = array(
				'value' => $topic->ID,
				'text'  => $topic->post_title,
			);
		}

		echo wp_json_encode( $fields );
		die();
	}
}
