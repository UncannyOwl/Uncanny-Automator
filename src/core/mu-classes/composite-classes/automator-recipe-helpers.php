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
	 * @var Fluent_Crm_Helpers
	 */
	public $fluent_crm;
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
	 * @var Give_Helpers
	 */
	public $give;
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
	 * @var Restrict_Content_Helpers
	 */
	public $restrict_content;
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
	 * @var Mycred_Helpers
	 */
	public $mycred;
	/**
	 * @var Upsell_Plugin_Helpers
	 */
	public $upsell_plugin;
	/**
	 * @var Wishlist_Member_Helpers
	 */
	public $wishlist_member;
	/**
	 * @var Uoa_Helpers
	 */
	public $uncanny_automator;
	/**
	 * @var Events_Manager_Helpers
	 */
	public $events_manager;
	/**
	 * @var Uncanny_Codes_Helpers
	 */
	public $uncanny_codes;
	/**
	 * @var Mailpoet_Helpers
	 */
	public $mailpoet;
	/**
	 * @var Wpwh_Helpers
	 */
	public $wp_webhooks;
	/**
	 * @var Happyforms_Helpers
	 */
	public $happyforms;
	/**
	 * @var Buddyboss_Helpers
	 */
	public $buddyboss;
	/**
	 * @var Wpwh_Helpers
	 */
	public $elementor;
	/**
	 * @var Wpjm_Helpers
	 */
	public $wp_job_manager;
	/**
	 * @var Wc_Memberships_Helpers
	 */
	public $wc_memberships;
	/**
	 * @var Affwp_Helpers
	 */
	public $affiliate_wp;
	/**
	 * @var Wp_User_Manager_Helpers
	 */
	public $wp_user_manager;
	/**
	 * @var Wpsp_Helpers
	 */
	public $wp_simple_pay;
	/**
	 * @var Twitter_Helpers
	 */
	public $twitter;
	/**
	 * @var Automator_Helpers_Recipe
	 */
	public $presto;
	/**
	 * @var Presto_Helpers;
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

		add_action( 'uncanny_automator_add_integration_helpers', array( $this, 'load_helpers_for_recipes' ) );

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
		$ignore_actions = array(
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
		);

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
		$match       = isset( $current_url['path'] ) ? $current_url['path'] : '';

		return preg_match( $regex, $match );
	}

	/**
	 * is_edit_page
	 * function to check if the current page is a post edit page
	 *
	 * @param string $new_edit what page to check for accepts new - new post page ,edit - edit post page, null for
	 *                         either
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
			if ( isset( $current_post->post_type ) && 'uo-recipe' === $current_post->post_type && in_array(
					$pagenow,
					array(
						'post.php',
						'post-new.php',
					)
				) ) {
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
			$label = esc_attr__( 'Number of times', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = esc_attr__( 'Example: 1', 'uncanny-automator' );
		}

		$option = array(
			'option_code'   => 'NUMTIMES',
			'label'         => $label,
			'description'   => $description,
			'placeholder'   => $placeholder,
			'input_type'    => 'int',
			'default_value' => 1,
			'required'      => true,
		);

		return apply_filters( 'uap_option_number_of_times', $option );
	}

	/**
	 * @return mixed
	 */
	public function less_or_greater_than() {
		$option = array(
			'option_code' => 'NUMBERCOND',
			/* translators: Noun */
			'label'       => esc_attr__( 'Condition', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			// 'default_value'      => false,
			'options'     => array(
				'='  => esc_attr__( 'equal to', 'uncanny-automator' ),
				'!=' => esc_attr__( 'not equal to', 'uncanny-automator' ),
				'<'  => esc_attr__( 'less than', 'uncanny-automator' ),
				'>'  => esc_attr__( 'greater than', 'uncanny-automator' ),
				'>=' => esc_attr__( 'greater or equal to', 'uncanny-automator' ),
				'<=' => esc_attr__( 'less or equal to', 'uncanny-automator' ),
			),
		);

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
			$label = esc_attr__( 'Redirect URL', 'uncanny-automator' );
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'url',
			'supports_tokens' => true,
			'required'        => true,
			'placeholder'     => 'https://',
		);

		return apply_filters( 'uap_option_get_redirect_url', $option );
	}

	/**
	 * @param array  $args
	 * @param bool   $add_any_option
	 * @param string $add_any_option_label
	 * @param bool   $is_all_label
	 *
	 * @return array
	 * @version 2.1.4 - changes made to pass  esc_attr__('All pages', 'uncanny-automator') as string instead of
	 * page, post, course etc
	 * @version 2.4 - Added transients
	 * @version 2.6 - Changed get_posts() to wpdb
	 *
	 * @version 1.0 - added
	 */
	public function wp_query( $args, $add_any_option = false, $add_any_option_label = null, $is_all_label = false ) {

		// set up a default label.
		if ( is_null( $add_any_option_label ) ) {
			$add_any_option_label = esc_attr__( 'Any page', 'uncanny-automator' );
		}

		// bail, if we aren't supposed to do this at all.
		if ( ! $this->load_helpers ) {
			return array();
		}

		// bail if no arguments are supplied.
		if ( empty( $args ) ) {
			return array();
		}

		/**
		 * Allow developers to modify $args
		 *
		 * @author  Saad
		 * @version 2.6
		 */
		$args = apply_filters( 'automator_wp_query_args', $args );

		extract( $args );

		// prepare transient key.
		$transient_key = apply_filters( 'automator_transient_name', 'uap_transient', $args );

		// suffix post type is needed.
		if ( isset( $args['post_type'] ) ) {
			$transient_key .= md5( json_encode( $args ) );
		}

		// attempt fetching options from transient.
		$options = apply_filters( 'automator_modify_transient_options', get_transient( $transient_key ), $args );

		// if meta query is set, its better to re-run query instead of transient
		if ( isset( $args['meta_query'] ) && ! empty( $args['meta_query'] ) ) {
			$options = array();
		}
		// if the transient is empty, generate options afresh.
		if ( empty( $options ) ) {
			// fetch all the posts.
			global $wpdb;
			if ( isset( $args['meta_query'] ) && ( isset( $args['meta_query']['relation'] ) || count( $args['meta_query'] ) > 1 ) ) {
				$posts = get_posts( $args );
			} else {
				$join = '';
				// basic query begins
				$query = "SELECT p.ID, p.post_title 
						FROM $wpdb->posts p";

				// check if there's meta query.. which means
				// we have to join postmeta table
				if ( isset( $meta_query ) ) {
					$mq         = array_shift( $meta_query );
					$meta_key   = sanitize_text_field( $mq['key'] );
					$meta_value = sanitize_text_field( $mq['value'] );
					$compare    = isset( $mq['compare'] ) ? $mq['compare'] : 'LIKE';
					$join       .= " 
									INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
									AND pm.meta_key = '{$meta_key}' AND pm.meta_value {$compare} '{$meta_value}'
								";
				}

				// Join tables
				$query .= $join;

				// basic where 1=1 so all other
				// where clauses can be joined via AND
				$query .= ' WHERE 1=1 ';

				// include post_type with fallback to publish
				if ( isset( $post_status ) ) {
					if ( ! empty( $post_status ) && ! is_array( $post_status ) ) {
						$query .= " AND p.post_status = '$post_status' ";
					} elseif ( ! empty( $post_status ) && is_array( $post_status ) ) {
						$comma_separated = implode( "','", $post_status );
						$comma_separated = "'" . $comma_separated . "'";
						$query           .= "AND p.post_status IN ({$comma_separated}) ";
					} else {
						$query .= " AND p.post_status = 'publish' ";
					}
				} else {
					$query .= " AND p.post_status = 'publish' ";
				}

				// filter by post_type with fallback to 'page' only
				if ( isset( $post_type ) && ! is_array( $post_type ) ) {
					$query .= " AND p.post_type = '{$post_type}'";
				} elseif ( isset( $post_type ) && is_array( $post_type ) ) {
					$comma_separated = implode( "','", $post_type );
					$comma_separated = "'" . $comma_separated . "'";
					$query           .= " AND p.post_type = '{$comma_separated}'";
				} else {
					$query .= " AND p.post_type = 'page'";
				}

				// order by provided argument, fallback to title
				if ( isset( $orderby ) && ! empty( $orderby ) ) {
					switch ( $orderby ) {
						case 'ID':
							$order_by = 'p.ID';
							break;
						case 'title':
						default:
							$order_by = 'p.post_title';
							break;

					}
					$query .= " ORDER BY $order_by";
				} else {
					$query .= ' ORDER BY p.post_title';
				}

				if ( isset( $order ) && empty( $order ) ) {
					$query .= " $order";
				} else {
					$query .= ' ASC';
				}

				if ( isset( $posts_per_page ) ) {
					$query .= " LIMIT 0, $posts_per_page";
				}

				//Utilities::log( [ $args, $query ], '', true, '$meta_query' );
				/**
				 * dropped get_posts() and used direct query to reduce load time
				 *
				 * @version 2.6
				 * @author  Saad
				 *
				 * @var  $query mysql query
				 * @var  $args  array of arguments passed to function
				 */
				$query = apply_filters( 'automator_maybe_modify_wp_query', $query, $args );

				$posts = $wpdb->get_results( $query );
			}
			// type set to array.
			$options = array();

			// if posts were found.
			if ( $posts ) {

				// loop through each post to set up individual options.
				foreach ( $posts as $post ) {
					$title = $post->post_title;

					// set up a descriptive title for posts with no title.
					if ( empty( $title ) ) {
						$title = sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $post->ID );
					}

					// add post as an option.
					$options[ $post->ID ] = $title;
				}

				// save fetched posts in a transient for 5 minutes for performance gains.
				/**
				 * Allow developers to modify transient times
				 *
				 * @author  Saad
				 * @version 2.6
				 */
				$transient_time = apply_filters( 'automator_transient_time', 5 * MINUTE_IN_SECONDS );
				set_transient( $transient_key, $options, $transient_time );
			}
		}

		// do we need to add an any/all posts option
		if ( $add_any_option ) {

			// get extra option.
			$any_option = $this->maybe_add_any_option( $add_any_option_label, $is_all_label );
			$options    = $any_option + $options;
		}

		return apply_filters( 'automator_modify_option_results', $options, $args );
	}

	/**
	 * @param string $limit
	 *
	 * @return array
	 * @author  Saad
	 * @version 2.6 - this function replaces wp's get_users()
	 */
	public function wp_users( $limit = '99999' ) {
		global $wpdb;
		// prepare transient key.
		$transient_key = 'uap_transient_users';

		// attempt fetching options from transient.
		$users = get_transient( $transient_key );
		if ( empty( $users ) ) {
			$query = apply_filters(
				'automator_get_users_query',
				"SELECT ID, display_name 
																FROM $wpdb->users 
																ORDER BY display_name ASC
																LIMIT 0, $limit"
			);
			$users = $wpdb->get_results( $query );

			// save fetched posts in a transient for 5 minutes for performance gains.
			set_transient( $transient_key, $users, 3 * MINUTE_IN_SECONDS );
		}

		return apply_filters( 'automator_modify_user_results', $users );
	}

	/**
	 * switch statement is for pre-v2.1.4
	 * default statement is v2.1.4+ in which
	 *  esc_attr__() is passed to $add_any_option_label
	 *
	 * @param $add_any_option_label
	 * @param $is_all_label
	 *
	 * @return mixed
	 */
	public function maybe_add_any_option( $add_any_option_label, $is_all_label ) {
		switch ( $add_any_option_label ) {
			case 'page':
			case 'pages':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All pages', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any page', 'uncanny-automator' );
				}
				break;
			case 'post':
			case 'posts':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All posts', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any post', 'uncanny-automator' );
				}
				break;
			case 'course':
			case 'courses':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All courses', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any course', 'uncanny-automator' );
				}
				break;
			case 'lesson':
			case 'lessons':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All lessons', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any lesson', 'uncanny-automator' );
				}
				break;
			case 'topic':
			case 'topics':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All topics', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any topic', 'uncanny-automator' );
				}
				break;
			case 'quiz':
			case 'quizzes':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All quizzes', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any quiz', 'uncanny-automator' );
				}
				break;
			case 'membership':
			case 'memberships':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All memberships', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any membership', 'uncanny-automator' );
				}
				break;
			case 'download':
			case 'downloads':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All downloads', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any download', 'uncanny-automator' );
				}
				break;
			case 'unit':
			case 'units':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All units', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any unit', 'uncanny-automator' );
				}
				break;
			case 'popup':
			case 'popups':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All popups', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any popup', 'uncanny-automator' );
				}
				break;
			case 'product':
			case 'products':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All products', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any product', 'uncanny-automator' );
				}
				break;
			case 'award':
			case 'awards':
				if ( $is_all_label ) {
					$options['-1'] = esc_attr__( 'All awards', 'uncanny-automator' );
				} else {
					$options['-1'] = esc_attr__( 'Any award', 'uncanny-automator' );
				}
				break;
			default:
				//fallback, assuming  esc_attr__() string is passed
				$options['-1'] = $add_any_option_label;
				break;
		}

		return $options;
	}

	/**
	 * Replacing frequently used helpers function/query to
	 * central so that it doesn't have to defined repeatedly
	 *
	 * @param      $meta_key
	 * @param      $trigger_id
	 * @param      $trigger_log_id
	 * @param null $user_id
	 *
	 * @return mixed|string
	 * @version 2.2
	 *
	 * @author  Saad
	 */
	public function get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id = null ) {
		if ( empty( $meta_key ) || empty( $trigger_id ) || empty( $trigger_log_id ) ) {
			return '';
		}
		global $wpdb;
		$qry        = $wpdb->prepare(
			"SELECT meta_value
														FROM {$wpdb->prefix}uap_trigger_log_meta
														WHERE 1 = 1
														AND user_id = %d
														AND meta_key = %s
														AND automator_trigger_id = %d
														AND automator_trigger_log_id = %d
														LIMIT 0,1",
			$user_id,
			$meta_key,
			$trigger_id,
			$trigger_log_id
		);
		$meta_value = $wpdb->get_var( $qry );
		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	public function maybe_load_trigger_options( $class = '' ) {
		if ( is_user_logged_in() && is_admin() && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
			$post    = get_post( $post_id );
			if ( $post && $post instanceof \WP_Post && 'uo-recipe' === $post->post_type ) {
				return apply_filters( 'automator_do_load_options', true, $class );
			}
		}

		return apply_filters( 'automator_do_load_options', false, $class );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function build_default_options_array( $label = 'Sample Label', $option_code = 'SAMPLE' ) {
		return apply_filters(
			'automator_default_options_array',
			array(
				'option_code' => $option_code,
				'label'       => $label,
				'input_type'  => '',
				'required'    => true,
				'options'     => array(),
			)
		);
	}

	/**
	 * Decode data coming from Automator API.
	 *
	 * @param string $message Original message string to decode.
	 * @param string $secret  Secret Key used for encription
	 *
	 * @return string|array
	 */
	public static function automator_api_decode_message( $message, $secret ) {
		$tokens = false;
		if ( ! empty( $message ) and ! empty( $secret ) ) {
			$message           = base64_decode( $message );
			$method            = 'AES128';
			$iv                = substr( $message, 0, 16 );
			$encrypted_message = substr( $message, 16 );
			$tokens            = openssl_decrypt( $encrypted_message, $method, $secret, 0, $iv );
			$tokens            = maybe_unserialize( $tokens );
		}

		return $tokens;
	}

}
