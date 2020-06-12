<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Recipe_Helpers
 * @package Uncanny_Automator
 */
class Automator_Helpers_Recipe extends Automator_Helpers {

	/**
	 * @var Automator_Helpers_Recipe_Field
	 */
	public $field;
	/**
	 * @var Bbpress_Helpers
	 */
	public $bbpress;
	/**
	 * @var Buddypress_Helpers
	 */
	public $buddypress;
	/**
	 * @var Caldera_Helpers
	 */
	public $caldera_forms;
	/**
	 * @var Contact_Form7_Helpers
	 */
	public $contact_form7;
	/**
	 * @var Edd_Helpers
	 */
	public $edd;
	/**
	 * @var Event_Tickets_Helpers
	 */
	public $event_tickets;
	/**
	 * @var Formidable_Helpers
	 */
	public $formidable;
	/**
	 * @var Forminator_Helpers
	 */
	public $forminator;
	/**
	 * @var Gamipress_Helpers
	 */
	public $gamipress;
	/**
	 * @var Gravity_Forms_Helpers
	 */
	public $gravity_forms;
	/**
	 * @var H5p_Helpers
	 */
	public $h5p;
	/**
	 * @var Learndash_Helpers
	 */
	public $learndash;
	/**
	 * @var Learnpress_Helpers
	 */
	public $learnpress;
	/**
	 * @var Lifterlms_Helpers
	 */
	public $lifterlms;
	/**
	 * @var Memberpress_Helpers
	 */
	public $memberpress;
	/**
	 * @var Ninja_Forms_Helpers
	 */
	public $ninja_forms;
	/**
	 * @var Paid_Memberships_Pro_Helpers
	 */
	public $paid_memberships_pro;
	/**
	 * @var Popup_Maker_Helpers
	 */
	public $popup_maker;
	/**
	 * @var Tutorlms_Helpers
	 */
	public $tutorlms;
	/**
	 * @var Ultimate_Member_Helpers
	 */
	public $ultimate_member;
	/**
	 * @var Wp_Helpers
	 */
	public $wp;
	/**
	 * @var Woocommerce_Helpers
	 */
	public $woocommerce;
	/**
	 * @var Wp_Courseware_Helpers
	 */
	public $wp_courseware;
	/**
	 * @var Wpforms_Helpers
	 */
	public $wpforms;
	/**
	 * @var Wp_Fluent_Forms_Helpers
	 */
	public $wp_fluent_forms;
	/**
	 * @var Wplms_Helpers
	 */
	public $wplms;
	/**
	 * @var Zapier_Helpers
	 */
	public $zapier;
	/**
	 * @var Badgeos_Helpers
	 */
	public $badgeos;
	/**
	 * @var Automator_Helpers_Recipe
	 */
	public $options;
	/*
	 * Check for loading options.
	 */
	/**
	 * @var bool
	 */
	public $load_helpers = false;

	/**
	 * @param mixed $options
	 */
	public function setOptions( $options ) {
		$this->options = $options;
	}

	/**
	 * Automator_Helpers_Recipe constructor.
	 */
	public function __construct() {

		$this->field = new Automator_Helpers_Recipe_Field();

		add_action( 'uncanny_automator_add_integration_helpers', [ $this, 'load_helpers_for_recipes' ] );

		if ( $this->is_edit_page() || $this->is_automator_ajax() ) {
			$this->load_helpers = true;
		}
	}

	/**
	 * @version 2.1.0
	 *
	 * This is purely to give developers access to Helper methods
	 * in IDEs. We could just use loop of $options and load everything
	 * in a loop, but manually assigning some common integrations
	 * i.e., $uncanny_automator->helpers->learndash->xyz() will
	 * list all helper functions of LearnDash.
	 */
	public function load_helpers_for_recipes() {

		$helpers = Utilities::get_all_helper_instances();
		if ( $helpers ) {
			foreach ( $helpers as $integration => $class ) {
				//if ( ! in_array( $integration, $this->custom_load ) ) {
				if ( isset( $this->$integration ) && $this->$integration instanceof $class ) {
					//Already defined, ignore
				} else {
					if ( property_exists( $class, 'options' ) ) {
						$this->$integration = $class;
						$this->$integration->setOptions( $class );
					} else {
						$this->$integration = $class;
					}
				}
				//}
			}
		}
	}

	/**
	 * @return bool
	 */
	public function is_automator_ajax() {

		if ( ! $this->is_ajax() && ! $this->is_rest() ) {
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
		//TODO: drop nonce, item_code, optionCode and may be use doing_rest? I think is_rest() pretty much covers for us
		if ( isset( $_POST['action'] ) && ( isset( $_POST['nonce'] ) || isset( $_POST['item_code'] ) || isset( $_POST['optionCode'] ) ) ) {
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
	 * Checks if the current request is a WP REST API request.
	 *
	 * Case #1: After WP_REST_Request initialisation
	 * Case #2: Support "plain" permalink settings
	 * Case #3: It can happen that WP_Rewrite is not yet initialized,
	 *          so do this (wp-settings.php)
	 * Case #4: URL Path begins with wp-json/ (your REST prefix)
	 *          Also supports WP installations in subfolders
	 *
	 * @returns boolean
	 * @author matzeeable
	 */
	function is_rest() {
		$prefix = rest_get_url_prefix();
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST // (#1)
		     || isset( $_GET['rest_route'] ) // (#2)
		        && strpos( trim( $_GET['rest_route'], '\\/' ), $prefix, 0 ) === 0 ) {
			return true;
		}
		// (#3)
		global $wp_rewrite;
		if ( $wp_rewrite === null ) {
			$wp_rewrite = new \WP_Rewrite();
		}

		// (#4)
		$current_url = wp_parse_url( add_query_arg( array() ) );
		$regex       = '/\/' . $prefix . '\/(' . str_replace( '/', '\/', AUTOMATOR_REST_API_END_POINT ) . '.+)/';
		$match       = $current_url['path'];


		return preg_match( $regex, $match );
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
	 * @param string $label
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function number_of_times( $label = null, $description = null, $placeholder = null ) {

		if ( ! $label ) {
			$label = __( 'Number of times', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = __( 'Example: 1', 'uncanny-automator' );
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
			'label'       => __( 'Condition', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			// 'default_value'      => false,
			'options'     => [
				'='  => __( 'equal to', 'uncanny-automator' ),
				'!=' => __( 'not equal to', 'uncanny-automator' ),
				'<'  => __( 'less than', 'uncanny-automator' ),
				'>'  => __( 'greater than', 'uncanny-automator' ),
				'>=' => __( 'greater or equal to', 'uncanny-automator' ),
				'<=' => __( 'less or equal to', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_less_or_greater_than', $option );
	}


	/**
	 * @param string $option_code
	 * @param string $label
	 *
	 * @return mixed
	 */
	public function get_redirect_url( $label = null, $option_code = 'REDIRECTURL' ) {

		if ( ! $label ) {
			$label = __( 'Redirect URL', 'uncanny-automator' );
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
	 * @param array $args
	 * @param bool $add_any_option
	 * @param string $add_any_option_label
	 * @param bool $is_all_label
	 *
	 * @return array
	 * @version 2.1.4 - changes made to pass __('All pages', 'uncanny-automator') as string instead of
	 * page, post, course etc
	 * @version 2.4 - Added transients
	 *
	 *
	 * @version 1.0 - added
	 */
	public function wp_query( $args, $add_any_option = false, $add_any_option_label = null, $is_all_label = false ) {

		if ( is_null( $add_any_option_label ) ) {
			$add_any_option_label = __( 'Any page', 'uncanny-automator' );
		}

		if ( ! $this->load_helpers ) {
			return [];
		}

		if ( empty( $args ) ) {
			return [];
		}

		if ( isset( $args['post_type'] ) ) {
			$transient = "uap_transient_{$args['post_type']}";
		} else {
			$transient = "uap_transient";
		}
		$get_transient = get_transient( $transient );
		if ( $get_transient ) {
			$options = $get_transient;
		} else {
			$posts = get_posts( $args );

			$options = [];
			if ( $add_any_option ) {
				//switch statement is for pre-v2.1.4
				//default statement is v2.1.4+ in which
				//__() is passed to $add_any_option_label
				switch ( $add_any_option_label ) {
					case 'page':
					case 'pages':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All pages', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any page', 'uncanny-automator' );
						}
						break;
					case 'post':
					case 'posts':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All posts', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any post', 'uncanny-automator' );
						}
						break;
					case 'course':
					case 'courses':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All courses', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any course', 'uncanny-automator' );
						}
						break;
					case 'lesson':
					case 'lessons':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All lessons', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any lesson', 'uncanny-automator' );
						}
						break;
					case 'topic':
					case 'topics':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All topics', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any topic', 'uncanny-automator' );
						}
						break;
					case 'quiz':
					case 'quizzes':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All quizzes', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any quiz', 'uncanny-automator' );
						}
						break;
					case 'membership':
					case 'memberships':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All memberships', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any membership', 'uncanny-automator' );
						}
						break;
					case 'download':
					case 'downloads':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All downloads', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any download', 'uncanny-automator' );
						}
						break;
					case 'unit':
					case 'units':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All units', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any unit', 'uncanny-automator' );
						}
						break;
					case 'popup':
					case 'popups':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All popups', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any popup', 'uncanny-automator' );
						}
						break;
					case 'award':
					case 'awards':
						if ( $is_all_label ) {
							$options['-1'] = __( 'All awards', 'uncanny-automator' );
						} else {
							$options['-1'] = __( 'Any award', 'uncanny-automator' );
						}
						break;
					default:
						//fallback, assuming __() string is passed
						$options['-1'] = $add_any_option_label;
						break;
				}
			}

			if ( $posts ) {
				foreach ( $posts as $post ) {
					$title = $post->post_title;

					if ( empty( $title ) ) {
						$title = sprintf( __( 'ID: %1$s (no title)', 'uncanny-automator' ), $post->ID );
					}

					$options[ $post->ID ] = $title;
				}

				/**
				 * Hold data in transient for X mins
				 * so that edit recipe page speeds up.
				 *
				 * @version 2.4
				 * @author Saad
				 *
				 */
				set_transient( $transient, $options, 5 * MINUTE_IN_SECONDS );

			}
		}

		return $options;
	}

	/**
	 * Replacing frequently used helpers function/query to
	 * central so that it doesn't have to defined repeatedly
	 *
	 * @param $meta_key
	 * @param $trigger_id
	 * @param $trigger_log_id
	 * @param null $user_id
	 *
	 * @return mixed|string
	 * @version 2.2
	 *
	 * @author Saad
	 */
	public function get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id = null ) {
		if ( empty( $meta_key ) || empty( $trigger_id ) || empty( $trigger_log_id ) ) {
			return '';
		}
		global $wpdb;
		$qry        = $wpdb->prepare( "SELECT meta_value 
														FROM {$wpdb->prefix}uap_trigger_log_meta 
														WHERE 1 = 1
														AND user_id = %d 
														AND meta_key = %s 
														AND automator_trigger_id = %d 
														AND automator_trigger_log_id = %d 
														LIMIT 0,1", $user_id, $meta_key, $trigger_id, $trigger_log_id );
		$meta_value = $wpdb->get_var( $qry );
		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}
}