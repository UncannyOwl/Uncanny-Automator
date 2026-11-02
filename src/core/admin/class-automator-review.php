<?php
/**
 * @class   Admin_Review
 * @since   3.0
 * @version 4.2
 * @author  Saad S.
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use WP_REST_Response;
use WP_REST_Request;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

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

		add_action( 'admin_head', array( $this, 'hide_all_admin_notices_on_automator_pages' ) );

		add_action( 'init', array( $this, 'save_review_settings_action' ) );

		add_action( 'rest_api_init', array( $this, 'uo_register_api_for_reviews' ) );

		add_action( 'wp_ajax_automator_handle_feedback', array( $this, 'handle_feedback' ) );

		return true;
	}

	/**
	 * Answers a review banner link from admin-ajax.php.
	 *
	 * @return void
	 */
	public function handle_feedback() {

		$this->answer_feedback(
			array(
				'nonce'        => automator_filter_input( 'nonce' ),
				'type'         => automator_filter_input( 'type' ),
				'banner'       => automator_filter_input( 'banner' ),
				'track'        => automator_filter_input( 'track' ),
				'redirect_url' => automator_filter_input( 'redirect_url' ),
			)
		);

		exit;
	}

	/**
	 * Records a review banner answer, then redirects to where its link points.
	 *
	 * Only a user who sees the banner may answer it. The link's redirect is
	 * followed to this site or to the two hosts the banner links to (the
	 * WordPress.org review form and the Automator feedback form); any other
	 * address goes to the dashboard.
	 *
	 * @param array<string, string> $request The link's nonce, type, banner, track and redirect_url.
	 *
	 * @return void
	 */
	public function answer_feedback( array $request ) {

		if ( ! wp_verify_nonce( $request['nonce'], 'feedback_banner' ) ) {
			wp_die( 'Unauthorized. Error invalid nonce.' );
		}

		if ( ! current_user_can( automator_get_capability() ) ) {
			wp_die( 'Unauthorized.', 403 );
		}

		// Dismiss the banner.
		automator_update_option( '_uncanny_automator_review_reminder', $request['type'] );
		automator_update_option( '_uncanny_automator_review_reminder_date', time() );

		$this->track_review_step( $request['banner'], $request['track'] );

		// The review hosts are allowed for this redirect only.
		add_filter( 'allowed_redirect_hosts', array( $this, 'allow_review_redirect_hosts' ) );
		wp_safe_redirect( $this->answer_redirect_target( (string) $request['redirect_url'] ) );
		remove_filter( 'allowed_redirect_hosts', array( $this, 'allow_review_redirect_hosts' ) );
	}

	/**
	 * Where an answer goes: its link's redirect, else the page it came from, else
	 * the dashboard.
	 *
	 * @param string $redirect_url The link's redirect; '' when it has none.
	 *
	 * @return string
	 */
	private function answer_redirect_target( string $redirect_url ): string {

		if ( '' !== $redirect_url ) {
			return $redirect_url;
		}

		$referer = wp_get_referer();

		return false !== $referer ? $referer : admin_url();
	}

	/**
	 * Adds the hosts the review banner links to.
	 *
	 * @param string[] $hosts The hosts wp_safe_redirect() may send to.
	 *
	 * @return string[]
	 */
	public function allow_review_redirect_hosts( $hosts ) {
		return array_merge( (array) $hosts, array( 'wordpress.org', 'automatorplugin.com' ) );
	}

	/**
	 * Register rest api calls for misc tasks.
	 *
	 * @since 2.1.0
	 */
	public function uo_register_api_for_reviews() {

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/get-credits/',
			array(
				'methods'             => 'POST, GET',
				'callback'            => array( $this, 'get_credits' ),
				'permission_callback' => array( $this, 'rest_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/allow-tracking-switch/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_tracking_settings' ),
				'permission_callback' => array( $this, 'rest_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/track-review/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track_review_steps' ),
				'permission_callback' => array( $this, 'rest_permissions' ),
			)
		);
	}

	/**
	 * Valiate the rest api permissions.
	 *
	 * @return bool
	 */
	public function rest_permissions() {
		return is_user_logged_in() && current_user_can( automator_get_capability() );
	}

	/**
	 * Callback for tracking review results.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function track_review_steps( $request ) {

		// Validate nonce ( in case called from external source ).
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'success' => false ), 401 );
		}

		// Track the event.
		return $this->track_review_step(
			$request->get_param( 'banner' ),
			$request->get_param( 'event' )
		);
	}

	/**
	 * Callback for saving user selection for review by querystring.
	 *
	 * @param object $request
	 *
	 * @since 2.11
	 */
	public function save_review_settings_action() {

		$_action  = automator_filter_has_var( 'action' ) ? automator_filter_input( 'action' ) : false;
		$is_admin = function_exists( 'is_admin' ) && is_admin();

		if ( ! $_action || ! $is_admin ) {
			return;
		}

		// These cases write site-wide options, so require the same capability
		// as the rest of the class plus a valid nonce. is_admin() alone is a
		// request-context check (true for any wp-admin / admin-ajax request,
		// including unauthenticated ones) and is neither an authorization nor a
		// CSRF control.
		if ( ! current_user_can( automator_get_capability() ) ) {
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_review_settings' ) ) {
			return;
		}

		switch ( $_action ) {
			// @TODO: Review I don't believe these are being used anywhere.
			case 'uo-maybe-later':
			case 'uo-hide-forever':
				automator_update_option( '_uncanny_automator_review_reminder', str_replace( 'uo-', '', $_action ) );
				automator_update_option( '_uncanny_automator_review_reminder_date', time() );
				wp_safe_redirect( remove_query_arg( 'action' ) );
				die;

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
		$response->message       = esc_html__( 'Information is missing.', 'uncanny-automator' );
		$response->success       = true;
		$response->credits_left  = 0;
		$response->total_credits = 0;
		$existing_data           = automator_license_manager()->get_license_data();

		if ( empty( $existing_data ) ) {
			return new \WP_REST_Response( $response, 200 );
		}

		$response->credits_left = $existing_data['usage_limit'] - $existing_data['paid_usage_count'];

		$response->total_credits = $existing_data['usage_limit'];

		return new \WP_REST_Response( $response, 200 );
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
				automator_update_option( 'automator_reporting', true );
			} else {
				automator_delete_option( 'automator_reporting' );
			}

			if ( isset( $data['hide'] ) ) {
				automator_update_option( '_uncanny_automator_tracking_reminder', 'hide-forever' );
			}

			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		return new WP_REST_Response( array( 'success' => false ), 200 );
	}

	/**
	 * @return void
	 */
	public function hide_all_admin_notices_on_automator_pages() {

		$current_screen = get_current_screen();

		// Do not show if we cannot identify which screen it is.
		if ( ! $current_screen instanceof \WP_Screen ) {
			return;
		}

		// Safe check in case WP_Screen changed its structure.
		if ( ! isset( $current_screen->id ) ) {
			return;
		}

		$allowed_pages = self::get_allowed_page_for_credits_notif( true );

		// Only remove admin_notices if its on Automator pages
		if ( in_array( $current_screen->id, $allowed_pages, true ) ) {
			// Remove all admin notices
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}

		do_action( 'automator_show_internal_admin_notice' );
	}

	/**
	 * Determine whether it's automator page or not.
	 *
	 * @return bool
	 */
	public static function is_automator_page() {

		$current_screen = get_current_screen();

		$automator_pages = array(
			'edit-recipe_category',
			'edit-recipe_tag',
			'uo-recipe_page_uncanny-automator-dashboard',
			'uo-recipe_page_uncanny-automator-config',
			'uo-recipe_page_uncanny-automator-admin-tools',
			'uo-recipe_page_uo-recipe-scheduled-actions',
			'uo-recipe_page_uncanny-automator-pro-upgrade',
			'uo-recipe',
			'edit-uo-recipe',
		);

		// Do not show if we cannot identify which screen it is.
		if ( ! $current_screen instanceof \WP_Screen ) {
			return false;
		}

		// Safe check in case WP_Screen changed its structure.
		if ( ! isset( $current_screen->id ) ) {
			return false;
		}

		return in_array( $current_screen->id, $automator_pages, true );
	}

	/**
	 * Get the pages that are allowed for credits notification.
	 *
	 * @return string[]
	 */
	public static function get_allowed_page_for_credits_notif( $remove_notices_action = false ) {

		$wp_pages = array(
			'dashboard',
			'plugins',
			'edit-page', // The 'edit-page' refers to wp-admin/edit.php?post-type=page not the edit screen.
			'edit-post', // The 'edit-post' refers to wp-admin/edit.php not the edit screen.
		);

		$automator_pages = array(
			'edit-recipe_category',
			'edit-recipe_tag',
			'uo-recipe_page_uncanny-automator-dashboard',
			'uo-recipe_page_uncanny-automator-template-library',
			'uo-recipe_page_uncanny-automator-addons',
			'uo-recipe_page_uncanny-automator-config',
			'uo-recipe_page_uncanny-automator-admin-logs',
			'uo-recipe_page_uncanny-automator-admin-tools',
			'uo-recipe_page_uncanny-automator-config',
			'uo-recipe_page_uncanny-automator-pro-upgrade',
			'uo-recipe',
			'edit-uo-recipe',
		);

		$allowed_pages = $automator_pages;

		if ( false === $remove_notices_action ) {
			$allowed_pages = array_merge( $allowed_pages, $wp_pages );
		}

		return $allowed_pages;
	}

	/**
	 * Gets config to display review banner templates.
	 *
	 * @return mixed - array|bool - The template array if the banner should be displayed. Otherwise, false.
	 */
	public function get_review_banner_config() {

		// User spent N_CREDITS_TO_SHOW (20 @ 4.10) credits. Only shows if Automator Pro is not enabled.
		if ( $this->has_spent_credits( self::N_CREDITS_TO_SHOW ) && ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			return array(
				'credits_used' => $this->get_usage_count(),
				'banner'       => 'review-credits-used',
			);
		}

		// Sent count is greater than or equal to self::N_EMAILS_COUNT (30 @ 4.10).
		if ( $this->get_sent_emails_count() >= self::N_EMAILS_COUNT ) {
			return array(
				'emails_sent' => $this->get_sent_emails_count(),
				'banner'      => 'review-emails-sent',
			);
		}

		// Completed recipes count is greater or equals to N_COMPLETED_RECIPE_COUNT (30 @ 4.10).
		if ( $this->get_completed_recipes_count() >= self::N_COMPLETED_RECIPE_COUNT ) {
			return array(
				'total_recipe_completion_count' => $this->get_completed_recipes_count(),
				'banner'                        => 'review-recipes-count',
			);
		}

		return false;
	}

	/**
	 * Retrieves the common variables used in the template.
	 *
	 * @param string $banner - The banner key.
	 *
	 * @return array
	 */
	public function get_common_vars( $banner = '' ) {

		$args = array();

		if ( ! empty( $banner ) ) {
			$args['banner'] = $banner;
		}

		$wp_args       = array_merge( $args, array( 'redirect_url' => 'https://wordpress.org/support/plugin/uncanny-automator/reviews/#new-post' ) );
		$feedback_args = array_merge( $args, array( 'redirect_url' => $this->get_feedback_url() ) );
		return array(
			'url_wordpress'    => $this->get_banner_url( 'hide-forever', $wp_args ),
			'url_feedback'     => $this->get_banner_url( 'hide-forever', $feedback_args ),
			'url_maybe_later'  => $this->get_banner_url( 'maybe-later', $args ),
			'url_already_did'  => $this->get_banner_url( 'hide-forever', $args ),
			'url_close_button' => $this->get_banner_url( 'hide-forever', $args ),
		);
	}

	/**
	 * Retrieves the banner URL.
	 *
	 * @param $type
	 * @param $args
	 *
	 * @return string
	 */
	public function get_banner_url( $type, $args = array() ) {

		$defaults = array(
			'type'   => $type,
			'nonce'  => wp_create_nonce( 'feedback_banner' ),
			'action' => 'automator_handle_feedback',
		);

		$args = array_merge( $defaults, $args );
		if ( isset( $args['redirect_url'] ) ) {
			$args['redirect_url'] = rawurlencode( esc_url( $args['redirect_url'] ) );
		}

		return add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
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

		$url_send_feedback_source = $is_pro ? 'uncanny-automator-pro' : 'uncanny-automator';

		return esc_url( 'https://automatorplugin.com/feedback/?version=' . $url_send_feedback_version . '&utm_source=' . $url_send_feedback_source . '&utm_medium=in-plugin&utm_content=review-banner' );
	}

	/**
	 * Retrieves the number of days has passed since the banner was last hidden.
	 *
	 * @return false|float
	 */
	public function get_banner_hidden_days() {

		$date_updated = automator_get_option( '_uncanny_automator_review_reminder_date', 0 );

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
		return 'maybe-later' === automator_get_option( '_uncanny_automator_review_reminder' );
	}

	/**
	 * Determines whether the banner is hidden forever.
	 *
	 * @return bool
	 */
	public function is_banner_hidden_forever() {
		return 'hide-forever' === automator_get_option( '_uncanny_automator_review_reminder' );
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

		// The cached license only: the review notice must never call the license API.
		$credits = get_transient( License_Manager::TRANSIENT_LICENSE );

		$usage_count = is_array( $credits ) && isset( $credits['paid_usage_count'] ) ? absint( $credits['paid_usage_count'] ) : false;

		// Allow overide for testing purposes.
		return absint( apply_filters( 'automator_review_get_usage_count', $usage_count, $this ) );
	}

	/**
	 * Retrieves the number of emails sent.
	 *
	 * @return int The number of emails sent.
	 */
	public function get_sent_emails_count() {

		return absint( apply_filters( 'automator_review_get_sent_emails_count', automator_get_option( 'automator_sent_email_completed', 0 ), $this ) );
	}

	/**
	 * Retrieves the number of completed recipes.
	 *
	 * @return int The number of completed recipes.
	 */
	public function get_completed_recipes_count() {

		return apply_filters( 'automator_review_get_completed_recipe_count', absint( Automator()->get->completed_recipes_count() ), $this );
	}

	/**
	 * Track review step.
	 *
	 * @param string $banner - The banner key.
	 * @param string $event - The event of the review process.
	 *
	 * @return void
	 */
	public function track_review_step( $banner, $event ) {

		// Bail if any of the values are empty.
		if ( empty( $banner ) || empty( $event ) ) {
			return new WP_REST_Response( array( 'success' => false ), 401 );
		}

		// Create a new WP_REST_Request object
		$request = new WP_REST_Request(
			'POST',
			AUTOMATOR_REST_API_END_POINT . '/log-event/'
		);

		// Set the parameters for the request
		$request->set_body_params(
			array(
				'event' => 'review-tracking',
				'value' => array(
					'banner' => $banner,
					'event'  => $event,
				),
			)
		);

		// Get the instance of Usage_Reports class.
		$reports = Automator_Load::get_core_class_instance( 'Usage_Reports' );
		$reports = is_a( $reports, 'Usage_Reports' ) ? $reports : new Usage_Reports();

		// Log the event.
		return $reports->log_event( $request );
	}
}
