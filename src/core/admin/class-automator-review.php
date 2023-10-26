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
	 * Constant REVIEW_BANNER_TMP_NUM_DAYS
	 *
	 * @var int The number of days set to show the banner again when 'maybe later' button is clicked.
	 */
	const REVIEW_BANNER_TMP_NUM_DAYS = 10;

	/**
	 * Constant N_CREDITS_TO_SHOW
	 *
	 * @var int The number of credits usage for the banner to show up.
	 */
	const N_CREDITS_TO_SHOW = 20;

	/**
	 * Constant N_EMAILS_COUNT
	 *
	 * @var int The number of emails sent for the banner to show up.
	 */
	const N_EMAILS_COUNT = 30;

	/**
	 * Constant N_COMPLETED_RECIPE_COUNT
	 *
	 * @var int The number of completed recipe count for the banner to show up.
	 */
	const N_COMPLETED_RECIPE_COUNT = 30;

	/**
	 * Method __construct.
	 *
	 * Registers the action hooks.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->register_hooks();

	}

	/**
	 * Registers required hook for banner to show up.
	 *
	 * @return bool True, always.
	 */
	protected function register_hooks() {

		add_action( 'admin_init', array( $this, 'maybe_ask_review' ) );

		add_action( 'admin_init', array( $this, 'maybe_ask_tracking' ) );

		add_action( 'init', array( $this, 'save_review_settings_action' ) );

		add_action( 'rest_api_init', array( $this, 'uo_register_api_for_reviews' ) );

		add_action( 'wp_ajax_automator_handle_feedback', array( $this, 'handle_feedback' ) );

		add_action( 'wp_ajax_automator_handle_credits_notification_feedback', array( $this, 'handle_feedback_credits' ) );

		return true;

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
	 * @return void
	 */
	public function handle_feedback_credits() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_handle_credits_notification_feedback' ) ) {

			wp_die( 'Unauthorized. Error invalid nonce.' );

		}

		$type = absint( automator_filter_input( 'type' ) );
		$proc = automator_filter_input( 'procedure' );

		if ( 'dismiss' === $proc ) {

			$this->dismiss_credits_notification( $type );

		}

		wp_redirect( wp_get_referer() ); //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

		exit;

	}

	/**
	 * Dismisses the credits notification base on the type.
	 *
	 * @param int $type The type of notification.
	 *
	 * @return bool True, always.
	 */
	public function dismiss_credits_notification( $type = null ) {

		if ( null === $type ) {
			return;
		}

		update_option( '_uncanny_credits_notification_' . $type, 'hide-forever', true );

		if ( 25 === $type ) {
			// Also hide '_uncanny_credits_notification_100' notification.
			update_option( '_uncanny_credits_notification_100', 'hide-forever', true );
		}

		if ( 0 === $type ) {
			// Also hide '_uncanny_credits_notification_25' and '_uncanny_credits_notification_100' notifications.
			update_option( '_uncanny_credits_notification_25', 'hide-forever', true );
			update_option( '_uncanny_credits_notification_100', 'hide-forever', true );
		}

		return true;

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

		$_is_reminder = automator_get_option( '_uncanny_automator_tracking_reminder', '' );

		$_reminder_date = automator_get_option( '_uncanny_automator_tracking_reminder_date', current_time( 'timestamp' ) );

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

		// Credits notifications.
		add_action( 'admin_notices', array( $this, 'load_credits_notif_required_assets' ) );

		// Review banner notices.
		add_action( 'admin_notices', array( $this, 'view_review_banner' ) );

	}

	/**
	 * @return void
	 */
	public function load_credits_notif_required_assets() {

		if ( ! $this->should_display_credits_notif() ) {
			return;
		}

		if ( ! $this->has_credits_notification() ) {
			return;
		}

		wp_enqueue_style( 'uap-admin', Utilities::automator_get_asset( 'backend/dist/bundle.min.css' ), array(), Utilities::automator_get_version() );

		// Register main JS in case it wasnt registered.
		wp_register_script(
			'uap-admin',
			Utilities::automator_get_asset( 'backend/dist/bundle.min.js' ),
			array(),
			Utilities::automator_get_version(),
			true
		);

		$admin_menu_instance = Admin_Menu::get_instance();

		// Get data for the main script
		wp_localize_script(
			'uap-admin',
			'UncannyAutomatorBackend',
			$admin_menu_instance->get_js_backend_inline_data( null )
		);

		// Enqueue uap-admin.
		wp_enqueue_script( 'uap-admin' );

	}

	/**
	 * Callback method to 'admin_notices.
	 *
	 * Loads template for review banner.
	 *
	 * @return void
	 */
	public function view_review_banner() {

		// Disable both credits notification and review banner notification in the "uncanny-automator-app-integrations" page.
		if ( automator_filter_has_var( 'page' ) && 'uncanny-automator-app-integrations' === automator_filter_input( 'page' ) ) {
			return;
		}

		// Do check before rendering the credits notification.
		if ( $this->should_display_credits_notif() && $this->has_credits_notification() ) {
			return $this->display_credits_notification();
		}

		/**
		 * Proceed to review banner rendering.
		 */
		if ( ! $this->is_page_automator_related() ) {
			// Bail if not on automator related pages.
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

		// Loads the template.
		$this->display_review_banner_template();

	}

	/**
	 * Determines whether the current page should display the credits notification.
	 *
	 * @return bool
	 */
	public function should_display_credits_notif() {

		// Make sure to only display on admin side.
		if ( ! is_admin() ) {
			return false;
		}

		return self::can_display_credits_notif();

	}

	/**
	 * Determines whether the current screen can display the credits notification or not.
	 *
	 * @return boolean
	 */
	public static function can_display_credits_notif() {

		$current_screen = get_current_screen();

		// Do not show if we cannot identify which screen it is.
		if ( ! $current_screen instanceof \WP_Screen ) {
			return false;
		}

		// Safe check in case WP_Screen changed its structure.
		if ( ! isset( $current_screen->id ) ) {
			return false;
		}

		return in_array( $current_screen->id, self::get_allowed_page_for_credits_notif(), true );

	}

	/**
	 * Get the pages that are allowed for credits notification.
	 *
	 * @return string[]
	 */
	public static function get_allowed_page_for_credits_notif() {

		$allowed_pages = array(
			'dashboard',
			'plugins',
			'edit-recipe_category',
			'edit-recipe_tag',
			'uo-recipe_page_uncanny-automator-dashboard',
			'uo-recipe_page_uncanny-automator-integrations',
			'uo-recipe_page_uncanny-automator-config',
			'uo-recipe_page_uncanny-automator-admin-logs',
			'uo-recipe_page_uncanny-automator-admin-tools',
			'uo-recipe_page_uncanny-automator-config',
			'uo-recipe_page_uncanny-automator-pro-upgrade',
			'uo-recipe',
			'edit-uo-recipe',
			'edit-page', // The 'edit-page' refers to wp-admin/edit.php?post-type=page not the edit screen.
			'edit-post', // The 'edit-post' refers to wp-admin/edit.php not the edit screen.
		);

		return $allowed_pages;

	}

	/**
	 * Determines if there is a credits notification.
	 *
	 * @return bool
	 */
	public function has_credits_notification() {

		if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			return false;
		}

		$is_credits_less_than_100 = $this->get_credits_remaining( $this->get_connected_user() ) <= 100;

		// Return false immediately if credits is less than 100.
		if ( ! $is_credits_less_than_100 ) {
			return false;
		}

		// Otherwise, if either of the option below is not 'hidden_forever', return true.
		$has_undismissed_notification = ! $this->is_credits_notification_hidden_forever( 100 )
			|| ! $this->is_credits_notification_hidden_forever( 25 )
			|| ! $this->is_credits_notification_hidden_forever( 0 );

		if ( $has_undismissed_notification ) {
			return true;
		}

		return false;

	}

	/**
	 * @return false|int|void
	 */
	public function display_credits_notification() {

		$user_connected = $this->get_connected_user();

		if ( false === $user_connected ) {
			return false;
		}

		$credits_remaining = $this->get_credits_remaining( $user_connected );

		$credits_remaining_args = array(
			'credits_remaining' => $credits_remaining,
			'customer_name'     => $user_connected['customer_name'],
			'credits_used'      => $this->get_usage_count(),
		);

		// Can be an assoc array without if then else condition, but might be hard to read.
		if ( $credits_remaining <= 0 && ! $this->is_credits_notification_hidden_forever( 0 ) ) {
			$credits_remaining_args['dismiss_link'] = $this->credits_feedback_url( 0, 'dismiss' );
			return $this->get_template( 'credits-remaining-0', $credits_remaining_args );
		}

		if ( $credits_remaining <= 25 && ! $this->is_credits_notification_hidden_forever( 25 ) ) {
			$credits_remaining_args['dismiss_link'] = $this->credits_feedback_url( 25, 'dismiss' );
			return $this->get_template( 'credits-remaining-25', $credits_remaining_args );
		}

		if ( $credits_remaining <= 100 && ! $this->is_credits_notification_hidden_forever( 100 ) ) {
			$credits_remaining_args['dismiss_link'] = $this->credits_feedback_url( 100, 'dismiss' );
			return $this->get_template( 'credits-remaining-100', $credits_remaining_args );
		}
	}


	/**
	 * @param $type
	 * @param $procedure
	 *
	 * @return string
	 */
	function credits_feedback_url( $type = 100, $procedure = 'dismiss' ) {

		$action = 'automator_handle_credits_notification_feedback';

		return add_query_arg(
			array(
				'action'    => $action,
				'procedure' => $procedure,
				'type'      => $type,
				'nonce'     => wp_create_nonce( $action ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Displays review banner template.
	 *
	 * @return int|bool Returns 1 if template is successfully displayed. Returns false, if no banner was shown.
	 */
	public function display_review_banner_template() {

		// User spent N_CREDITS_TO_SHOW (20 @ 4.10) credits. Only shows if Automator Pro is not enabled.
		if ( $this->has_spent_credits( self::N_CREDITS_TO_SHOW ) && ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			// Show free credits template.
			return $this->get_template(
				'review-credits-used',
				array(
					'credits_used' => $this->get_usage_count(),
				)
			);

		}

		// Sent count is greater than or equal to self::N_EMAILS_COUNT (30 @ 4.10).
		if ( $this->get_sent_emails_count() >= self::N_EMAILS_COUNT ) {
			// Show sent emails template.
			return $this->get_template(
				'review-emails-sent',
				array(
					'emails_sent' => $this->get_sent_emails_count(),
				)
			);
		}

		// Completed recipes count is greater or equals to N_COMPLETED_RECIPE_COUNT (30 @ 4.10).
		if ( $this->get_completed_recipes_count() >= self::N_COMPLETED_RECIPE_COUNT ) {
			// Show recipe count template.
			return $this->get_template(
				'review-recipes-count',
				array(
					'total_recipe_completion_count' => $this->get_completed_recipes_count(),
				)
			);
		}

		/**
		 * Always load the following templates.
		 *
		 * Up to JS to show it conditionally base on clicked button renderend on the template above.
		 **/
		$this->get_template( 'review-user-love-automator' );

		$this->get_template( 'review-user-dont-love-automator' );

		return false;

	}

	/**
	 * Retrieves the template.
	 *
	 * @param string $template The name of the template.
	 * @param array $args The arguments you want to pass to the template.
	 *
	 * @return int 1 if the view was successfully included. Otherwise, throws E_WARNING.
	 */
	public function get_template( $template = '', $args = array() ) {

		$vars = array_merge( $this->get_common_vars(), $args );

		return include_once Utilities::automator_get_view( sanitize_file_name( $template . '.php' ) );

	}

	/**
	 * Retrieves the common variables used in the template.
	 *
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
	 * Retrieves the banner URL.
	 *
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
	 * Retrieves the feedback URL.
	 *
	 * @return string
	 */
	public function get_feedback_url() {

		$is_pro = false;

		$version = AUTOMATOR_PLUGIN_VERSION;

		if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {

			$is_pro = true;

			$version = AUTOMATOR_PRO_PLUGIN_VERSION;

		}

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

		if ( ( $page !== 'uncanny-automator-dashboard' ) && ( empty( $typenow ) || 'uo-recipe' !== $typenow ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( 'post' === $screen->base ) {
			return false;
		}

		return true;

	}

	/**
	 * Retrieves the number of days has passed since the banner was last hidden.
	 *
	 * @return false|float
	 */
	public function get_banner_hidden_days() {

		$date_updated = get_option( '_uncanny_automator_review_reminder_date', 0 );

		$current_datetime = strtotime( current_time( 'mysql' ) );

		$seconds_passed = absint( $current_datetime - $date_updated );

		return floor( $seconds_passed / ( 60 * 60 * 24 ) );

	}

	/**
	 * Determines whether the banner was hidden temporarily.
	 *
	 * @return bool
	 */
	public function is_banner_hidden_temporarily() {
		return 'maybe-later' === get_option( '_uncanny_automator_review_reminder' );
	}

	/**
	 * Determines whether the banner is hidden forever.
	 *
	 * @return bool
	 */
	public function is_banner_hidden_forever() {
		return 'hide-forever' === get_option( '_uncanny_automator_review_reminder' );
	}

	/**
	 * Determines whether the banner is hidden forever.
	 *
	 * @param int $notification_type The type of notification. E.g. 100, 25, 0.
	 *
	 * @return bool
	 */
	public function is_credits_notification_hidden_forever( $notification_type = 100 ) {
		return 'hide-forever' === get_option( '_uncanny_credits_notification_' . $notification_type );
	}

	/**
	 * Retrieves the number of credits remaining.
	 *
	 * @return mixed|null
	 */
	public function get_credits_remaining( $user_connected ) {

		if ( false === $user_connected || empty( $user_connected['usage_limit'] ) || empty( $user_connected['paid_usage_count'] ) ) {
			// Assume unused if credits are empty.
			return apply_filters( 'automator_review_get_credits_remaining', 250, $this );
		}

		$credits_remaining = absint( intval( $user_connected['usage_limit'] ) - intval( $user_connected['paid_usage_count'] ) );

		return apply_filters( 'automator_review_get_credits_remaining', $credits_remaining, $this );

	}

	/**
	 * @return false|null
	 */
	public function get_connected_user() {

		return Api_Server::is_automator_connected();

	}

	/**
	 * Determines whether the user has spent number of credits.
	 *
	 * @param int $number_of_credits The number of credits allowed.
	 *
	 * @return bool True if the number of credits used is greater and equals to the provided number of credits.
	 */
	public function has_spent_credits( $number_of_credits = 0 ) {

		$usage_count = $this->get_usage_count();

		// Return false if 'paid_usage_count' is not set.
		if ( false === $usage_count ) {
			return false;
		}

		return $usage_count >= $number_of_credits;

	}

	/**
	 * Retrieves the usage count.
	 *
	 * @return int|bool The usage count. Returns false, if 'paid_usage_count' is not set.
	 */
	protected function get_usage_count() {

		$credits = Api_Server::is_automator_connected();

		$usage_count = isset( $credits['paid_usage_count'] ) ? absint( $credits['paid_usage_count'] ) : false;

		// Allow overide for testing purposes.
		return absint( apply_filters( 'automator_review_get_usage_count', $usage_count, $this ) );

	}

	/**
	 * Retrieves the number of emails sent.
	 *
	 * @return int The number of emails sent.
	 */
	public function get_sent_emails_count() {

		return absint( apply_filters( 'automator_review_get_sent_emails_count', get_option( 'automator_sent_email_completed', 0 ), $this ) );

	}

	/**
	 * Retrieves the number of completed recipes.
	 *
	 * @return int The number of completed recipes.
	 */
	public function get_completed_recipes_count() {

		return apply_filters( 'automator_review_get_completed_recipe_count', absint( Automator()->get->completed_recipes_count() ), $this );

	}

}
