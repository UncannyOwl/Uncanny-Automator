<?php


namespace Uncanny_Automator;

/**
 * Class Automator_Options
 * @package Uncanny_Automator
 * @deprecated since v2.1.0
 */
class Automator_Options {

	/*
	 * Check for loading options.
	 */
	protected $load_option = false;

	public function __construct() {
		if ( $this->is_edit_page() || $this->is_automator_ajax() ) {
			$this->load_option = true;
		}
	}

	/**
	 * @return bool
	 */
	public function is_automator_ajax() {
		if ( ! $this->is_ajax() ) {
			return false;
		}

		//#10488 - ticket fix
		$ignore_actions = [
			'activity_filter',
			'bp_spam_activity',
			'post_update',
			'bp_nouveau_get_activity_objects',
			'new_activity_comment',
			'delete_activity',
			'activity_clear_new_mentions',
			'activity_mark_unfav',
			'activity_mark_fav',
			'get_single_activity_content',
			'messages_search_recipients',
			'messages_dismiss_sitewide_notice',
			'messages_read',
			'messages_unread',
			'messages_star',
			'messages_unstar',
			'messages_delete',
			'messages_get_thread_messages',
			'messages_thread_read',
			'messages_get_user_message_threads',
			'messages_send_reply',
			'messages_send_message',
			'groups_filter',
			'gamipress_track_visit',
		];

		//Provide a filter for future use
		$ignore_actions = apply_filters( 'automator_post_actions_ignore_list', $ignore_actions );

		if ( isset( $_POST['action'] ) && isset( $_POST['nonce'] ) ) {
			if ( in_array( $_POST['action'], $ignore_actions ) ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function is_ajax() {
		return function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : defined( 'DOING_AJAX' );
	}

	/**
	 * is_edit_page
	 * function to check if the current page is a post edit page
	 *
	 * @param string $new_edit what page to check for accepts new - new post page ,edit - edit post page, null for either
	 *
	 * @return boolean
	 */
	public function is_edit_page( $new_edit = null ) {
		global $pagenow;

		if ( null === $pagenow && isset( $_SERVER['SCRIPT_FILENAME'] ) ) {
			$pagenow = basename( $_SERVER['SCRIPT_FILENAME'] );
		}
		//make sure we are on the backend
		if ( ! is_admin() ) {
			return false;
		}
		if ( isset( $_GET['post'] ) && ! empty( $_GET['post'] ) ) {
			$current_post = get_post( absint( $_GET['post'] ) );
			if ( isset( $current_post->post_type ) && 'uo-recipe' === $current_post->post_type && in_array( $pagenow, [
					'post.php',
					'post-new.php'
				] ) ) {
				return true;
			}

			return false;
		}

		return false;
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function integer_field( $option_code = 'INT', $label = null, $description = null, $placeholder = null ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Number', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder =  esc_attr__( 'Example: 1', 'uncanny-automator' );
		}

		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'description' => $description,
			'placeholder' => $placeholder,
			'input_type'  => 'int',
			'required'    => true,
		];


		return apply_filters( 'uap_option_integer_field', $option );
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function float_field( $option_code = 'FLOAT', $label = null, $description = null, $placeholder = null ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Number', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder =  esc_attr__( 'Example: 1.1', 'uncanny-automator' );
		}

		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'description' => $description,
			'placeholder' => $placeholder,
			'input_type'  => 'float',
			'required'    => true,
		];


		return apply_filters( 'uap_option_float_field', $option );
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param bool $tokens
	 * @param string $type
	 * @param string $default
	 * @param bool
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function text_field( $option_code = 'TEXT', $label = null, $tokens = false, $type = 'text', $default = null, $required = true, $description = '', $placeholder = null ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Text', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = '';
		}

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'description'     => $description,
			'placeholder'     => $placeholder,
			'input_type'      => $type,
			'supports_tokens' => $tokens,
			'required'        => $required,
			'default_value'   => $default,
		];

		if ( 'textarea' === $type ) {
			$option['supports_tinymce'] = true;
		}


		return apply_filters( 'uap_option_text_field', $option );
	}


	/**
	 * @param string $option_code
	 * @param string $label
	 * @param array $options
	 * @param string $default
	 * @param bool $is_ajax
	 * @param string $fill_values_in
	 *
	 * @return mixed
	 */
	public function select_field( $option_code = 'SELECT', $label = null, $options = [], $default = null, $is_ajax = false, $fill_values_in = '', $relevant_tokens = [] ) {

		// TODO this function should be the main way to create select fields
		// TODO chained values should be introduced using the format in function "list_gravity_forms"
		// TODO the following function should use this function to create selections
		// -- less_or_greater_than
		// -- all_posts
		// -- all_pages
		// -- all_ld_courses
		// -- all_ld_lessons
		// -- all_ld_topics
		// -- all_ld_groups
		// -- all_ld_quiz
		// -- all_buddypress_groups
		// -- all_wc_products
		// -- list_contact_form7_forms
		// -- list_bbpress_forums
		// -- wc_order_statuses
		// -- wp_user_roles
		// -- list_gravity_forms
		// -- all_ec_events
		// -- all_lp_courses
		// -- all_lp_lessons
		// -- all_lf_courses
		// -- all_lf_lessons

		if ( ! $label ) {
			$label =  esc_attr__( 'Option', 'uncanny-automator' );
		}

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'supports_tokens' => apply_filters( 'uap_option_' . $option_code . '_select_field', false ),
			'required'        => true,
			'default_value'   => $default,
			'options'         => $options,
			//'is_ajax'         => $is_ajax,
			//'chained_to'      => $fill_values_in,
		];

		if ( ! empty( $relevant_tokens ) ) {
			$option['relevant_tokens'] = $relevant_tokens;
		}

		return apply_filters( 'uap_option_select_field', $option );
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param array $options
	 * @param string $default
	 * @param bool $is_ajax
	 *
	 * @return mixed
	 */
	public function select_field_ajax( $option_code = 'SELECT', $label = null, $options = [], $default = null, $placeholder = '', $supports_token = false, $is_ajax = false, $args = [], $relevant_tokens = [] ) {


		// TODO this function should be the main way to create select fields
		// TODO chained values should be introduced using the format in function "list_gravity_forms"
		// TODO the following function should use this function to create selections
		// -- less_or_greater_than
		// -- all_posts
		// -- all_pages
		// -- all_ld_courses
		// -- all_ld_lessons
		// -- all_ld_topics
		// -- all_ld_groups
		// -- all_ld_quiz
		// -- all_buddypress_groups
		// -- all_wc_products
		// -- list_contact_form7_forms
		// -- list_bbpress_forums
		// -- wc_order_statuses
		// -- wp_user_roles
		// -- list_gravity_forms
		// -- all_ec_events
		// -- all_lp_courses
		// -- all_lp_lessons
		// -- all_lf_courses
		// -- all_lf_lessons

		if ( ! $label ) {
			$label =  esc_attr__( 'Option', 'uncanny-automator' );
		}

		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : '';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'supports_tokens' => apply_filters( 'uap_option_' . $option_code . '_select_field', false ),
			'supports_custom_value' => $supports_custom_value,
			'required'        => true,
			'default_value'   => $default,
			'options'         => $options,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'integration'     => 'GF',
			'endpoint'        => $end_point,
			'placeholder'     => $placeholder,
		];

		if ( ! empty( $relevant_tokens ) ) {
			$option['relevant_tokens'] = $relevant_tokens;
		}

		return apply_filters( 'uap_option_select_field_ajax', $option );
	}

	/**
	 * @param string $label
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function number_of_times( $label = null, $description = null, $placeholder = null ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Number of times', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder =  esc_attr__( 'Example: 1', 'uncanny-automator' );
		}

		$option = [
			'option_code'   => 'NUMTIMES',
			'label'         => $label,
			'description'   => $description,
			'placeholder'   => $placeholder,
			'input_type'    => 'int',
			'default_value' => 1,
			'required'      => true,
		];

		return apply_filters( 'uap_option_number_of_times', $option );
	}

	/**
	 * @return mixed
	 */
	public function less_or_greater_than() {
		$option = [
			'option_code' => 'NUMBERCOND',
			/* translators: Noun */
			'label'       =>  esc_attr__( 'Condition', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			// 'default_value'      => false,
			'options'     => [
				'='  =>  esc_attr__( 'equal to', 'uncanny-automator' ),
				'!=' =>  esc_attr__( 'not equal to', 'uncanny-automator' ),
				'<'  =>  esc_attr__( 'less than', 'uncanny-automator' ),
				'>'  =>  esc_attr__( 'greater than', 'uncanny-automator' ),
				'>=' =>  esc_attr__( 'greater or equal to', 'uncanny-automator' ),
				'<=' =>  esc_attr__( 'less or equal to', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_less_or_greater_than', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_posts( $label = null, $option_code = 'WPPOST', $any_option = true ) {

		if ( ! $label ) {
			/* translators: Noun */
			$label =  esc_attr__( 'Post', 'uncanny-automator' );
		}

		$args = [
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'post',
			'post_status'    => 'publish',
		];

		$all_posts = $this->wp_query( $args, $any_option,  esc_attr__( 'Any post', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $all_posts,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Post title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Post ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Post URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_posts', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_pages( $label = null, $option_code = 'WPPAGE', $any_option = false ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Page', 'uncanny-automator' );
		}

		$args = [
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'page',
			'post_status'    => 'publish',
		];

		$all_pages = $this->wp_query( $args, $any_option,  esc_attr__( 'Any page', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $all_pages,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Page title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Page ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Page URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_pages', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_ld_courses( $label = null, $option_code = 'LDCOURSE', $any_option = true ) {

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

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any course', 'uncanny-automator' ) );

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

		$options = $this->wp_query( $args, $any_lesson,  esc_attr__( 'Any lesson', 'uncanny-automator' ) );

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

		$options = $this->wp_query( $args, true,  esc_attr__( 'Any topic', 'uncanny-automator' ) );

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
			$options = $this->wp_query( $args, $any_option, 'groups', $all_label );
		} else {
			$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any group', 'uncanny-automator' ) );
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

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any quiz', 'uncanny-automator' ) );

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
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_buddypress_groups( $label = null, $option_code = 'BPGROUPS', $args = array() ) {

		$args = wp_parse_args( $args, array(
			'uo_include_any' => false,
			'uo_any_label'   =>  esc_attr__( 'Any group', 'uncanny-automator' ),
			'status'         => array( 'public' ),
		) );

		if ( ! $label ) {
			$label =  esc_attr__( 'Group', 'uncanny-automator' );
		}

		global $wpdb;
		$qry     = "SHOW TABLES LIKE '{$wpdb->prefix}bp_groups';";
		$options = [];
		if ( $this->load_option ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			if ( $wpdb->query( $qry ) ) {
				// previous solution was not preparing correct query
				$in_str_arr = array_fill( 0, count( $args['status'] ), '%s' );
				$in_str     = join( ',', $in_str_arr );
				$group_qry  = $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}bp_groups WHERE status IN ($in_str)",
					$args['status']
				);
				$results    = $wpdb->get_results( $group_qry );

				if ( $results ) {
					foreach ( $results as $result ) {
						$options[ $result->id ] = $result->name;
					}
				}
			}
		}
		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $options,
		];


		return apply_filters( 'uap_option_all_buddypress_groups', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_buddypress_users( $label = null, $option_code = 'BPUSERS', $args = array() ) {
		if ( ! $label ) {
			$label =  esc_attr__( 'User', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   =>  esc_attr__( 'Any user', 'uncanny-automator' ),
			)
		);

		$options = [];
		if ( $this->load_option ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			$users = get_users();

			foreach ( $users as $user ) {
				$options[ $user->ID ] = $user->display_name;
			}
		}
		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $options,
		];


		return apply_filters( 'uap_option_all_buddypress_users', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wc_products( $label = null, $option_code = 'WOOPRODUCT' ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'product',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Product title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Product URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wc_products', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wc_subscriptions( $label = null, $option_code = 'WOOSUBSCRIPTIONS' ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Subscription', 'uncanny-automator' );
		}

		global $wpdb;
		$q = "
			SELECT posts.ID, posts.post_title FROM $wpdb->posts as posts
			LEFT JOIN $wpdb->term_relationships as rel ON (posts.ID = rel.object_id)
			WHERE rel.term_taxonomy_id IN (SELECT term_id FROM $wpdb->terms WHERE slug IN ('subscription','variable-subscription'))
			AND posts.post_type = 'product'
			AND posts.post_status = 'publish'
			UNION ALL
			SELECT ID, post_title FROM $wpdb->posts
			WHERE post_type = 'shop_subscription'
			AND post_status = 'publish'
			ORDER BY post_title
		";

		// Query all subscription products based on the assigned product_type category (new WC type) and post_type shop_"
		$subscriptions = $wpdb->get_results( $q );

		$options       = [];
		$options['-1'] =  esc_attr__( 'Any subscription', 'uncanny-automator' );

		foreach ( $subscriptions as $post ) {
			$title = $post->post_title;

			if ( empty( $title ) ) {
				$title = sprintf(  esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $post->ID );
			}

			$options[ $post->ID ] = $title;
		}

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Product title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Product URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wc_subscriptions', $option );
	}


	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function list_contact_form7_forms( $label = null, $option_code = 'CF7FORMS', $args = [] ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Form', 'uncanny-automator' );
		}
		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		$args = [
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args );
		$type    = 'select';
//		$option = [
//			'option_code' => $option_code,
//			'label'       => $label,
//			'input_type'  => 'select',
//			'required'    => true,
//			'options'     => $options,
//		];
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Form ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Form URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_list_contact_form7_forms', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function list_bbpress_forums( $label = null, $option_code = 'BBFORUMS' ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Forum', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => bbp_get_forum_post_type(),
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => [ 'publish', 'private' ],
		];

		$options = $this->wp_query( $args );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Forum title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Forum ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Forum URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_list_bbpress_forums', $option );
	}


	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function wc_order_statuses( $label = null, $option_code = 'WCORDERSTATUS' ) {

		// TODO this currently has no usage. remove if its unused in version 1.0

		if ( ! $label ) {
			$label = 'Status';
		}


		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => wc_get_order_statuses(),
		];

		return apply_filters( 'uap_option_woocommerce_statuses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function wp_user_roles( $label = null, $option_code = 'WPROLE' ) {

		if ( ! $label ) {
			/* translators: WordPress role */
			$label =  esc_attr__( 'Role', 'uncanny-automator' );
		}

		$roles = [];
		if ( $this->load_option ) {
			foreach ( wp_roles()->roles as $role_name => $role_info ) {
				$roles[ $role_name ] = $role_info['name'];
			}
		}
		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $roles,
		];

		return apply_filters( 'uap_option_wp_user_roles', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_gravity_forms( $label = null, $option_code = 'GFFORMS', $args = [] ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		if ( $this->load_option ) {
			$forms = \GFFormsModel::get_forms();

			foreach ( $forms as $form ) {
				$options[ $form->id ] = esc_html( $form->title );
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_list_gravity_forms', $option );

	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ec_events( $label = null, $option_code = 'ECEVENTS' ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Event', 'uncanny-automator' );
		}

		$args = [
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
		];

		$all_events = $this->wp_query( $args );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			//'default_value'      => 'Any post',
			'options'         => $all_events,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Event title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Event ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Event URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ec_events', $option );
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 *
	 * @return mixed
	 */
	public function get_redirect_type( $option_code = 'REDIRECTTYPE', $label = 'Redirect Type' ) {

		//TODO this function is currently unused, remove if its still not needed in version 1.0
		$options      = [];
		$options[301] = 'Permanently';
		$options[302] = 'Temporarily';

		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $options,
		];

		return apply_filters( 'uap_option_get_redirect_type', $option );
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 *
	 * @return mixed
	 */
	public function get_redirect_url( $label = null, $option_code = 'REDIRECTURL' ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Redirect URL', 'uncanny-automator' );
		}

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'url',
			'supports_tokens' => true,
			'required'        => true,
			'placeholder'     => 'https://',
		];

		return apply_filters( 'uap_option_get_redirect_url', $option );
	}


	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_lp_courses( $label = null, $option_code = 'LPCOURSE', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'lp_course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any course', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Course URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lp_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lp_lessons( $label = null, $option_code = 'LPLESSON', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Lesson', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'lp_lesson',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any lesson', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Lesson title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Lesson URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lp_lessons', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_lf_courses( $label = null, $option_code = 'LFCOURSE', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any course', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Course URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lf_lessons( $label = null, $option_code = 'LFLESSON', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Lesson', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'lesson',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any lesson', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Lesson title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Lesson ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Lesson URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_lessons', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lf_sections( $label = null, $option_code = 'LFSECTION', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Section', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'section',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any section', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Section title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Section ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Section URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_sections', $option );
	}


	/**
	 * @param array $args
	 * @param bool $add_any_option
	 * @param string $add_any_option_label
	 *
	 * @return array
	 */
	public function wp_query( $args, $add_any_option = false, $add_any_option_label = 'page', $is_all_label = false ) {

		if ( ! $this->load_option ) {
			return [];
		}

		if ( empty( $args ) ) {
			return [];
		}
		$posts   = get_posts( $args );
		$options = [];
		if ( $add_any_option ) {
			if ( $is_all_label ) {
				/* translators: Fallback. All types of content (post, page, media, etc) */
				$options['-1'] = sprintf(  esc_attr__( 'All %s', 'uncanny-automator' ), $add_any_option_label );
			} else {
				/* translators: Fallback. Any type of content (post, page, media, etc) */
				$options['-1'] = sprintf(  esc_attr__( 'Any %s', 'uncanny-automator' ), $add_any_option_label );
			}
		}
		foreach ( $posts as $post ) {
			$title = $post->post_title;

			if ( empty( $title ) ) {
				$title = sprintf(  esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $post->ID );
			}

			$options[ $post->ID ] = $title;
		}

		return $options;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_edd_downloads( $label = null, $option_code = 'EDDPRODUCTS', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'download',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any download', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Download title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Download ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Download URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_edd_downloads', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wpcw_units( $label = null, $option_code = 'WPCW_UNIT', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Unit', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'course_unit',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any unit', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Unit title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Unit ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Unit URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wpcw_units', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */

	public function all_wpcw_modules( $label = null, $option_code = 'WPCW_MODULE', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Module', 'uncanny-automator' );
		}
		$modules = array();
		$options = [];
		if ( $this->load_option ) {
			if ( function_exists( 'wpcw_get_modules' ) ) {
				$modules = wpcw_get_modules();
			}

			if ( $any_option ) {
				$options['-1'] =  esc_attr__( 'Any module', 'uncanny-automator' );
			}
			if ( ! empty( $modules ) ) {
				foreach ( $modules as $module ) {
					$options[ $module->module_id ] = $module->module_title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
		];

		return apply_filters( 'uap_option_all_wpcw_modules', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */

	public function all_wpcw_courses( $label = null, $option_code = 'WPCW_COURSE', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'wpcw_course',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any course', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Course URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wpcw_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_wplms_courses( $label = null, $option_code = 'WPLMS_COURSE', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Course', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any course', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Course title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Course ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Course URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wplms_courses', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wplms_units( $label = null, $option_code = 'WPLMS_UNIT', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Unit', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'unit',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any unit', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Unit title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Unit ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Unit URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wplms_units', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_wplms_quizs( $label = null, $option_code = 'WPLMS_QUIZ', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Quiz', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'quiz',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any quiz', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Quiz title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Quiz URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_wplms_quizs', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_h5p_contents( $label = null, $option_code = 'H5P_CONTENT', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Content', 'uncanny-automator' );
		}

		global $wpdb;
		$options = [];
		if ( $this->load_option ) {
			// Get the library content
			$contents = $wpdb->get_results(
				"SELECT c.id,c.title FROM {$wpdb->prefix}h5p_contents c"
			);

			if ( $any_option ) {
				$options['-1'] =  esc_attr__( 'Any content', 'uncanny-automator' );
			}
			if ( ! empty( $contents ) ) {
				foreach ( $contents as $content ) {
					$options[ $content->id ] = $content->title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
		];

		return apply_filters( 'uap_option_all_h5p_contents', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_h5p_content_types( $label = null, $option_code = 'H5P_CONTENTTYPE', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Content type', 'uncanny-automator' );
		}

		global $wpdb;
		$options = [];
		if ( $this->load_option ) {
			// Get the library content
			$types = $wpdb->get_results(
				"SELECT t.id,t.title FROM {$wpdb->prefix}h5p_libraries t WHERE t.runnable = 1 "
			);

			if ( $any_option ) {
				$options['-1'] =  esc_attr__( 'Any content type', 'uncanny-automator' );
			}
			if ( ! empty( $types ) ) {
				foreach ( $types as $type ) {
					$options[ $type->id ] = $type->title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
		];

		return apply_filters( 'uap_option_all_h5p_content_types', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_ninja_forms( $label = null, $option_code = 'NFFORMS', $args = [] ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		if ( $this->load_option ) {
			$forms = \Ninja_Forms()->form()->get_forms();

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form->get_id() ] = esc_html( $form->get_setting( 'title' ) );
				}
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_list_ninja_forms', $option );

	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_wp_forms( $label = null, $option_code = 'WPFFORMS', $args = [] ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		if ( $this->load_option ) {
			$wpforms = new \WPForms_Form_Handler();

			$forms = $wpforms->get( '', [
				'orderby' => 'title',
			] );

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form->ID ] = esc_html( $form->post_title );
				}
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_list_wp_forms', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_post_types( $label = null, $option_code = 'WPPOSTTYPES', $args = [] ) {
		if ( ! $label ) {
			$label =  esc_attr__( 'Post type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];
		if ( $this->load_option ) {
			$args = [
				'public'   => true,
				'_builtin' => false,
			];

			$output   = 'object';
			$operator = 'and';

			$post_types = get_post_types( $args, $output, $operator );
			if ( ! empty( $post_types ) ) {
				foreach ( $post_types as $post_type ) {
					$options[ $post_type->name ] = esc_html( $post_type->label );
				}
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];


		return apply_filters( 'uap_option_all_post_types', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_gp_award_types( $label = null, $option_code = 'GPAWARDTYPES', $args = [] ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Achievement type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		if ( $this->load_option ) {
			$posts = get_posts( [
				'post_type'      => 'achievement-type',
				'posts_per_page' => 9999,
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					if ( $post->post_type === 'achievement-type' ) {
						$options[ $post->post_name ] = $post->post_title;
					}
				}
			}
			/* translators: GamiPress achievement type */
			$options['points-award']     =  esc_attr__( 'Points awards', 'uncanny-automator' );
			/* translators: GamiPress achievement type */
			$options['step']             =  esc_attr__( 'Step', 'uncanny-automator' );
			/* translators: GamiPress achievement type */
			$options['rank-requirement'] =  esc_attr__( 'Rank requirement', 'uncanny-automator' );
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_list_gp_award_types', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_gp_points_types( $label = null, $option_code = 'GPPOINTSTYPES', $args = [] ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Point type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$include_all  = key_exists( 'include_all', $args ) ? $args['include_all'] : false;

		$options = [];

		if ( $include_all ) {
			$options['ua-all-gp-types'] =  esc_attr__( 'All point types', 'uncanny-automator' );
		}

		if ( $this->load_option ) {
			$posts = get_posts( [
				'post_type'      => 'points-type',
				'posts_per_page' => 9999,
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					if ( $post->post_type === 'points-type' ) {
						$options[ $post->post_name ] = $post->post_title;
					}
				}
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_list_gp_points_types', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_gp_rank_types( $label = null, $option_code = 'GPRANKTYPES', $args = [] ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Rank type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		if ( $this->load_option ) {
			$posts = get_posts( [
				'post_type'      => 'rank-type',
				'posts_per_page' => 9999,
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					if ( $post->post_type === 'rank-type' ) {
						$options[ $post->post_name ] = $post->post_title;
					}
				}
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_list_gp_rank_types', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products_onetime( $label = null, $option_code = 'MPPRODUCT', $args = [] ) {
		if ( ! $label ) {
			$label =  esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   =>  esc_attr__( 'Any one-time subscription product', 'uncanny-automator' ),
			)
		);

		$options = [];
		if ( $this->load_option ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			$posts = get_posts( [
				'post_type'      => 'memberpressproduct',
				'posts_per_page' => 999,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => '_mepr_product_period_type',
						'value'   => 'lifetime',
						'compare' => '=',
					]
				]
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$options[ $post->ID ] = $post->post_title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Product title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Product URL', 'uncanny-automator' ),
			],
		];


		return apply_filters( 'uap_option_all_memberpress_products_onetime', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products_recurring( $label = null, $option_code = 'MPPRODUCT', $args = [] ) {
		if ( ! $label ) {
			$label =  esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   =>  esc_attr__( 'Any recurring subscription product', 'uncanny-automator' ),
			)
		);

		$options = [];
		if ( $this->load_option ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			$posts = get_posts( [
				'post_type'      => 'memberpressproduct',
				'posts_per_page' => 999,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => '_mepr_product_period_type',
						'value'   => 'lifetime',
						'compare' => '!=',
					]
				]
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$options[ $post->ID ] = $post->post_title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Product title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Product URL', 'uncanny-automator' ),
			],
		];


		return apply_filters( 'uap_option_all_memberpress_products_recurring', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products( $label = null, $option_code = 'MPPRODUCT', $args = [] ) {
		if ( ! $label ) {
			$label =  esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   =>  esc_attr__( 'Any product', 'uncanny-automator' ),
			)
		);

		$options = [];
		if ( $this->load_option ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			$posts = get_posts( [
				'post_type'      => 'memberpressproduct',
				'posts_per_page' => 999,
				'post_status'    => 'publish',
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$options[ $post->ID ] = $post->post_title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Product title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Product URL', 'uncanny-automator' ),
			],
		];


		return apply_filters( 'uap_option_all_memberpress_products', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_formidable_forms( $label = null, $option_code = 'FIFORMS', $args = [] ) {
		if ( ! $label ) {
			$label =  esc_attr__( 'Form', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   =>  esc_attr__( 'Any form', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];
		if ( $this->load_option ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}
			$s_query                = [
				[
					'or'               => 1,
					'parent_form_id'   => null,
					'parent_form_id <' => 1,
				],
			];
			$s_query['is_template'] = 0;
			$s_query['status !']    = 'trash';
			$forms                  = \FrmForm::getAll( $s_query, '', ' 0, 999' );

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form->id ] = $form->name;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_all_formidable_forms', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lf_quizs( $label = null, $option_code = 'LFQUIZ', $any_option = true ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Quiz', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'llms_quiz',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option,  esc_attr__( 'Any quiz', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Quiz title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Quiz ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Quiz URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_quizs', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_lf_memberships( $label = null, $option_code = 'LFMEMBERSHIP', $any_option = true, $is_all_label = false ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Membership', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'llms_membership',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		$options = $this->wp_query( $args, $any_option, 'membership', $is_all_label );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Membership title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Membership ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Membership URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_lf_memberships', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function list_caldera_forms_forms( $label = null, $option_code = 'CFFORMS', $args = [] ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Form', 'uncanny-automator' );
		}
		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];
		if ( $this->load_option ) {
			$forms = \Caldera_Forms_Forms::get_forms( true );

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form['ID'] ] = $form['name'];
				}
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_list_caldera_forms_forms', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ec_rsvp_events( $label = null, $option_code = 'ECEVENTS' ) {

		if ( ! $label ) {
			$label =  esc_attr__( 'Event', 'uncanny-automator' );
		}

		$args    = [
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
		];
		$options = [];
		if ( $this->load_option ) {
			$posts          = get_posts( $args );
			$ticket_handler = new \Tribe__Tickets__Tickets_Handler();
			foreach ( $posts as $post ) {
				$title = $post->post_title;

				if ( empty( $title ) ) {
					$title = sprintf(  esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $post->ID );
				}

				$rsvp_ticket = $ticket_handler->get_event_rsvp_tickets( $post );

				if ( ! empty ( $rsvp_ticket ) ) {
					$options[ $post->ID ] = $title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			//'default_value'      => 'Any post',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          =>  esc_attr__( 'Event title', 'uncanny-automator' ),
				$option_code . '_ID'  =>  esc_attr__( 'Event ID', 'uncanny-automator' ),
				$option_code . '_URL' =>  esc_attr__( 'Event URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ec_events', $option );
	}
}