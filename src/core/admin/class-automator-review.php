<?php
/**
 * @class   Admin_Review
 * @since   3.0
 * @version 4.2
 * @author  Saad S.
 * @package Uncanny_Automator
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
	 *
	 */
	const REVIEW_BANNER_TMP_NUM_DAYS = 10;

	/**
	 *
	 */
	public function __construct() {

		add_action( 'admin_init', array( $this, 'maybe_ask_review' ) );

		add_action( 'admin_init', array( $this, 'maybe_ask_tracking' ) );

		add_action( 'init', array( $this, 'save_review_settings_action' ) );

		add_action( 'rest_api_init', array( $this, 'uo_register_api_for_reviews' ) );

		add_action( 'wp_ajax_automator_handle_feedback', array( $this, 'handle_feedback' ) );

	}

	/**
	 * @return void
	 */
	public function handle_feedback() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'feedback_banner' ) ) {

			wp_die( 'Unauthorized. Error invalid nonce.' );

		}

		$type = automator_filter_input( 'type' );

		$redirect_url = automator_filter_input( 'redirect_url' );

		update_option( '_uncanny_automator_review_reminder', $type );

		update_option( '_uncanny_automator_review_reminder_date', strtotime( current_time( 'mysql' ) ) );

		if ( ! empty( $redirect_url ) ) {

			wp_redirect( $redirect_url ); //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

			exit;

		}

		wp_redirect( wp_get_referer() ); //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

		exit;

	}

	/**
	 * Register rest api calls for misc tasks.
	 *
	 * @since 2.1.0
	 */
	public function uo_register_api_for_reviews() {

		$check_closure = Automator()->db->closure->get_all();
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
			'/get-credits/',
			array(
				'methods'             => 'POST, GET',
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

		$data = $request->get_params();

		if ( isset( $data['user_id'] ) && isset( $data['client_secret'] ) && md5( 'l6fsX3vAAiJbSXticLBd' . $data['user_id'] ) === (string) $data['client_secret'] ) {

			$user_id = $data['user_id'];

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
		$response->message       = __( 'Information is missing.', 'automator-plugin' );
		$response->success       = true;
		$response->credits_left  = 0;
		$response->total_credits = 0;
		$existing_data           = Api_Server::is_automator_connected();

		if ( empty( $existing_data ) ) {
			return new \WP_REST_Response( $response, 200 );
		}

		$response->credits_left = $existing_data['usage_limit'] - $existing_data['paid_usage_count'];

		$response->total_credits = $existing_data['usage_limit'];

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
		// The rest response object
		$response = (object) array();

		$response->success = true;

		$response->recipes = Automator()->get->recipes_using_credits();

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

		$_is_reminder = get_option( '_uncanny_automator_tracking_reminder', '' );

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

	/**
	 * Callback method to `admin_init`.
	 *
	 * Registers the admin notice depending on the condition.
	 *
	 * @return void
	 */
	public function maybe_ask_review() {

		// Add conditions here before showing admin_notice.
		add_action( 'admin_notices', array( $this, 'view_review_banner' ) );

	}

	/**
	 * Callback method to 'admin_notices.
	 *
	 * Loads template for review banner.
	 *
	 * @return void
	 */
	public function view_review_banner() {

		// Bail if not on automator related pages.
		if ( ! $this->is_page_automator_related() ) {
			return;
		}

		// Bail if banner was hidden permanently.
		if ( $this->is_banner_hidden_forever() ) {
			return;
		}

		// Bail if banner was hidden temporarily and banner hidden days is less than the defined num of days.
		if ( $this->is_banner_hidden_temporarily() && $this->get_banner_hidden_days() <= self::REVIEW_BANNER_TMP_NUM_DAYS ) {
			return;
		}

		// Load the template.
		$this->get_review_banner_template();

		// Always load the following templates.
		// Up to JS to show it conditionally base on clicked button renderend on the template above.
		$this->get_template( 'review-user-love-automator' );

		$this->get_template( 'review-user-dont-love-automator' );

	}

	/**
	 * @return void
	 */
	public function get_review_banner_template() {

		// 900 Credits remaining. Only shows if Automator Pro is not enabled.
		if ( $this->get_credits_remaining() <= 900 && ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			// Show free credits template.

			$this->get_template(
				'review-credits-used',
				array(
					'credits_used' => 1000 - $this->get_credits_remaining(),
				)
			);

			return;
		}

		// Sent count is greater than or equal to 100.
		if ( $this->get_sent_emails_count() >= 100 ) {
			// Show sent emails template.
			$this->get_template(
				'review-emails-sent',
				array(
					'emails_sent' => $this->get_sent_emails_count(),
				)
			);

			return;
		}

		// Completed recipes count is greater or equals to five.
		if ( $this->get_completed_recipes_count() >= 5 ) {

			// Show recipe count template.
			$this->get_template(
				'review-recipes-count',
				array(
					'total_recipe_completion_count' => $this->get_completed_recipes_count(),
				)
			);

			return;

		}

	}

	/**
	 * @param $template
	 * @param $args
	 *
	 * @return void
	 */
	public function get_template( $template = '', $args = array() ) {

		$vars = array_merge( $this->get_common_vars(), $args );

		include_once Utilities::automator_get_view( sanitize_file_name( $template . '.php' ) );

	}

	/**
	 * @return array
	 */
	public function get_common_vars() {

		return array(
			'url_wordpress'    => $this->get_banner_url( array( 'redirect_url' => 'https://wordpress.org/support/plugin/uncanny-automator/reviews/?filter=5#new-post' ), 'hide-forever' ),
			'url_feedback'     => $this->get_banner_url( array( 'redirect_url' => $this->get_feedback_url() ), 'hide-forever' ),
			'url_maybe_later'  => $this->get_banner_url( array(), 'maybe-later' ),
			'url_already_did'  => $this->get_banner_url( array(), 'hide-forever' ),
			'url_close_button' => $this->get_banner_url( array(), 'hide-forever' ),
		);

	}

	/**
	 * @param $args
	 * @param $type
	 *
	 * @return string
	 */
	public function get_banner_url( $args = array(), $type = '' ) {

		return add_query_arg(
			array(
				'type'         => $type,
				'nonce'        => wp_create_nonce( 'feedback_banner' ),
				'action'       => 'automator_handle_feedback',
				'redirect_url' => isset( $args['redirect_url'] ) ? rawurlencode( esc_url( $args['redirect_url'] ) ) : '',
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * @return string
	 */
	public function get_feedback_url() {

		$is_pro = false;

		$version = AUTOMATOR_PLUGIN_VERSION;

		if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {

			$is_pro = true;

			$version = AUTOMATOR_PRO_PLUGIN_VERSION;

		}

		// Send review URL
		$url_send_review = 'https://wordpress.org/support/plugin/uncanny-automator/reviews/?filter=5#new-post';

		// Send feedback URL
		$url_send_feedback_version = $is_pro ? 'Uncanny%20Automator%20Pro%20' . $version : 'Uncanny%20Automator%20' . $version;

		$url_send_feedback_source = $is_pro ? 'uncanny_automator_pro' : 'uncanny_automator';

		return esc_url( 'https://automatorplugin.com/feedback/?version=' . $url_send_feedback_version . '&utm_source=' . $url_send_feedback_source . '&utm_medium=review_banner' );

	}

	/**
	 * Method is_page_automator_related.
	 *
	 * Check if current loaded page is related to Automator.
	 *
	 * @return boolean True if it is. Otherwise, false.
	 */
	public function is_page_automator_related() {

		// Check only Automator related pages.
		global $typenow;

		// Get current page
		$page = automator_filter_input( 'page' );

		if ( ( $page !== 'uncanny-automator-dashboard' ) &&
			 ( empty( $typenow ) || 'uo-recipe' !== $typenow )
		) {
			return false;
		}

		$screen = get_current_screen();

		if ( 'post' === $screen->base ) {
			return false;
		}

		return true;

	}

	/**
	 * @return false|float
	 */
	public function get_banner_hidden_days() {

		$date_updated = get_option( '_uncanny_automator_review_reminder_date', 0 );

		$current_datetime = strtotime( current_time( 'mysql' ) );

		$seconds_passed = absint( $current_datetime - $date_updated );

		return floor( $seconds_passed / ( 60 * 60 * 24 ) );

	}

	/**
	 * @return bool
	 */
	public function is_banner_hidden_temporarily() {
		return 'maybe-later' === get_option( '_uncanny_automator_review_reminder' );
	}

	/**
	 * @return bool
	 */
	public function is_banner_hidden_forever() {
		return 'hide-forever' === get_option( '_uncanny_automator_review_reminder' );
	}

	/**
	 * @return mixed|null
	 */
	public function get_credits_remaining() {

		$credits = Api_Server::is_automator_connected();

		if ( false === $credits || empty( $credits['usage_limit'] ) || empty( $credits['paid_usage_count'] ) ) {
			// Assume unused if credits are empty.
			return apply_filters( 'automator_review_get_credits_remaining', 1000, $this );
		}

		$credits_remaining = absint( intval( $credits['usage_limit'] ) - intval( $credits['paid_usage_count'] ) );

		return apply_filters( 'automator_review_get_credits_remaining', $credits_remaining, $this );

	}

	/**
	 * @return mixed|null
	 */
	public function get_sent_emails_count() {

		return apply_filters( 'automator_review_get_sent_emails_count', absint( get_option( 'automator_sent_email_completed', 0 ) ), $this );

	}

	/**
	 * @return void
	 */
	public function get_completed_recipes_count() {
		Automator()->get->completed_recipes_count();

	}

}
