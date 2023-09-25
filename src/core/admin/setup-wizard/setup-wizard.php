<?php

namespace Uncanny_Automator;

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

	/** @var string The connect url. */
	public $connect_url = '';

	/** @var string The connect page. */
	public $connect_page = '';

	/**
	 * Set-ups action hooks.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->connect_url = AUTOMATOR_FREE_STORE_URL;

		$this->connect_page = AUTOMATOR_FREE_STORE_CONNECT_URL;

		add_action( 'admin_menu', array( $this, 'setup_menu_page' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Redirect to proper step.
		add_action( 'admin_init', array( $this, 'redirect_if_connected' ), 20 );

		if ( filter_has_var( INPUT_GET, 'recipe_ui_connect_automator_license' ) && filter_has_var( INPUT_GET, 'origin' ) ) {

			add_action( 'admin_init', array( $this, 'redirect_if_from_recipe_builder' ) );

		}

	}

	/**
	 * @return void
	 */
	public static function set_tried_connecting() {
		self::set_has_tried_connecting( true );
		die;
	}

	public function redirect_if_from_recipe_builder() {

		$secret = wp_create_nonce( 'automator_setup_wizard_redirect_nonce' );

		$message_to_decrypt = array(
			'redirect_url' => urldecode( filter_input( INPUT_GET, 'return_to' ) ) . '&state=' . $secret,
		);

		$message = Automator_Helpers_Recipe::encrypt( $message_to_decrypt, $secret );

		wp_redirect(  //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
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

		$is_setup_wizard_page = 'uncanny-automator-setup-wizard' === automator_filter_input( 'page' );

		// Only add the page if site is not connected OR the user is on the setup wizard page (confirmations etc).
		if ( $is_setup_wizard_page || ! $this->is_user_connected() ) {
			add_submenu_page(
				'edit.php?post_type=uo-recipe',
				esc_attr__( 'Uncanny Automator Setup Wizard', 'uncanny-automator' ),
				esc_attr__( 'Setup wizard', 'uncanny-automator' ),
				'manage_options',
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
				'uap-admin-settings',
				Utilities::automator_get_asset( '/legacy/css/admin/performance.css' ),
				array(),
				Utilities::automator_get_version()
			);

			wp_enqueue_style(
				'uap-setup-wizard',
				plugins_url( 'assets/css/setup-wizard.css', __FILE__ ),
				array( 'uap-admin-settings' ),
				Utilities::automator_get_version()
			);

		}

	}

	/**
	 * Retrieves the current step.
	 *
	 * @return string
	 */
	public function get_step() {

		$step = absint( automator_filter_input( 'step' ) );

		if ( $step > 3 || $step < 1 ) {
			$step = 1;
		}

		return sprintf( 'step-%d', $step );

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
	 * @param $step
	 * @param $is_method
	 *
	 * @return string
	 */
	public function get_dashboard_uri( $step = 1, $is_method = false ) {

		$args = array(
			'post_type' => 'uo-recipe',
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

		return 'https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=setup_wizard&utm_content=upgrade_to_pro_btn';

	}

	/**
	 * Determines whether the user is connected or not.
	 *
	 * @return false|null
	 */
	public function is_user_connected() {

		$page = automator_filter_input( 'page' );

		$post_type = automator_filter_input( 'post_type' );

		// Pull data from licensing server if user is from set-up wizard.
		if ( 'uo-recipe' === $post_type && 'uncanny-automator-setup-wizard' === $page ) {
			return Api_Server::is_automator_connected( true ); // Pass force refresh to true.
		}

		// Otherwise pull data from local db to avoid multiple calls.
		return ! empty( Api_Server::get_license_key() );

	}

	/**
	 * Retrieves the steps.
	 *
	 * @return array
	 */
	public function get_steps() {

		$steps           = array();
		$default_step    = 1;
		$number_of_steps = 3;

		$current_step = absint( automator_filter_input( 'step' ) );

		if ( $current_step > 3 ) {
			$current_step = 3;
		}

		if ( $current_step < 1 ) {
			$current_step = 1;
		}

		for ( $i = 1; $i <= $number_of_steps; $i ++ ) {
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
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-dashboard',
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Redirects the user if they're connected already.
	 *
	 * @return void
	 */
	public function redirect_if_connected() {

		$page = automator_filter_input( 'page', INPUT_GET );
		$step = absint( automator_filter_input( 'step', INPUT_GET ) );

		if ( 'uncanny-automator-setup-wizard' === $page ) {

			$is_connected         = $this->is_user_connected();
			$has_tried_connecting = $this->has_tried_connecting();

			if ( $has_tried_connecting && ! $is_connected && 3 === $step ) {
				return;
			}

			if ( $is_connected && 3 !== $step ) {
				wp_safe_redirect( $this->get_dashboard_uri( 3 ) );
				exit;
			}

			if ( $has_tried_connecting && ! $is_connected && 2 !== $step ) {
				wp_safe_redirect( $this->get_dashboard_uri( 2 ) );
				exit;
			}
		}
	}

	/**
	 * Determines if the user has tried connecting before.
	 *
	 * @return false|mixed|null
	 */
	public static function has_tried_connecting() {

		return get_option( 'uoa_setup_wiz_has_connected', false );

	}

	/**
	 * Sets `uoa_setup_wiz_has_connected` option base on the given value.
	 *
	 * @param $bool
	 *
	 * @return bool
	 */
	public static function set_has_tried_connecting( $bool = false ) {
		update_option( 'uoa_setup_wiz_has_connected', $bool );

		return true;
	}
}
