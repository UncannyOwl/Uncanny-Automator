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
 * @package Uncanny_Automator
 */
class Automator_Review {

	/**
	 * Automator_Review constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'maybe_ask_review' ] );
		add_action( 'init', [ $this, 'save_review_settings_action' ] );
		add_action( 'rest_api_init', [ $this, 'uo_register_api_for_reviews' ] );
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

				return new WP_REST_Response( [ 'redirect_url' => $redirect_url ], 201 );
			}
		}

		return new WP_REST_Response( [ 'redirect_url' => '' ], 201 );
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

			add_action( 'admin_notices', function () {

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
				$url_hide_forever          = add_query_arg( [ 'action' => 'uo-hide-forever' ] );
				$url_remind_later          = add_query_arg( [ 'action' => 'uo-maybe-later' ] );
				include Utilities::automator_get_view( 'review-banner.php' );
			} );
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

			return new WP_REST_Response( [ 'success' => true ], 200 );
		}

		return new WP_REST_Response( [ 'success' => false ], 200 );
	}

	/**
	 * Callback for saving user selection for review by querystring.
	 *
	 * @param object $request
	 *
	 * @return object
	 * @since 2.11
	 */
	public function save_review_settings_action() {
		// check if its a valid request.
		if ( isset( $_GET['action'] ) && ( 'uo-maybe-later' === $_GET['action'] || 'uo-hide-forever' === $_GET['action'] ) ) {
			if ( function_exists( 'is_admin' ) && is_admin() ) {
				$_action = str_replace( 'uo-', '', $_GET['action'] );

				update_option( '_uncanny_automator_review_reminder', $_action );
				update_option( '_uncanny_automator_review_reminder_date', current_time( 'timestamp' ) );
				$back_url = remove_query_arg( 'action' );
				wp_safe_redirect( $back_url );
				die;
			}
		}
	}
}
