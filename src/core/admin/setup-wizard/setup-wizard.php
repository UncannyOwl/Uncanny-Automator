<?php
/**
 * Setup wizard controller.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Throwable;
use Uncanny_Automator\App\Feature_State\Domain\Feature_State;
use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;

use function Uncanny_Automator\App\Infrastructure\automator_feature_state_query;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Setup_Wizard
 *
 * Handles the set-up wizard.
 *
 * @since ${VERSION}
 */
class Setup_Wizard {

	/**
	 * The account connection URL.
	 *
	 * @var string
	 */
	public $connect_url = '';

	/**
	 * The account connection page.
	 *
	 * @var string
	 */
	public $connect_page = '';

	/**
	 * Request cache for the account connection state.
	 *
	 * @var bool|null
	 */
	private $is_user_connected_cache = null;

	/**
	 * Set-ups action hooks.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->connect_url = AUTOMATOR_STORE_URL;

		$this->connect_page = AUTOMATOR_FREE_STORE_CONNECT_URL;

		if ( 'uncanny-automator-setup-wizard' === automator_filter_input( 'page' ) ) {
			// Keep Uncanny Agent off the setup wizard. Agent actions redirect to a
			// supported admin surface before opening the docked panel.
			add_filter( 'automator_mcp_should_render_surface', '__return_false' );
		}

		add_action( 'admin_menu', array( $this, 'setup_menu_page' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Persist the usage tracking opt-in before the step redirects run.
		add_action( 'admin_init', array( $this, 'maybe_save_tracking_setting' ), 19 );

		// Redirect to proper step.
		add_action( 'admin_init', array( $this, 'redirect_if_connected' ), 20 );

		if ( filter_has_var( INPUT_GET, 'recipe_ui_connect_automator_license' ) && filter_has_var( INPUT_GET, 'origin' ) ) {

			add_action( 'admin_init', array( $this, 'redirect_if_from_recipe_builder' ) );

		}
	}

	/**
	 * Record an account connection attempt.
	 *
	 * @return void
	 */
	public static function set_tried_connecting() {

		// Authorization gate: this AJAX handler writes a site option, so it must
		// not be reachable by low-privileged or unauthenticated-via-CSRF callers.
		if ( ! current_user_can( automator_get_admin_capability() ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		self::set_has_tried_connecting( true );
		die;
	}

	/**
	 * Redirect account connections that start in the recipe builder.
	 *
	 * @return void
	 */
	public function redirect_if_from_recipe_builder() {

		$secret = wp_create_nonce( 'automator_setup_wizard_redirect_nonce' );

		$message_to_decrypt = array(
			'redirect_url' => urldecode( filter_input( INPUT_GET, 'return_to' ) ) . '&state=' . $secret,
		);

		$message = Automator_Helpers_Recipe::encrypt( $message_to_decrypt, $secret );

		wp_redirect(  // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			add_query_arg(
				array(
					'client'       => $message,
					'state'        => $secret,
					'redirect_url' => $this->get_dashboard_uri( 2, true ),
					'__version'    => AUTOMATOR_PLUGIN_VERSION,
				),
				$this->connect_url . $this->connect_page
			)
		);

		die;
	}

	/**
	 * Set-ups the menu page.
	 *
	 * @return void
	 */
	public function setup_menu_page() {

		$is_setup_wizard_page = $this->is_setup_wizard_page();
		$is_visible           = $this->feature_is_visible( Feature_State::SETUP_WIZARD );

		if ( $is_visible || $is_setup_wizard_page ) {
			// Preserve an in-progress direct route without advertising a hidden
			// wizard in the Recipes menu. WordPress requires a string parent slug.
			add_submenu_page(
				$is_visible ? 'edit.php?post_type=uo-recipe' : '',
				esc_attr__( 'Uncanny Automator Setup Wizard', 'uncanny-automator' ),
				esc_attr__( 'Setup wizard', 'uncanny-automator' ),
				automator_get_capability(),
				'uncanny-automator-setup-wizard',
				array(
					$this,
					'setup_wizard_view',
				),
				0
			);
		}
	}

	/**
	 * Retrieves the views directory path.
	 *
	 * @return string
	 */
	public function get_view_path() {
		return UA_ABSPATH . 'src/core/admin/setup-wizard/src/views/';
	}

	/**
	 * Includes the set-up wizard view.
	 *
	 * @return void
	 */
	public function setup_wizard_view() {
		include_once $this->get_view_path() . 'welcome.php';
	}

	/**
	 * Enqueues set-up wizard CSS.
	 *
	 * @return void
	 */
	public function enqueue_styles() {

		$page = automator_filter_input( 'page' );

		if ( 'uncanny-automator-setup-wizard' === $page ) {

			wp_enqueue_style(
				'uap-setup-wizard',
				plugins_url( 'assets/css/setup-wizard.css', __FILE__ ),
				array( 'uap-admin' ),
				Utilities::automator_get_version()
			);

		}
	}

	/**
	 * Retrieves the number of steps in the wizard.
	 *
	 * The usage tracking step is only shown when nothing else opts the user in:
	 * connecting an account does, and so does activating a Pro license, which is
	 * what Pro's own step 1 asks for. The tracking choice does not change the step
	 * count during navigation.
	 *
	 * @return int
	 */
	public function get_total_steps() {

		$needs_tracking_step = ! $this->is_user_connected()
			&& ! $this->is_pro_active();

		return (int) apply_filters( 'automator_setup_wizard_total_steps', $needs_tracking_step ? 4 : 3 );
	}

	/**
	 * Retrieves the current step number.
	 *
	 * @return int
	 */
	public function get_step_number() {

		$step = absint( automator_filter_input( 'step' ) );

		if ( $step < 1 ) {
			$step = 1;
		}

		$step = min( $step, $this->get_total_steps() );

		return $step;
	}

	/**
	 * Retrieves the current step.
	 *
	 * @return string
	 */
	public function get_step() {
		return sprintf( 'step-%d', $this->get_step_number() );
	}

	/**
	 * Retrieves the template path of the current step.
	 *
	 * Templates are named after the longest flow (step-4.php is the final screen).
	 * Shorter flows skip the usage tracking step, so their last step still renders
	 * the final screen.
	 *
	 * @return string
	 */
	public function get_step_view() {

		$step = $this->get_step_number();

		if ( $step === $this->get_total_steps() ) {
			$step = 4;
		}

		$view = $this->get_view_path() . sprintf( 'step-%d.php', $step );

		if ( ! is_file( $view ) ) {
			$view = $this->get_view_path() . 'step-1.php';
		}

		return $view;
	}

	/**
	 * Retrieves the URL of the previous step.
	 *
	 * @return string
	 */
	public function get_previous_step_uri() {

		$previous = max( 1, $this->get_step_number() - 1 );

		$uri = $this->get_dashboard_uri( $previous );

		// skip=true suppresses the connection error notice — going back is not a connection attempt.
		if ( 2 === $previous ) {
			$uri = add_query_arg( 'skip', 'true', $uri );
		}

		return $uri;
	}

	/**
	 * Retrieves the URL of the next step.
	 *
	 * @param array $args Optional query arguments to add to the URL.
	 *
	 * @return string
	 */
	public function get_next_step_uri( $args = array() ) {

		$next = min( $this->get_total_steps(), $this->get_step_number() + 1 );

		return add_query_arg( $args, $this->get_dashboard_uri( $next ) );
	}

	/**
	 * Retrieves the connect button URL..
	 *
	 * @return string
	 */
	public function get_connect_button_uri() {

		$secret = wp_create_nonce( 'automator_setup_wizard_client' );

		$redirect_url = $this->get_dashboard_uri( 2, true );

		$message_to_decrypt = array(
			'redirect_url' => $redirect_url,
		);

		$message = Automator_Helpers_Recipe::encrypt( $message_to_decrypt, $secret );

		return add_query_arg(
			array(
				'client'       => $message,
				'state'        => $secret,
				'__version'    => AUTOMATOR_PLUGIN_VERSION,
				'requested'    => time(),
				'redirect_url' => $redirect_url, // Legacy sign-up form handle.
			),
			$this->connect_url . $this->connect_page
		);
	}

	/**
	 * Retrieves the dashboards url.
	 *
	 * @param int  $step      Wizard step number.
	 * @param bool $is_method Whether the URL starts an account connection.
	 *
	 * @return string
	 */
	public function get_dashboard_uri( $step = 1, $is_method = false ) {

		$args = array(
			'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
			'page'      => 'uncanny-automator-setup-wizard',
			'state'     => wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ),
			'step'      => absint( $step ),
		);

		if ( $is_method ) {
			$args['method'] = 'connect';
		}

		return add_query_arg(
			$args,
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Retrieves the checkout URL.
	 *
	 * @return string
	 */
	public function get_checkout_uri() {

		return 'https://automatorplugin.com/pricing/?utm_source=uncanny-automator&utm_medium=in-plugin&utm_content=setup-wizard&utm_campaign=upgrade-to-pro';
	}

	/**
	 * Determines whether the user is connected or not.
	 *
	 * @return false|null
	 */
	public function is_user_connected() {

		if ( null !== $this->is_user_connected_cache ) {
			return $this->is_user_connected_cache;
		}

		$page = automator_filter_input( 'page' );

		$post_type = automator_filter_input( 'post_type' );

		// Pull data from licensing server if user is from set-up wizard.
		if ( AUTOMATOR_POST_TYPE_RECIPE === $post_type && 'uncanny-automator-setup-wizard' === $page ) {
			$this->is_user_connected_cache = (bool) Api_Server::is_automator_connected( true ); // Pass force refresh to true.

			return $this->is_user_connected_cache;
		}

		// Otherwise pull data from local db to avoid multiple calls.
		$this->is_user_connected_cache = ! empty( Api_Server::get_license_key() );

		return $this->is_user_connected_cache;
	}

	/**
	 * Determines whether the user has done what step 1 asks of them.
	 *
	 * Lite asks for a free account; Pro asks for a license key, and a free
	 * connection does not satisfy that. Pro is judged on the license *status*
	 * rather than the key: deactivating a license clears the status but can leave
	 * the key behind, which would otherwise read as still-licensed.
	 *
	 * @return bool
	 */
	public function is_step_1_complete() {

		if ( $this->is_pro_active() ) {
			return 'valid' === automator_get_option( 'uap_automator_pro_license_status' );
		}

		return $this->is_user_connected();
	}

	/**
	 * Determine whether Automator Pro is active.
	 *
	 * @return bool
	 */
	public function is_pro_active() {
		return defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' );
	}

	/**
	 * Determine whether the wizard can show the Uncanny Agent option.
	 *
	 * @return bool
	 */
	public function has_agent_access() {
		// The wizard button follows the launcher column, not settings-tab access.
		return $this->feature_is_visible( Feature_State::AGENT_LAUNCHER_TAB );
	}

	/**
	 * Determine whether the current site can start a Page Builder page.
	 *
	 * The policy and the runtime must both permit new Page Builder pages.
	 *
	 * @return bool
	 */
	public function has_page_builder_access() {

		// The wizard button is a new-page affordance and therefore follows the
		// Page Builder menu column before applying runtime readiness checks.
		if ( ! $this->feature_is_visible( Feature_State::PAGE_BUILDER_MENU ) ) {
			return false;
		}

		try {
			return $this->page_builder_is_available();
		} catch ( Throwable $error ) {
			unset( $error );

			return false;
		}
	}

	/**
	 * Read Page Builder readiness for new-page creation.
	 *
	 * @return bool
	 */
	protected function page_builder_is_available() {

		$settings = new Page_Builder_Settings();

		return $settings->is_enabled( true )
			&& false !== has_action( 'admin_post_uncanny_page_builder_create_page' );
	}

	/**
	 * Check if the current request is for the setup wizard.
	 *
	 * @return bool
	 */
	protected function is_setup_wizard_page() {
		return 'uncanny-automator-setup-wizard' === automator_filter_input( 'page' );
	}

	/**
	 * Get the visibility of one feature.
	 *
	 * @param string $feature Feature-state key.
	 *
	 * @return bool
	 */
	protected function feature_is_visible( $feature ) {
		try {
			return automator_feature_state_query()->execute()->is_visible( $feature );
		} catch ( Throwable $error ) {
			unset( $error );

			return false;
		}
	}

	/**
	 * Get the signed Page Builder page creation URL.
	 *
	 * @return string
	 */
	public function get_page_builder_create_uri() {

		return add_query_arg(
			array(
				'action'   => 'uncanny_page_builder_create_page',
				'_wpnonce' => wp_create_nonce( 'uncanny_page_builder_create_page' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Determine whether the Pro plugin has a stored license key.
	 *
	 * @return bool
	 */
	public function is_pro_connected() {
		$is_pro_active   = defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' );
		$has_pro_license = trim( automator_get_option( 'uap_automator_pro_license_key', '' ) );

		return ! ( $is_pro_active && empty( $has_pro_license ) );
	}

	/**
	 * Retrieves the steps.
	 *
	 * @return array
	 */
	public function get_steps() {

		$steps           = array();
		$number_of_steps = $this->get_total_steps();

		$current_step = $this->get_step_number();

		for ( $i = 1; $i <= $number_of_steps; $i++ ) {
			$steps[ $i ] = array(
				'label'   => esc_html( $i ),
				'classes' => array( sprintf( $i ) ),
			);
			if ( $current_step === $i ) {
				$steps[ $i ]['classes'][] = 'active';
			}
		}

		return (array) $steps;
	}

	/**
	 * Retrieves the dashboard URL.
	 *
	 * @return string
	 */
	public function get_automator_dashboard_uri() {
		return add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-dashboard',
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Retrieves the dashboard URL that opens Uncanny Agent.
	 *
	 * @return string
	 */
	public function get_automator_dashboard_agent_uri() {

		return add_query_arg( 'automator_open_agent', '1', $this->get_automator_dashboard_uri() );
	}

	/**
	 * Persists the usage tracking opt-in when the user accepts it from the wizard.
	 *
	 * The "Count me in!" button on step 2 links to step 3 with `automator_reporting=true`.
	 *
	 * @return void
	 */
	public function maybe_save_tracking_setting() {

		if ( 'uncanny-automator-setup-wizard' !== automator_filter_input( 'page' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'automator_reporting' ) ) {
			return;
		}

		if ( ! current_user_can( automator_get_admin_capability() ) ) {
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( 'state' ), 'automator_setup_wizard_redirect_nonce' ) ) {
			return;
		}

		$opted_in = automator_filter_input( 'automator_reporting', INPUT_GET, FILTER_VALIDATE_BOOLEAN );

		if ( $opted_in ) {
			// Opt-outs are represented by the option being absent (see the Improve
			// Automator settings tab) — never store an explicit false here.
			automator_update_option( 'automator_reporting', true );
		}

		automator_update_option( 'automator_setup_wizard_tracking_choice', $opted_in ? 'opted_in' : 'declined' );
	}

	/**
	 * Determines whether the user already made a tracking choice in the wizard or is opted in.
	 *
	 * @return bool
	 */
	public function has_recorded_tracking_choice() {

		if ( (bool) automator_get_option( 'automator_reporting', false ) ) {
			return true;
		}

		return '' !== (string) automator_get_option( 'automator_setup_wizard_tracking_choice', '' );
	}

	/**
	 * Redirects the user if they're connected already.
	 *
	 * @return void
	 */
	public function redirect_if_connected() {

		$page = automator_filter_input( 'page', INPUT_GET );
		$step = absint( automator_filter_input( 'step', INPUT_GET ) );

		if ( 'uncanny-automator-setup-wizard' !== $page ) {
			return;
		}

		$redirect_step = $this->get_redirect_step(
			$step,
			automator_filter_input( 'skip', INPUT_GET, FILTER_VALIDATE_BOOLEAN )
		);

		if ( null !== $redirect_step ) {
			wp_safe_redirect( $this->get_dashboard_uri( $redirect_step ) );
			exit;
		}
	}

	/**
	 * Get the destination step for the current wizard state.
	 *
	 * @param int  $step           Current step.
	 * @param bool $skipped_step_1 Whether the user skipped step 1.
	 *
	 * @return int|null
	 */
	public function get_redirect_step( $step, $skipped_step_1 = false ) {

		$step               = absint( $step );
		$is_step_1_complete = $this->is_step_1_complete();

		if ( $this->is_pro_active() ) {
			// Skipping Pro license activation bypasses the licensed addons step.
			if ( 2 === $step && $skipped_step_1 ) {
				return $step < $this->get_total_steps() ? $this->get_total_steps() : null;
			}

			if ( $is_step_1_complete ) {
				// Older Pro releases still replace step 2 with a license warning. Only
				// route there when Pro explicitly declares support for the addons step.
				$has_addons_step = (bool) apply_filters( 'automator_setup_wizard_pro_addons_step_enabled', false );

				if ( ! $has_addons_step ) {
					return $step < $this->get_total_steps() ? $this->get_total_steps() : null;
				}

				if ( $step < 2 ) {
					return 2;
				}
			}

			return null;
		}

		// Lite users see the Pro offer after they connect.
		if ( $is_step_1_complete && $step < 2 ) {
			return 2;
		}

		return null;
	}

	/**
	 * Determines if the user has tried connecting before.
	 *
	 * @return false|mixed|null
	 */
	public static function has_tried_connecting() {

		return automator_get_option( 'uoa_setup_wiz_has_connected', false );
	}

	/**
	 * Sets `uoa_setup_wiz_has_connected` option base on the given value.
	 *
	 * @param bool $value Connection attempt state.
	 *
	 * @return bool
	 */
	public static function set_has_tried_connecting( $value = false ) {

		automator_update_option( 'uoa_setup_wiz_has_connected', $value );

		return true;
	}
}
