<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Learndash_Pro_Helpers;

/**
 * Class Learndash_Helpers
 *
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
	 * @var bool
	 */
	public $load_any_options = true;

	/**
	 * Learndash_Helpers constructor.
	 */
	public function __construct( $load_action_hook = true ) {

		$this->load_options = true;
		if ( true === $load_action_hook ) {

			add_action(
				'wp_ajax_select_lesson_from_course_LESSONDONE',
				array(
					$this,
					'select_lesson_from_course_func',
				)
			);
			add_action(
				'wp_ajax_select_lesson_from_course_MARKLESSONDONE',
				array(
					$this,
					'select_lesson_from_course_no_any',
				)
			);

			add_action(
				'wp_ajax_select_lesson_from_course_LD_TOPICDONE',
				array(
					$this,
					'lesson_from_course_func',
				),
				15
			);
			add_action(
				'wp_ajax_select_lesson_from_course_MARKTOPICDONE',
				array(
					$this,
					'lesson_from_course_func_no_any',
				),
				15
			);

			add_action(
				'wp_ajax_select_topic_from_lesson_MARKTOPICDONE',
				array(
					$this,
					'topic_from_lesson_func_no_any',
				),
				15
			);
			add_action( 'wp_ajax_select_topic_from_lesson_LD_TOPICDONE', array( $this, 'topic_from_lesson_func' ), 15 );

			add_action(
				'learndash_update_user_activity',
				array(
					$this,
					'learndash_update_user_activity_func',
				),
				20,
				1
			);
		}
	}

	/**
	 * @param Learndash_Helpers $options
	 */
	public function setOptions( Learndash_Helpers $options ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * @param Learndash_Pro_Helpers $pro
	 */
	public function setPro( Learndash_Pro_Helpers $pro ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 * @param bool $include_relevant_tokens
	 *
	 * @return mixed
	 */
	public function all_ld_courses( $label = null, $option_code = 'LDCOURSE', $any_option = true, $include_relevant_tokens = true ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 9999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		'orderby'            => 'title',
		'order'              => 'ASC',
		'post_status'        => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$relevant_tokens = array();
		if ( $include_relevant_tokens ) {
			$relevant_tokens = wp_list_pluck( $this->get_course_relevant_tokens( 'trigger', $option_code ), 'name' );

			if ( self::is_course_timer_activated() ) {
				$relevant_tokens[ $option_code . '_COURSE_CUMULATIVE_TIME' ]    = __( 'Course cumulative time', 'uncanny-automator' );
				$relevant_tokens[ $option_code . '_COURSE_TIME_AT_COMPLETION' ] = __( 'Course time at completion', 'uncanny-automator' );
			}
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => $relevant_tokens,
			'custom_value_description' => _x( 'Course ID', 'LearnDash', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_all_ld_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function get_all_ld_courses( $label = null, $option_code = 'LDCOURSE', $any_option = true ) {
		$this->load_options = true;

		return $this->all_ld_courses( $label, $option_code, $any_option );
	}

	/**
	 * Get Relevant Tokens for Course
	 *
	 * @param string $type - 'trigger' or 'action'
	 * @param string $option_code - option code
	 *
	 * @return array
	 */
	public function get_course_relevant_tokens( $type = 'trigger', $option_code = 'LDCOURSE' ) {

		$tokens = array(
			$option_code                    => array(
				'name' => esc_attr_x( 'Course title', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_ID'            => array(
				'name' => esc_attr_x( 'Course ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_STATUS'        => array(
				'name' => esc_attr_x( 'Course status', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_ACCESS_EXPIRY' => array(
				'name' => esc_attr_x( 'Course access expiry date', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_URL'           => array(
				'name' => esc_attr_x( 'Course URL', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_THUMB_ID'      => array(
				'name' => esc_attr_x( 'Course featured image ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_THUMB_URL'     => array(
				'name' => esc_attr_x( 'Course featured image URL', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
		);

		return apply_filters( "automator_set_learndash_course_{$type}_tokens", $tokens );

	}

	/**
	 * Hydrate the relevant course tokens for the action.
	 *
	 * @param int $course_id - the course ID
	 * @param int $user_id - the option code for the action
	 * @param string $action_meta - the meta key
	 *
	 * @return array
	 */
	public function hydrate_ld_course_action_tokens( $course_id, $user_id, $action_meta ) {

		$relevant_tokens = $this->get_course_relevant_tokens( 'action', $action_meta );
		$tokens          = array();
		foreach ( $relevant_tokens as $token => $config ) {
			switch ( $token ) {
				case $action_meta:
					$tokens[ $token ] = get_the_title( $course_id );
					break;
				case $action_meta . '_ID':
					$tokens[ $token ] = $course_id;
					break;
				case $action_meta . '_STATUS':
					$tokens[ $token ] = learndash_course_status( $course_id, $user_id );
					break;
				case $action_meta . '_ACCESS_EXPIRY':
					$tokens[ $token ] = learndash_adjust_date_time_display( ld_course_access_expires_on( $course_id, $user_id ) );
					break;
				case $action_meta . '_URL':
					$tokens[ $token ] = get_permalink( $course_id );
					break;
				case $action_meta . '_THUMB_ID':
					$tokens[ $token ] = get_post_thumbnail_id( $course_id );
					break;
				case $action_meta . '_THUMB_URL':
					$tokens[ $token ] = get_the_post_thumbnail_url( $course_id );
					break;
				default:
					$tokens[ $token ] = apply_filters( "automator_hydrate_learndash_action_token_{$token}", '', $course_id, $user_id );
					break;
			}
		}

		return $tokens;
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

		$args = array(
			'post_type'      => 'sfwd-lessons',
			'posts_per_page' => 9999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		'orderby'            => 'title',
		'order'              => 'ASC',
		'post_status'        => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_lesson, esc_attr__( 'Any lesson', 'uncanny-automator' ) );
		$option  = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => wp_list_pluck( $this->get_lesson_relevant_tokens( 'trigger', $option_code ), 'name' ),
			'custom_value_description' => _x( 'Lesson ID', 'LearnDash', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_all_ld_lessons', $option );
	}

	/**
	 * Get Relevant Tokens for Lessons
	 *
	 * @param string $type - 'trigger' or 'action'
	 * @param string $option_code - option code
	 *
	 * @return array
	 */
	public function get_lesson_relevant_tokens( $type = 'trigger', $option_code = 'LDLESSON' ) {

		$tokens = array(
			$option_code                => array(
				'name' => esc_attr_x( 'Lesson title', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_ID'        => array(
				'name' => esc_attr_x( 'Lesson ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_URL'       => array(
				'name' => esc_attr_x( 'Lesson URL', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_THUMB_ID'  => array(
				'name' => esc_attr_x( 'Lesson featured image ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_THUMB_URL' => array(
				'name' => esc_attr_x( 'Lesson featured image URL', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
		);

		return apply_filters( "automator_set_learndash_lesson_{$type}_tokens", $tokens );
	}

	/**
	 * Hydrate the relevant lesson tokens for the action.
	 *
	 * @param int $lesson_id - the lesson ID
	 * @param int $user_id - the option code for the action
	 * @param string $action_meta - the meta key
	 *
	 * @return array
	 */
	public function hydrate_ld_lesson_action_tokens( $lesson_id, $user_id, $action_meta ) {

		$relevant_tokens = $this->get_lesson_relevant_tokens( 'action', $action_meta );
		$tokens          = array();
		foreach ( $relevant_tokens as $token => $config ) {
			switch ( $token ) {
				case $action_meta:
					$tokens[ $token ] = get_the_title( $lesson_id );
					break;
				case $action_meta . '_ID':
					$tokens[ $token ] = $lesson_id;
					break;
				case $action_meta . '_URL':
					$tokens[ $token ] = get_permalink( $lesson_id );
					break;
				case $action_meta . '_THUMB_ID':
					$tokens[ $token ] = get_post_thumbnail_id( $lesson_id );
					break;
				case $action_meta . '_THUMB_URL':
					$tokens[ $token ] = get_the_post_thumbnail_url( $lesson_id );
					break;
				default:
					$tokens[ $token ] = apply_filters( "automator_hydrate_learndash_action_token_{$token}", '', $lesson_id, $user_id );
					break;
			}
		}

		return $tokens;
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

		$args = array(
			'post_type'      => 'sfwd-topic',
			'posts_per_page' => 9999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		'orderby'            => 'title',
		'order'              => 'ASC',
		'post_status'        => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any topic', 'uncanny-automator' ) );

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => wp_list_pluck( $this->get_topic_relevant_tokens( 'trigger', $option_code ), 'name' ),
			'custom_value_description' => _x( 'Topic ID', 'LearnDash', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_all_ld_topics', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function get_topic_relevant_tokens( $type = 'trigger', $option_code = 'LDTOPIC' ) {

		$tokens = array(
			$option_code                => array(
				'name' => esc_attr_x( 'Topic title', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_ID'        => array(
				'name' => esc_attr_x( 'Topic ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_URL'       => array(
				'name' => esc_attr_x( 'Topic URL', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_THUMB_ID'  => array(
				'name' => esc_attr_x( 'Topic featured image ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_THUMB_URL' => array(
				'name' => esc_attr_x( 'Topic featured image URL', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
		);

		return apply_filters( "automator_set_learndash_topic_{$type}_tokens", $tokens );
	}

	/**
	 * Hydrate the relevant topic tokens for the action.
	 *
	 * @param int $topic_id - the topic ID
	 * @param int $user_id - user ID
	 * @param string $action_meta - the meta key
	 *
	 * @return array
	 */
	public function hydrate_ld_topic_action_tokens( $topic_id, $user_id, $action_meta ) {

		$relevant_tokens = $this->get_topic_relevant_tokens( 'action', $action_meta );
		$tokens          = array();
		foreach ( $relevant_tokens as $token => $config ) {
			switch ( $token ) {
				case $action_meta:
					$tokens[ $token ] = get_the_title( $topic_id );
					break;
				case $action_meta . '_ID':
					$tokens[ $token ] = $topic_id;
					break;
				case $action_meta . '_URL':
					$tokens[ $token ] = get_permalink( $topic_id );
					break;
				case $action_meta . '_THUMB_ID':
					$tokens[ $token ] = get_post_thumbnail_id( $topic_id );
					break;
				case $action_meta . '_THUMB_URL':
					$tokens[ $token ] = get_the_post_thumbnail_url( $topic_id );
					break;
				default:
					$tokens[ $token ] = apply_filters( "automator_hydrate_learndash_action_token_{$token}", '', $topic_id, $user_id );
					break;
			}
		}

		return $tokens;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ld_groups( $label = null, $option_code = 'LDGROUP', $all_label = false, $any_option = true, $multiple_values = false, $relevant_tokens = true ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Group', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'groups',
			'posts_per_page' => 9999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		'orderby'            => 'title',
		'order'              => 'ASC',
		'post_status'        => 'publish',
		);

		if ( $all_label ) {
			$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any group', 'uncanny-automator' ), $all_label );
		} else {
			$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any group', 'uncanny-automator' ) );
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'supports_multiple_values' => $multiple_values,
			'relevant_tokens'          => wp_list_pluck( $this->get_group_relevant_tokens( 'trigger', $option_code ), 'name' ),
			'custom_value_description' => _x( 'Group ID', 'LearnDash', 'uncanny-automator' ),
		);

		if ( false === $relevant_tokens ) {
			$option['relevant_tokens'] = array();
		}

		return apply_filters( 'uap_option_all_ld_groups', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function get_group_relevant_tokens( $type = 'trigger', $option_code = 'LDGROUP' ) {

		$tokens = array(
			$option_code                => array(
				'name' => esc_attr_x( 'Group title', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_ID'        => array(
				'name' => esc_attr_x( 'Group ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_URL'       => array(
				'name' => esc_attr_x( 'Group URL', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_THUMB_ID'  => array(
				'name' => esc_attr_x( 'Group featured image ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_THUMB_URL' => array(
				'name' => esc_attr_x( 'Group featured image ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
		);

		return apply_filters( "automator_set_learndash_group_{$type}_tokens", $tokens );
	}

	/**
	 * Hydrate the relevant group tokens for the action.
	 *
	 * @param int $group_id - the group ID
	 * @param int $user_id - user ID
	 * @param string $action_meta - the meta key
	 *
	 * @return array
	 */
	public function hydrate_ld_group_action_tokens( $group_id, $user_id, $action_meta ) {

		$relevant_tokens = $this->get_group_relevant_tokens( 'action', $action_meta );
		$tokens          = array();
		foreach ( $relevant_tokens as $token => $config ) {
			switch ( $token ) {
				case $action_meta:
					$tokens[ $token ] = get_the_title( $group_id );
					break;
				case $action_meta . '_ID':
					$tokens[ $token ] = $group_id;
					break;
				case $action_meta . '_URL':
					$tokens[ $token ] = get_permalink( $group_id );
					break;
				case $action_meta . '_THUMB_ID':
					$tokens[ $token ] = get_post_thumbnail_id( $group_id );
					break;
				case $action_meta . '_THUMB_URL':
					$tokens[ $token ] = get_the_post_thumbnail_url( $group_id );
					break;
				default:
					$tokens[ $token ] = apply_filters( "automator_hydrate_learndash_action_token_{$token}", '', $group_id, $user_id );
					break;
			}
		}

		return $tokens;
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

		$args = array(
			'post_type'      => 'sfwd-quiz',
			'posts_per_page' => 9999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		'orderby'            => 'title',
		'order'              => 'ASC',
		'post_status'        => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any quiz', 'uncanny-automator' ) );

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => wp_list_pluck( $this->get_quiz_relevant_tokens( 'trigger', $option_code ), 'name' ),
			'custom_value_description' => _x( 'Quiz ID', 'LearnDash', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_all_ld_quiz', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function get_quiz_relevant_tokens( $type = 'trigger', $option_code = 'LDQUIZ' ) {

		$tokens = array(
			$option_code                      => array(
				'name' => esc_attr_x( 'Quiz title', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_ID'              => array(
				'name' => esc_attr_x( 'Quiz ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_URL'             => array(
				'name' => esc_attr_x( 'Quiz URL', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_THUMB_ID'        => array(
				'name' => esc_attr_x( 'Quiz featured image ID', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_THUMB_URL'       => array(
				'name' => esc_attr_x( 'Quiz featured image URL', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_TIME'            => array(
				'name' => esc_attr_x( 'Quiz time spent', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_SCORE'           => array(
				'name' => esc_attr_x( 'Quiz score', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_CORRECT'         => array(
				'name' => esc_attr_x( 'Quiz number of correct answers', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'int',
			),
			$option_code . '_CATEGORY_SCORES' => array(
				'name' => esc_attr_x( 'Quiz category scores', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_Q_AND_A'         => array(
				'name' => esc_attr_x( 'Quiz questions and answers', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
			$option_code . '_Q_AND_A_CSV'     => array(
				'name' => esc_attr_x( 'Quiz question & answers (unformatted)', 'LearnDash Token', 'uncanny-automator' ),
				'type' => 'text',
			),
		);

		return apply_filters( "automator_set_learndash_quiz_{$type}_tokens", $tokens );
	}

	/**
	 * Hydrate the relevant quiz tokens for the action.
	 *
	 * @param int $quiz_id - the quiz ID
	 * @param int $user_id - user ID
	 * @param string $action_meta - the meta key
	 *
	 * @return array
	 */
	public function hydrate_ld_quiz_action_tokens( $quiz_id, $user_id, $action_meta ) {

		$relevant_tokens = $this->get_quiz_relevant_tokens( 'action', $action_meta );
		$tokens          = array();
		$token_class     = new Ld_Tokens( false );
		foreach ( $relevant_tokens as $token => $config ) {
			switch ( $token ) {
				case $action_meta:
					$tokens[ $token ] = get_the_title( $quiz_id );
					break;
				case $action_meta . '_ID':
					$tokens[ $token ] = $quiz_id;
					break;
				case $action_meta . '_URL':
					$tokens[ $token ] = get_permalink( $quiz_id );
					break;
				case $action_meta . '_THUMB_ID':
					$tokens[ $token ] = get_post_thumbnail_id( $quiz_id );
					break;
				case $action_meta . '_THUMB_URL':
					$tokens[ $token ] = get_the_post_thumbnail_url( $quiz_id );
					break;
				case $action_meta . '_TIME':
					$tokens[ $token ] = $token_class->get_quiz_token_data( 'LDQUIZ_TIME', $user_id, $quiz_id );
					break;
				case $action_meta . '_SCORE':
					$tokens[ $token ] = $token_class->get_quiz_token_data( 'LDQUIZ_SCORE', $user_id, $quiz_id );
					break;
				case $action_meta . '_CORRECT':
					$tokens[ $token ] = $token_class->get_quiz_token_data( 'LDQUIZ_CORRECT', $user_id, $quiz_id );
					break;
				case $action_meta . '_CATEGORY_SCORES':
					$tokens[ $token ] = $token_class->get_quiz_token_data( 'LDQUIZ_CATEGORY_SCORES', $user_id, $quiz_id );
					break;
				case $action_meta . '_Q_AND_A':
					$tokens[ $token ] = $token_class->get_quiz_token_data( 'LDQUIZ_Q_AND_A', $user_id, $quiz_id );
					break;
				case $action_meta . '_Q_AND_A_CSV':
					$tokens[ $token ] = $token_class->get_quiz_token_data( 'LDQUIZ_Q_AND_A_CSV', $user_id, $quiz_id );
					break;
				default:
					$tokens[ $token ] = apply_filters( "automator_hydrate_learndash_action_token_{$token}", '', $quiz_id, $user_id );
					break;
			}
		}

		return $tokens;
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
		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$ld_post_value  = automator_filter_input( 'value', INPUT_POST );
		$ld_post_values = automator_filter_input_array( 'values', INPUT_POST );

		if ( 'automator_custom_value' === (string) $ld_post_value && '-1' !== absint( $ld_post_value ) ) {
			$ld_course_id = isset( $ld_post_values['LDCOURSE_custom'] ) ? absint( $ld_post_values['LDCOURSE_custom'] ) : 0;
		} else {
			$ld_course_id = absint( $ld_post_values['LDCOURSE'] );
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

		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$ld_post_value  = sanitize_text_field( automator_filter_input( 'value', INPUT_POST ) );
		$ld_post_values = automator_filter_input_array( 'values', INPUT_POST );
		$ld_course_id   = sanitize_text_field( automator_filter_input( 'value', INPUT_POST ) );

		if ( 'automator_custom_value' === $ld_post_value && intval( '-1' ) !== intval( $ld_post_value ) ) {
			if ( 'automator_custom_value' === (string) $ld_course_id ) {
				$ld_course_id = isset( $ld_post_values['LDCOURSE_custom'] ) ? absint( $ld_post_values['LDCOURSE_custom'] ) : 0;
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
	 */
	public function topic_from_lesson_func() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields      = array();
		$include_any = $this->load_any_options;
		if ( $include_any ) {
			$fields[] = array(
				'value' => - 1,
				'text'  => esc_attr__( 'Any topic', 'uncanny-automator' ),
			);
		}

		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$trigger_id = absint( automator_filter_input( 'item_id', INPUT_POST ) );
		if ( ! $trigger_id ) {
			echo wp_json_encode( $fields );
			die();
		}

		if ( ! automator_filter_has_var( 'values', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$post_value  = sanitize_text_field( automator_filter_input( 'value', INPUT_POST ) );
		$post_values = automator_filter_input_array( 'values', INPUT_POST );

		if ( 'automator_custom_value' === $post_value ) {
			$course_id = isset( $post_values['LDCOURSE_custom'] ) ? absint( $post_values['LDCOURSE_custom'] ) : 0;
		} else {
			$course_id = absint( $post_values['LDCOURSE'] );
		}

		if ( '-1' === sanitize_text_field( automator_filter_input( 'value', INPUT_POST ) ) ) {
			$lesson = null;
			echo wp_json_encode( $fields );
			die();
		} else {
			if ( 'automator_custom_value' === $post_value ) {
				$lesson = isset( $post_values['LDLESSON_custom'] ) ? absint( $post_values['LDLESSON_custom'] ) : 0;
			} else {
				$lesson = absint( automator_filter_input( 'value', INPUT_POST ) );
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

	/**
	 * Fallback code to fire course, lesson and topic complete actions if admin completes on edit-profile
	 *
	 * @param $args
	 *
	 * @return void
	 */
	public function learndash_update_user_activity_func( $args ) {
		// Bail early if args is empty
		if ( empty( $args ) ) {
			return;
		}
		// If it's not an admin (or ajax for quiz complete), bail
		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return;
		}
		// activity status is empty or not completed, bail
		if ( ! isset( $args['activity_status'] ) || 1 !== absint( $args['activity_status'] ) ) {
			return;
		}
		// 'update' action is called when an activity is updated
		$activity_action = $args['activity_action'];
		if ( 'update' !== $activity_action && 'insert' !== $activity_action ) {
			return;
		}
		// if activity_completed timestamp is empty, bail
		if ( empty( $args['activity_completed'] ) ) {
			return;
		}
		$user_id         = absint( $args['user_id'] );
		$user            = get_user_by( 'ID', $user_id );
		$post_id         = absint( $args['post_id'] ); //Course, lesson or topic ID
		$course_id       = absint( $args['course_id'] ); // Linked Course ID
		$activity_type   = $args['activity_type']; //course, lesson or topic
		$course_progress = get_user_meta( $user_id, '_sfwd-course_progress', true ); // course progress
		// Activity type is lesson, fire do_action
		if ( 'lesson' === $activity_type ) {
			do_action(
				'automator_learndash_lesson_completed',
				array(
					'user'     => $user,
					'course'   => get_post( $course_id ),
					'lesson'   => get_post( $post_id ),
					'progress' => $course_progress,
				)
			);

			return;
		}

		// Activity type is topic, fire do_action
		if ( 'topic' === $activity_type ) {
			$lesson_id = learndash_get_lesson_id( $post_id, $course_id );
			do_action(
				'automator_learndash_lesson_completed',
				array(
					'user'     => $user,
					'course'   => get_post( $course_id ),
					'lesson'   => get_post( $lesson_id ),
					'topic'    => get_post( $post_id ),
					'progress' => $course_progress,
				)
			);

			return;
		}
	}

	/**
	 * Check if course timer is activated
	 */
	public static function is_course_timer_activated() {

		static $is_activated = null;

		if ( is_null( $is_activated ) ) {
			if ( ! defined( 'UNCANNY_TOOLKIT_PRO_VERSION' ) ) {
				$is_activated = false;
			} else {
				$active_modules = get_option( 'uncanny_toolkit_active_classes', true );
				$is_activated   = ! empty( $active_modules['uncanny_pro_toolkit\CourseTimer'] );
			}
		}

		return $is_activated;
	}

	/**
	 * Submitted Quiz passed check.
	 *
	 * @param array $data - submitted quiz data.
	 *
	 * @return mixed - WP_Error || true if passed, false otherwise.
	 */
	public function submitted_quiz_pased( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error( 'no_data', __( 'No data provided', 'uncanny-automator' ) );
		}

		$passed = ! empty( (int) $data['pass'] );
		// Quiz has been passed return true.
		if ( $passed ) {
			return true;
		}

		// Check if grading is enabled.
		$has_graded = isset( $data['has_graded'] ) ? absint( $data['has_graded'] ) : 0;
		$has_graded = ! empty( $has_graded );
		$graded     = $has_graded && isset( $data['graded'] ) ? $data['graded'] : false;

		if ( $has_graded ) {
			if ( ! empty( $graded ) ) {
				foreach ( $graded as $grade_item ) {
					// Quiz has not been graded yet.
					if ( isset( $grade_item['status'] ) && 'not_graded' === $grade_item['status'] ) {
						return new \WP_Error( 'not_graded', __( 'Quiz has not been graded', 'uncanny-automator' ) );
					}
				}
			}
		}

		// Quiz has not been passed.
		return false;
	}

	/**
	 * Graded Essay - Quiz passed check.
	 *
	 * @param array $essay - essay post object.
	 * @param int $pro_quiz_id - quiz ID.
	 *
	 * @return mixed - WP_Error || true if passed, false otherwise.
	 */
	public function graded_quiz_passed( $essay, $pro_quiz_id ) {

		if ( ! is_a( $essay, 'WP_Post' ) || 'sfwd-essays' !== $essay->post_type ) {
			return new \WP_Error( 'essay', __( 'Not an essay post.', 'uncanny-automator' ) );
		}

		// Not graded yet.
		if ( 'graded' !== $essay->post_status ) {
			return new \WP_Error( 'not_graded', __( 'Quiz has not been graded', 'uncanny-automator' ) );
		}

		// Set vars to determine if the Quiz passed.
		$course_id      = get_post_meta( $essay->ID, 'course_id', true );
		$course_id      = absint( $course_id );
		$pro_quiz_id    = absint( $pro_quiz_id );
		$user_quiz_meta = get_user_meta( $essay->post_author, '_sfwd-quizzes', true );
		$user_quiz_meta = maybe_unserialize( $user_quiz_meta );
		if ( ! is_array( $user_quiz_meta ) ) {
			return new \WP_Error( 'no_data', __( 'No user quiz data recorded', 'uncanny-automator' ) );
		}
		// Reverse the array so we can loop from the latest quiz attempt.
		$user_quiz_meta = array_reverse( $user_quiz_meta );

		foreach ( $user_quiz_meta as $quiz ) {
			if ( $pro_quiz_id === absint( $quiz['pro_quizid'] ) && $course_id === absint( $quiz['course'] ) ) {
				$graded = isset( $quiz['graded'] ) ? $quiz['graded'] : false;
				if ( ! empty( $graded ) && is_array( $graded ) ) {
					// Ensure the currently graded quiz ID is in the Graded array.
					$graded_posts = wp_list_pluck( $graded, 'status', 'post_id' );
					if ( ! key_exists( $essay->ID, $graded_posts ) ) {
						continue;
					}
					// Validate all graded items have been graded.
					if ( in_array( 'not_graded', $graded_posts, true ) ) {
						return new \WP_Error( 'not_graded', __( 'All quizzes have not been graded', 'uncanny-automator' ) );
					}
					// All graded items have been graded return pass or fail bool.
					return absint( $quiz['pass'] );
				}
			}
		}

		return new \WP_Error( 'no_data', __( 'No quiz data recorded', 'uncanny-automator' ) );
	}

	/**
	 * Migrate Graded Quiz Trigger Action Data.
	 *
	 * @param string $code - trigger code.
	 *
	 * @return void
	 */
	public static function migrate_trigger_learndash_quiz_submitted_action_data( $code ) {
		$option_key = strtolower( $code . '_action_migrated' );
		// Bail if already migrated.
		if ( 'yes' === get_option( $option_key, 'no' ) ) {
			return;
		}
		global $wpdb;
		// Get all post IDs where `code` = $code and `add_action` = `learndash_quiz_submitted`
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s AND post_id IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s)",
				'code',
				$code,
				'add_action',
				'learndash_quiz_submitted'
			)
		);
		// Update the `meta_value` of the `add_action` meta key for the selected posts
		if ( ! empty( $post_ids ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $wpdb->postmeta SET meta_value = %s WHERE post_id IN (" . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ') AND meta_key = %s AND meta_value = %s',
					maybe_serialize( array( 'learndash_quiz_submitted', 'learndash_essay_quiz_data_updated' ) ),
					...array_merge( $post_ids, array( 'add_action', 'learndash_quiz_submitted' ) )
				)
			);
		}
		// Update option flag.
		update_option( $option_key, 'yes' );
	}
}
