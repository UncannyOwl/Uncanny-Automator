<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Recipe_Helpers
 *
 * @package Uncanny_Automator
 */
#[\AllowDynamicProperties] // phpcs:ignore Uncanny_Automator.PHP.PHP80Features.Attribute
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
	 * @var Memberpress_Courses_Helpers
	 */
	public $memberpress_courses;
	/**
	 * @var Ninja_Forms_Helpers
	 */
	public $ninja_forms;
	/**
	 * @var Paid_Memberships_Pro_Helpers
	 */
	public $paid_memberships_pro;
	/**
	 * @var \Popup_Maker_Helpers
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
	 * @var Elementor_Helpers
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
	 * @var Presto_Helpers;
	 */
	public $presto;
	/**
	 * @var \Modern_Events_Calendar_Helpers;
	 */
	public $modern_events_calendar;
	/**
	 * @var Ameliabooking_Helpers;
	 */
	public $ameliabooking;
	/**
	 * @var Slack_Helpers
	 */
	public $slack;
	/**
	 * @var Facebook_Helpers;
	 */
	public $facebook;
	/**
	 * @var Facebook_Groups_Helpers;
	 */
	public $facebook_groups;
	/**
	 * @var Instagram_Helpers;
	 */
	public $instagram;
	/**
	 * @var Google_Sheet_Helpers
	 */
	public $google_sheet;
	/**
	 * @var Mailchimp_Helpers;
	 */
	public $mailchimp;
	/**
	 * @var Divi_Helpers;
	 */
	public $divi;
	/**
	 * @var Hubspot_Helpers
	 */
	public $hubspot;
	/**
	 * @var Zoom_Helpers;
	 */
	public $zoom;
	/**
	 * @var Zoom_Webinar_Helpers;
	 */
	public $zoom_webinar;
	/**
	 * @var Gototraining_Helpers;
	 */
	public $gototraining;
	/**
	 * @var Twilio_Helpers;
	 */
	public $twilio;
	/**
	 * @var Google_Calendar_Helpers
	 */
	public $google_calendar;
	/**
	 * @var Uncanny_Groups_Helpers;
	 */
	public $uncanny_groups;
	/**
	 * @var Automator_Helpers_Recipe
	 */
	public $options;
	/**
	 * @var PeepSo_Helpers
	 */
	public $peepso;
	/**
	 * @var Optinmonster_Helpers
	 */
	public $optinmonster;
	/**
	 * @var Advanced_Coupons_Helpers
	 */
	public $advanced_coupons;
	/*
	 * Check for loading options.
	 */
	/**
	 * @var bool
	 */
	public $load_helpers = false;

	/**
	 * @var mixed $active_campaign
	 */
	public $active_campaign;

	/**
	 * @var mixed $clickup
	 */
	public $clickup;

	/**
	 * @var mixed $convertkit
	 */
	public $convertkit;

	/**
	 * @var mixed $drip
	 */
	public $drip;

	/**
	 * @var mixed $emails
	 */
	public $emails;

	/**
	 * @var mixed $gotowebinar
	 */
	public $gotowebinar;

	/**
	 * @var mixed $helpscout
	 */
	public $helpscout;

	/**
	 * @var mixed $integromat
	 */
	public $integromat;

	/**
	 * @var mixed $linkedin
	 */
	public $linkedin;

	/**
	 * @var mixed $open_ai
	 */
	public $open_ai;

	/**
	 * @var mixed $telegram
	 */
	public $telegram;

	/**
	 * @var mixed $trello
	 */
	public $trello;

	/**
	 * @var mixed $whatsapp
	 */
	public $whatsapp;

	/**
	 * @var mixed $zoho_campaigns
	 */
	public $zoho_campaigns;

	/**
	 * Automator_Helpers_Recipe constructor.
	 */
	public function __construct() {

		$this->field = new Automator_Helpers_Recipe_Field();

		add_action( 'automator_add_integration_helpers', array( $this, 'load_helpers_for_recipes' ) );

		if ( $this->is_edit_page() || $this->is_automator_ajax() ) {
			$this->load_helpers = true;
		}
	}

	/**
	 * is_edit_page
	 * function to check if the current page is a post edit page
	 *
	 * @return boolean
	 */
	public function is_edit_page() {
		// Allow override via constant
		if ( defined( 'AUTOMATOR_FORCE_EDIT_PAGE' ) && AUTOMATOR_FORCE_EDIT_PAGE ) {
			return true;
		}

		// Early return if not admin
		if ( ! is_admin() ) {
			return false;
		}

		// Check if we have a post parameter
		if ( ! automator_filter_has_var( 'post' ) || empty( automator_filter_input( 'post' ) ) ) {
			return false;
		}

		// Get current post and validate
		$current_post = get_post( absint( automator_filter_input( 'post' ) ) );
		if ( ! $current_post || AUTOMATOR_POST_TYPE_RECIPE !== $current_post->post_type ) {
			return false;
		}

		// Check if we're on the correct page
		global $pagenow;
		$valid_pages = array( 'post.php', 'post-new.php' );
		return in_array( $pagenow, $valid_pages, true );
	}

	/**
	 * @return bool
	 * @version 3.0 Automator will load on ajax calls as well
	 */
	public function is_automator_ajax() {

		if ( ! $this->is_ajax() && ! $this->is_rest() ) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function is_ajax() {
		return function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : defined( 'DOING_AJAX' );
	}

	/**
	 * Check if the current request is a REST API request.
	 *
	 * Detects REST API requests for both plain and pretty permalink structures.
	 * Supports WordPress core endpoints and Automator custom namespaces.
	 *
	 * @return bool True if this is a REST API request, false otherwise.
	 */
	public function is_rest() {
		// Check if WordPress has already identified this as a REST request
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// Check for plain permalink structure: ?rest_route=/namespace/endpoint
		if ( $this->is_rest_route_parameter() ) {
			return true;
		}

		// Check for pretty permalink structure: /wp-json/namespace/endpoint
		return $this->is_rest_url_path();
	}

	/**
	 * Check if the rest_route parameter indicates a REST request.
	 *
	 * @return bool
	 */
	private function is_rest_route_parameter() {
		if ( ! automator_filter_has_var( 'rest_route' ) ) {
			return false;
		}

		$route = trim( automator_filter_input( 'rest_route' ), '\\/' );

		$valid_prefixes = array(
			rest_get_url_prefix(),          // wp-json
			AUTOMATOR_REST_API_END_POINT,   // uap/v2
			'automator/v1',                 // automator/v1
		);

		foreach ( $valid_prefixes as $prefix ) {
			if ( 0 === strpos( $route, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the current URL path indicates a REST request.
	 *
	 * @return bool
	 */
	private function is_rest_url_path() {
		$current_url = wp_parse_url( add_query_arg( array() ) );
		$path        = isset( $current_url['path'] ) ? $current_url['path'] : '';

		if ( empty( $path ) ) {
			return false;
		}

		$rest_prefix          = rest_get_url_prefix(); // wp-json
		$automator_namespaces = array(
			str_replace( '/', '\/', AUTOMATOR_REST_API_END_POINT ), // uap\/v2
			'automator\/v1',
		);

		$pattern = '/\/' . $rest_prefix . '\/(' . implode( '|', $automator_namespaces ) . ').+/';

		return (bool) preg_match( $pattern, $path );
	}

	/**
	 * Is it a valid endpoint to return query and return tokens
	 * @return bool
	 */
	public function is_valid_token_endpoint() {

		// If it's not a valid rest call, return
		if ( ! $this->is_rest() ) {
			return false;
		}

		//      $current_url = wp_parse_url( add_query_arg( array() ) );
		//      $match       = isset( $current_url['path'] ) ? $current_url['path'] : '';
		//
		//      $valid_endpoints = array(
		//          'add',
		//          'change_post_status',
		//          'user-selector',
		//          'update',
		//          'schedule_actions',
		//          'actions_conditions_update',
		//      );
		//
		//      return in_array( basename( $match ), $valid_endpoints, true );
		return true;
	}

	/**
	 * Check if a given action or trigger code is active in any published recipe.
	 *
	 * @param string $code The action or trigger code (e.g. "SHOW_CAMPAIGN").
	 *
	 * @return bool True if active in at least one published recipe, false otherwise.
	 */
	public function is_action_or_trigger_active( $code ) {

		// Use the manifest for O(1) lookup when available.
		$manifest      = Recipe_Manifest::get_instance();
		$manifest_data = $manifest->get();

		if ( ! empty( $manifest_data ) ) {
			// Build a reverse lookup on first access: bare_code => true.
			static $bare_code_lookup = null;
			static $bare_code_source = null;

			// Rebuild if the manifest data changed (e.g. after rebuild).
			if ( null === $bare_code_lookup || $bare_code_source !== $manifest_data ) {
				$bare_code_lookup = array();
				foreach ( $manifest_data as $composite_key => $integration_code ) {
					$bare                      = substr( $composite_key, strlen( $integration_code ) + 1 );
					$bare_code_lookup[ $bare ] = true;
				}
				$bare_code_source = $manifest_data;
			}

			return isset( $bare_code_lookup[ $code ] );
		}

		// Fallback: direct DB query if manifest is not yet built.
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p
					ON pm.post_id = p.ID
				WHERE p.post_type IN ( %s, %s )
					AND p.post_status = 'publish'
					AND pm.meta_key = 'code'
					AND pm.meta_value = %s
					AND p.post_parent IN (
						SELECT ID FROM {$wpdb->posts}
						WHERE post_type = %s
						AND post_status = 'publish'
					)
				LIMIT 1",
				AUTOMATOR_POST_TYPE_ACTION,
				AUTOMATOR_POST_TYPE_TRIGGER,
				$code,
				AUTOMATOR_POST_TYPE_RECIPE
			)
		);

		return null !== $result;
	}

	/**
	 * Decode data coming from Automator API.
	 *
	 * @param string $message Original message string to decode.
	 * @param string $secret Secret Key used for decryption (often generated from wp_create_nonce).
	 *
	 * @return string|array|false
	 */
	public static function automator_api_decode_message( $message, $secret ) {
		// Bail early if message or secret is empty
		if ( empty( $message ) || empty( $secret ) ) {
			return false;
		}

		// Decode the base64-encoded message
		$message = base64_decode( $message ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		// Specify the encryption algorithm (AES-128)
		$method = 'AES128';

		// Extract the Initialization Vector (IV) from the first 16 bytes of the message
		$iv = substr( $message, 0, 16 );

		// Extract the actual encrypted content (everything after the IV)
		$encrypted_message = substr( $message, 16 );

		// Decrypt the message using the provided secret as the key
		$tokens = openssl_decrypt( $encrypted_message, $method, $secret, 0, $iv );

		// Convert the JSON string result to an associative array
		$tokens = json_decode( $tokens, true );

		// If JSON decoding failed, return false
		if ( null === $tokens || JSON_ERROR_NONE !== json_last_error() ) {
			return false;
		}

		return $tokens;
	}

	/**
	 * Encrypt any outgoing data.
	 *
	 * @param array $args The message to decrypt.
	 * @param string $secret The secret key to pass for decoding.
	 *
	 * @return string The encrypted data.
	 */
	public static function encrypt( $args, $secret ) {
		$message_to_encrypt = serialize( $args ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$method             = 'AES128';
		$ivlen              = openssl_cipher_iv_length( $method );
		$iv                 = openssl_random_pseudo_bytes( $ivlen );
		$encrypted_message  = openssl_encrypt( $message_to_encrypt, $method, $secret, 0, $iv );
		$encrypted_message  = base64_encode( $iv . $encrypted_message ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return urlencode( $encrypted_message ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
	}


	/**
	 * @param mixed $options
	 */
	public function setOptions( $options ) {
		$this->options = $options;
	}

	/**
	 * @version 2.1.0
	 *
	 * This is purely to give developers access to Helper methods
	 * in IDEs. We could just use loop of $options and load everything
	 * in a loop, but manually assigning some common integrations
	 * i.e., Automator()->helpers->learndash->xyz() will
	 * list all helper functions of LearnDash.
	 */
	public function load_helpers_for_recipes() {

		$helpers = Utilities::automator_get_all_helper_instances();
		if ( $helpers ) {
			foreach ( $helpers as $integration => $class ) {
				if ( isset( $this->$integration ) && $this->$integration instanceof $class ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
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
			'option_code'            => 'NUMTIMES',
			'label'                  => $label,
			'show_label_in_sentence' => false,
			'description'            => $description,
			'placeholder'            => $placeholder,
			'input_type'             => 'int',
			'default_value'          => 1,
			'min_number'             => 1,
			'required'               => true,
		);

		$option = apply_filters_deprecated( 'uap_option_number_of_times', array( $option ), '3.0', 'automator_option_number_of_times' );

		return apply_filters( 'automator_option_number_of_times', $option );
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
			'options'     => array(
				'='  => esc_attr__( 'equal to', 'uncanny-automator' ),
				'!=' => esc_attr__( 'not equal to', 'uncanny-automator' ),
				'<'  => esc_attr__( 'less than', 'uncanny-automator' ),
				'>'  => esc_attr__( 'greater than', 'uncanny-automator' ),
				'>=' => esc_attr__( 'greater or equal to', 'uncanny-automator' ),
				'<=' => esc_attr__( 'less or equal to', 'uncanny-automator' ),
			),
		);

		$option = apply_filters_deprecated( 'uap_option_less_or_greater_than', array( $option ), '3.0', 'automator_option_less_or_greater_than' );

		return apply_filters( 'automator_option_less_or_greater_than', $option );
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

		$option = apply_filters_deprecated( 'uap_option_get_redirect_url', array( $option ), '3.0', 'automator_option_get_redirect_url' );

		return apply_filters( 'automator_option_get_redirect_url', $option );
	}

	/**
	 * @param array $args
	 * @param bool $add_any_option
	 * @param string $add_any_option_label
	 * @param bool $is_all_label
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

		// Allow automator to load this wp_query results from MCP requests. Backwards compatibility.
		$is_mcp = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/automator/v1/mcp' );

		if ( ! $this->load_helpers && ! $is_mcp ) {
			return array();
		}

		if ( empty( $args ) ) {
			return array();
		}

		/** @var array $args Allow developers to modify $args. */
		$args = apply_filters( 'automator_wp_query_args', $args );

		// Translate legacy positional params into modern array params.
		$params = $args;

		if ( $add_any_option ) {
			if ( $is_all_label ) {
				$params['include_all'] = true;
				$params['all_label']   = $this->resolve_any_option_label( $add_any_option_label, true );
			} else {
				$params['include_any'] = true;
				$params['any_label']   = $this->resolve_any_option_label( $add_any_option_label, false );
			}
		}

		$options = automator_wp_query( $params, 'legacy' );

		return apply_filters( 'automator_modify_option_results', $options, $args );
	}

	/**
	 * Resolve the Any/All label from legacy switch-case logic.
	 *
	 * @param string|null $label       The label passed to wp_query.
	 * @param bool        $is_all      Whether this is an "All" label.
	 *
	 * @return string
	 */
	private function resolve_any_option_label( $label, $is_all ) {

		if ( null === $label ) {
			$label = $is_all
				? esc_html_x( 'All', 'Uncanny Automator', 'uncanny-automator' )
				: esc_html_x( 'Any page', 'Uncanny Automator', 'uncanny-automator' );
		}

		// If the label resolves via the legacy maybe_add_any_option switch, use it.
		$resolved = $this->maybe_add_any_option( $label, $is_all );

		// maybe_add_any_option returns [ '-1' => 'label' ].
		return isset( $resolved['-1'] ) ? $resolved['-1'] : $label;
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

		return apply_filters( 'automator_add_any_option_label', $options, $add_any_option_label, $is_all_label );
	}

	/**
	 * @param string $limit
	 *
	 * @return array
	 * @version 2.6 - this function replaces wp's get_users()
	 * @author  Saad
	 */
	public function wp_users( $limit = 99999 ) {
		global $wpdb;
		// prepare transient key.
		$transient_key = 'automator_transient_users';
		// attempt fetching options from transient.
		$users = Automator()->cache->get( 'uap_transient_users' );
		if ( empty( $users ) ) {
			$query = apply_filters(
				'automator_get_users_query',
				"SELECT ID, display_name
					FROM $wpdb->users
					ORDER BY display_name
					LIMIT 0, $limit"
			);
			$users = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// save fetched posts in a transient for 3 minutes for performance gains.
			$expiration_time = apply_filters( 'automator_get_users_expiry_time', Automator()->cache->expires, $users );
			Automator()->cache->set( $transient_key, $users, 'automator', $expiration_time );
		}

		return apply_filters( 'automator_modify_user_results', $users );
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
	 * @deprecated 3.0 Use Use Automator()->db->trigger->get_token_meta()
	 */
	public function get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id = null ) {
		if ( empty( $meta_key ) || empty( $trigger_id ) || empty( $trigger_log_id ) ) {
			return '';
		}
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( '$uncanny_automator->helpers->recipe->get_form_data_from_trigger_meta()', 'Use Automator()->db->trigger->get_token_meta()', '3.0' );
		}
		$parse_tokens = array(
			'trigger_id'     => $trigger_id,
			'trigger_log_id' => $trigger_log_id,
			'user_id'        => $user_id,
		);

		return Automator()->db->trigger->get_token_meta( $meta_key, $parse_tokens );
	}

	/**
	 * @param string $class
	 *
	 * @return bool
	 */
	public function maybe_load_trigger_options( $class_name = '' ) {
		if ( is_admin() && automator_filter_has_var( 'action' ) && 'edit' === automator_filter_input( 'action' ) && automator_filter_has_var( 'post' ) ) {
			$post_id = absint( automator_filter_input( 'post' ) );
			$post    = get_post( $post_id );
			if ( $post && $post instanceof \WP_Post && AUTOMATOR_POST_TYPE_RECIPE === $post->post_type ) {
				return apply_filters( 'automator_do_load_options', true, $class_name );
			}
		}

		return apply_filters( 'automator_do_load_options', false, $class_name );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function build_default_options_array( $label = 'Sample label', $option_code = 'SAMPLE' ) {
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
	 * action_is_finished
	 *
	 * @param array $action
	 *
	 * @return bool
	 */
	public function action_is_finished( $action ) {

		if ( empty( $action['action_data']['completed'] ) ) {
			return false;
		}

		$action_status = (int) $action['action_data']['completed'];

		return \Uncanny_Automator\Automator_Status::finished( $action_status );
	}

	/**
	 * Sets the properties of a Trigger that will be displayed in the logs.
	 *
	 * @param array{array{type:string,label:string,content:string,code_language:string}} $properties_args The key `code_language` is optional. Only needed for non-text `type`.
	 * @param string $type Defaults to 'action'.
	 *
	 * @return array{array{type:string,label:string,content:string,code_language:string}} Returns mixed array of the properties args.
	 */
	public function set_trigger_log_properties( $properties_args ) {

		return $this->set_log_properties( $properties_args, 'trigger' );
	}

	/**
	 * Sets the properties of an action that will be displayed in the logs.
	 *
	 * @param array{array{type:string,label:string,content:string,code_language:string}} $properties_args The key `code_language` is optional. Only needed for non-text `type`.
	 * @param string $type Defaults to 'action'.
	 *
	 * @return array{array{type:string,label:string,content:string,code_language:string}} Returns mixed array of the properties args.
	 */
	public function set_log_properties( $properties_args = array(), $type = 'action' ) {

		$properties = new Services\Properties();

		foreach ( (array) $properties_args as $property_arg ) {

			$props = wp_parse_args(
				$property_arg,
				array(
					'type'       => '',
					'label'      => '',
					'value'      => '',
					'attributes' => array(),
				)
			);

			if ( empty( $props['type'] ) || empty( $props['label'] || empty( $props['value'] ) ) ) {
				continue; // Skip. The "type", "label", and "value" are required.
			}

			$properties->add_item( $props );

		}

		if ( 'action' === $type ) {
			$properties->dispatch();
		}

		if ( 'trigger' === $type ) {
			$properties->dispatch_trigger();
		}

		return $properties;
	}
}
