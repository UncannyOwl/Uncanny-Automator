<?php
/**
 * @class   Admin_Review
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */


namespace Uncanny_Automator;

use WP_REST_Response;

/**
 * Class Automator_Review
 *
 * @package Uncanny_Automator
 */
class Automator_Review {

	/**
	 * Automator_Review constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_ask_review' ) );
		add_action( 'admin_init', array( $this, 'maybe_ask_tracking' ) );
		add_action( 'init', array( $this, 'save_review_settings_action' ) );
		add_action( 'rest_api_init', array( $this, 'uo_register_api_for_reviews' ) );
	}

	/**
	 * Register rest api calls for misc tasks.
	 *
	 * @since 2.1.0
	 */
	public function uo_register_api_for_reviews() {
		global $wpdb;
		$check_closure = $wpdb->get_col( "SELECT cp.ID as ID FROM {$wpdb->posts} cp LEFT JOIN {$wpdb->posts} rp ON rp.ID = cp.post_parent WHERE cp.post_type LIKE 'uo-closure' AND cp.post_status LIKE 'publish' AND rp.post_status LIKE 'publish' LIMIT 1" );
		if ( ! empty( $check_closure ) ) {
			register_rest_route(
				AUTOMATOR_REST_API_END_POINT,
				'/uoa_redirect/',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'send_feedback' ),
					'permission_callback' => function () {
						return true;
					},
				)
			);
		}

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/review-banner-visibility/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_review_settings' ),
				'permission_callback' => function () {
					if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
						return true;
					}

					return false;
				},
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/get-credits/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_credits' ),
				'permission_callback' => function () {
					if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
						return true;
					}

					return false;
				},
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/get-recipes-using-credits/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_recipes_using_credits' ),
				'permission_callback' => function () {
					if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
						return true;
					}

					return false;
				},
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/allow-tracking-switch/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_tracking_settings' ),
				'permission_callback' => function () {
					if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
						return true;
					}

					return false;
				},
			)
		);
	}

	/**
	 * Rest api callbacks for redirects.
	 *
	 * @since 2.1.0
	 */
	public function send_feedback( $request ) {
		// check if its a valid request.
		$data = $request->get_params();
		if ( isset( $data['user_id'] ) && isset( $data['client_secret'] ) && $data['client_secret'] == md5( 'l6fsX3vAAiJbSXticLBd' . $data['user_id'] ) ) {
			$user_id      = $data['user_id'];
			$redirect_url = get_option( 'UO_REDIRECTURL_' . $user_id, '' );
			// Send a simple message at random intervals.
			if ( ! empty( $redirect_url ) ) {
				delete_option( 'UO_REDIRECTURL_' . $user_id );

				return new WP_REST_Response( array( 'redirect_url' => $redirect_url ), 201 );
			}
		}

		return new WP_REST_Response( array( 'redirect_url' => '' ), 201 );
	}

	/**
	 * Admin notice for review this plugin.
	 *
	 * @since 2.1.4
	 */
	public function maybe_ask_review() {

		// check plugin install date
		$review_time = get_option( '_uncanny_automator_review_time', '' );

		if ( empty( $review_time ) ) {
			$review_time = current_time( 'timestamp' );
			update_option( '_uncanny_automator_review_time', $review_time );
		}

		$current_date = current_time( 'timestamp' );
		$days_after   = 10;
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ceil( ( $current_date - $review_time ) / 86400 ) > $days_after ) {

			$_is_reminder   = get_option( '_uncanny_automator_review_reminder', '' );
			$_reminder_date = get_option( '_uncanny_automator_review_reminder_date', current_time( 'timestamp' ) );

			if ( ! empty( $_is_reminder ) && 'hide-forever' === $_is_reminder ) {
				return;
			}

			if ( ! empty( $_is_reminder ) && 'maybe-later' === $_is_reminder ) {
				// check reminder date difference
				if ( ceil( ( $current_date - $_reminder_date ) / 86400 ) < $days_after ) {
					return;
				}
			}

			$_previous_display_date = get_option( '_uncanny_automator_previous_display_date', '' );

			if ( ! empty( $_previous_display_date ) ) {
				$_previous_display_date = strtotime( $_previous_display_date );
				$current_date           = strtotime( date( 'Y-m-d', current_time( 'timestamp' ) ) );
				if ( $_previous_display_date != $current_date && ceil( ( $current_date - $_previous_display_date ) / 86400 ) < 3 ) {
					return;
				}
			}

			add_action(
				'admin_notices',
				function () {

					// Check only Automator related pages.
					global $typenow;

					if ( empty( $typenow ) || 'uo-recipe' !== $typenow ) {
						return;
					}

					$screen = get_current_screen();

					if ( $screen->base === 'post' ) {
						return;
					}

					update_option( '_uncanny_automator_previous_display_date', date( 'Y-m-d', current_time( 'timestamp' ) ) );
					// Get data about Automator's version
					$is_pro  = false;
					$version = AUTOMATOR_PLUGIN_VERSION;
					if ( defined( 'AUTOMATOR_PRO_FILE' ) || class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
						$is_pro  = true;
						$version = \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION;
					}

					// Send review URL
					$url_send_review = 'https://wordpress.org/support/plugin/uncanny-automator/reviews/?filter=5#new-post';

					// Send feedback URL
					$url_send_feedback_version = $is_pro ? 'Uncanny%20Automator%20Pro%20' . $version : 'Uncanny%20Automator%20' . $version;
					$url_send_feedback_source  = $is_pro ? 'uncanny_automator_pro' : 'uncanny_automator';
					$url_send_feedback         = 'https://automatorplugin.com/feedback/?version=' . $url_send_feedback_version . '&utm_source=' . $url_send_feedback_source . '&utm_medium=review_banner';
					$url_hide_forever          = add_query_arg( array( 'action' => 'uo-hide-forever' ) );
					$url_remind_later          = add_query_arg( array( 'action' => 'uo-maybe-later' ) );
					include Utilities::automator_get_view( 'review-banner.php' );
				}
			);
		}
	}

	/**
	 * Rest API callback for saving user selection for review.
	 *
	 * @param object $request
	 *
	 * @return object
	 * @since 2.1.4
	 */
	public function save_review_settings( $request ) {
		// check if its a valid request.
		$data = $request->get_params();
		if ( isset( $data['action'] ) && ( 'maybe-later' === $data['action'] || 'hide-forever' === $data['action'] ) ) {
			update_option( '_uncanny_automator_review_reminder', $data['action'] );
			update_option( '_uncanny_automator_review_reminder_date', current_time( 'timestamp' ) );

			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		return new WP_REST_Response( array( 'success' => false ), 200 );
	}

	/**
	 * Callback for saving user selection for review by querystring.
	 *
	 * @param object $request
	 *
	 * @since 2.11
	 */
	public function save_review_settings_action() {
		// check if its a valid request.
		if ( automator_filter_has_var( 'action' ) && ( 'uo-maybe-later' === automator_filter_input( 'action' ) || 'uo-hide-forever' === automator_filter_input( 'action' ) ) ) {
			if ( function_exists( 'is_admin' ) && is_admin() ) {
				$_action = str_replace( 'uo-', '', automator_filter_input( 'action' ) );

				update_option( '_uncanny_automator_review_reminder', $_action );
				update_option( '_uncanny_automator_review_reminder_date', current_time( 'timestamp' ) );
				$back_url = remove_query_arg( 'action' );
				wp_safe_redirect( $back_url );
				die;
			}
		}
		if ( automator_filter_has_var( 'action' ) && 'uo-allow-tracking' === automator_filter_input( 'action' ) ) {
			if ( function_exists( 'is_admin' ) && is_admin() ) {
				update_option( 'automator_reporting', true );
				update_option( '_uncanny_automator_tracking_reminder', 'hide-forever' );

				$back_url = remove_query_arg( 'action' );
				wp_safe_redirect( $back_url );
				die;
			}
		}
		if ( automator_filter_has_var( 'action' ) && 'uo-hide-track' === automator_filter_input( 'action' ) ) {
			if ( function_exists( 'is_admin' ) && is_admin() ) {

				update_option( '_uncanny_automator_tracking_reminder', 'hide-forever' );

				$back_url = remove_query_arg( 'action' );
				wp_safe_redirect( $back_url );
				die;
			}
		}

	}

	/**
	 * Callback for getting api credits.
	 *
	 * @param object $request
	 *
	 * @return object
	 * @since 3.1
	 */
	public function get_credits() {
		// The rest response object
		$response = (object) array();

		// Default return message
		$response->message  = __( 'Information is missing.', 'automator-plugin' );
		$response->success  = true;
		$have_valid_licence = false;
		$licence_key        = '';
		$item_name          = '';
		$count              = 0;
		$existing_data      = get_transient( 'automator_api_credits' );
		if ( ! empty( $existing_data ) ) {
			if ( is_array( $existing_data ) ) {
				$existing_data = (object) $existing_data;
			}
			$response->credits_left  = $existing_data->data->usage_limit - $existing_data->data->paid_usage_count;
			$response->total_credits = $existing_data->data->usage_limit;

			return new \WP_REST_Response( $response, 200 );
		}

		if ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === get_option( 'uap_automator_pro_license_status' ) ) {
			$licence_key = get_option( 'uap_automator_pro_license_key' );
			$item_name   = defined( 'AUTOMATOR_PRO_ITEM_NAME' ) ? AUTOMATOR_PRO_ITEM_NAME : AUTOMATOR_AUTOMATOR_PRO_ITEM_NAME;
		} elseif ( 'valid' === get_option( 'uap_automator_free_license_status' ) ) {
			$licence_key = get_option( 'uap_automator_free_license_key' );
			$item_name   = AUTOMATOR_FREE_ITEM_NAME;
		}

		if ( empty( $licence_key ) ) {
			$response->credits_left  = 0;
			$response->total_credits = 0;
			$response                = new \WP_REST_Response( $response, 200 );

			return $response;
		}

		$website = preg_replace( '(^https?://)', '', get_home_url() );

		// data to send in our API request
		$api_params = array(
			'action'      => 'get_credits',
			'license_key' => $licence_key,
			'site_name'   => $website,
			'item_name'   => $item_name,
			'api_ver'     => '2.0',
			'plugins'     => defined( 'AUTOMATOR_PRO_FILE' ) ? \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION : AUTOMATOR_PLUGIN_VERSION,
		);

		// Call the custom API.
		$api_response = wp_remote_post(
			AUTOMATOR_API_URL . 'v2/credits',
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
			)
		);
		if ( is_wp_error( $api_response ) ) {
			$response->credits_left  = 0;
			$response->total_credits = 0;

			return new \WP_REST_Response( $response, 200 );
		}

		$credit_data = json_decode( wp_remote_retrieve_body( $api_response ) );
		if ( 200 === $credit_data->statusCode ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$response->credits_left  = $credit_data->data->usage_limit - $credit_data->data->paid_usage_count;
			$response->total_credits = $credit_data->data->usage_limit;
			set_transient( 'automator_api_credits', $credit_data, HOUR_IN_SECONDS );
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Callback for getting recipes using api credits.
	 *
	 * @param object $request
	 *
	 * @return object
	 * @since 3.1
	 */
	public function get_recipes_using_credits() {
		global $wpdb;
		$integration_codes = array( 'GOOGLESHEET', 'SLACK', 'MAILCHIMP', 'TWITTER', 'FACEBOOK', 'INSTAGRAM', 'HUBSPOT', 'ACTIVE_CAMPAIGN', 'TWILIO' );

		$where_meta = array();
		foreach ( $integration_codes as $code ) {
			$where_meta[] = " `meta_value` LIKE '{$code}' ";
		}

		$meta          = implode( ' OR ', $where_meta );
		$check_recipes = $wpdb->get_col( $wpdb->prepare( "SELECT rp.ID as ID FROM $wpdb->posts cp LEFT JOIN $wpdb->posts rp ON rp.ID = cp.post_parent WHERE cp.ID IN ( SELECT post_id FROM $wpdb->postmeta WHERE $meta ) AND cp.post_status LIKE %s AND rp.post_status LIKE %s", 'publish', 'publish' ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// The rest response object
		$response = (object) array();

		$response->success = true;

		$recipes = array();
		if ( ! empty( $check_recipes ) ) {
			foreach ( $check_recipes as $recipe_id ) {
				// Get the title
				$recipe_title = get_the_title( $recipe_id );
				$recipe_title = ! empty( $recipe_title ) ? $recipe_title : sprintf( __( 'ID: %s (no title)', 'uncanny-automator' ), $recipe_id );

				// Get the URL
				$recipe_edit_url = get_edit_post_link( $recipe_id );

				// Get the recipe type
				$recipe_type = Automator()->utilities->get_recipe_type( $recipe_id );

				// Get the times per user
				$recipe_times_per_user = '';
				if ( $recipe_type == 'user' ) {
					$recipe_times_per_user = get_post_meta( $recipe_id, 'recipe_completions_allowed', true );
				}

				// Get the total allowed completions
				$recipe_allowed_completions_total = get_post_meta( $recipe_id, 'recipe_max_completions_allowed', true );

				// Get the number of runs
				$recipe_number_of_runs = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(run_number) FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id=%d AND completed = %d", $recipe_id, 1 ) );

				$recipes[] = array(
					'id'                        => $recipe_id,
					'title'                     => $recipe_title,
					'url'                       => $recipe_edit_url,
					'type'                      => $recipe_type,
					'times_per_user'            => $recipe_times_per_user,
					'allowed_completions_total' => $recipe_allowed_completions_total,
					'completed_runs'            => $recipe_number_of_runs,
				);
			}
		}

		$response->recipes = $recipes;

		$response = new \WP_REST_Response( $response, 200 );

		return $response;
	}

	/**
	 * Rest API callback for saving user selection for review.
	 *
	 * @param object $request
	 *
	 * @return object
	 * @since 2.1.4
	 */
	public function save_tracking_settings( $request ) {
		// check if its a valid request.
		$data = $request->get_params();
		if ( isset( $data['action'] ) && 'tracking-settings' === $data['action'] ) {
			if ( 'true' === $data['swtich'] ) {
				update_option( 'automator_reporting', true );
			} else {
				delete_option( 'automator_reporting' );
			}

			if ( isset( $data['hide'] ) ) {
				update_option( '_uncanny_automator_tracking_reminder', 'hide-forever' );
			}

			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		return new WP_REST_Response( array( 'success' => false ), 200 );
	}

	/**
	 * Admin notice for review this plugin.
	 *
	 * @since 2.1.4
	 */
	public function maybe_ask_tracking() {
		$_is_reminder   = get_option( '_uncanny_automator_tracking_reminder', '' );
		$_reminder_date = get_option( '_uncanny_automator_tracking_reminder_date', current_time( 'timestamp' ) );

		if ( ! empty( $_is_reminder ) && 'hide-forever' === $_is_reminder ) {
			return;
		}

		$automator_reporting = get_option( 'automator_reporting', false );

		if ( $automator_reporting ) {
			return;
		}
		add_action(
			'admin_notices',
			function () {

				// Check only Automator related pages.
				global $typenow;

				if ( empty( $typenow ) || 'uo-recipe' !== $typenow ) {
					return;
				}

				$screen = get_current_screen();

				if ( $screen->base === 'post' ) {
					return;
				}

				// Get data about Automator's version
				$is_pro  = false;
				$version = AUTOMATOR_PLUGIN_VERSION;
				if ( defined( 'AUTOMATOR_PRO_FILE' ) || class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
					$is_pro  = true;
					$version = \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION;
				}

				if ( $is_pro ) {
					return;
				}

				// Send review URL
				$url_send_review = add_query_arg( array( 'action' => 'uo-allow-tracking' ) );

				// Send feedback URL
				$url_send_feedback_version = $is_pro ? 'Uncanny%20Automator%20Pro%20' . $version : 'Uncanny%20Automator%20' . $version;
				$url_send_feedback_source  = $is_pro ? 'uncanny_automator_pro' : 'uncanny_automator';
				$url_remind_later          = add_query_arg( array( 'action' => 'uo-hide-track' ) );
				include Utilities::automator_get_view( 'tracking-banner.php' );
			}
		);

	}
}
