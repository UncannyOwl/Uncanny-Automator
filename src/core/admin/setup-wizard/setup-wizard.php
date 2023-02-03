<?php

namespace Uncanny_Automator;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Handles Setup Wizard related functionalities.
 */
class Setup_Wizard {

	/**
	 * @var string
	 */
	public $connect_url = '';

	/**
	 * @var string
	 */
	public $connect_page = '';

	/**
	 * Set-ups action hooks.
	 */
	public function __construct() {

		$this->connect_url  = AUTOMATOR_FREE_STORE_URL;
		$this->connect_page = AUTOMATOR_FREE_STORE_CONNECT_URL;

		add_action( 'admin_menu', array( $this, 'setup_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Redirect to proper step.
		add_action( 'admin_init', array( $this, 'redirect_if_connected' ), 20 );

	}

	/**
	 * @return void
	 */
	public static function set_tried_connecting() {
		self::set_has_tried_connecting( true );
		die;
	}

	/**
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
	 * @return string
	 */
	public function get_view_path() {
		return UA_ABSPATH . 'src/core/admin/setup-wizard/src/views/';
	}

	/**
	 * @return void
	 */
	public function setup_wizard_view() {
		include_once $this->get_view_path() . 'welcome.php';
	}

	/**
	 * @return void
	 */
	public function enqueue_styles() {
		$page = automator_filter_input( 'page' );
		if ( 'uncanny-automator-setup-wizard' === $page ) {
			wp_enqueue_style( 'uap-admin-settings', Utilities::automator_get_asset( '/legacy/css/admin/performance.css' ), array(), Utilities::automator_get_version() );
			wp_enqueue_style(
				'uap-setup-wizard',
				plugins_url( 'assets/css/setup-wizard.css', __FILE__ ),
				array( 'uap-admin-settings' ),
				Utilities::automator_get_version()
			);
		}
	}

	/**
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
	 * @return string
	 */
	public function get_connect_button_uri() {

		return add_query_arg(
			array(
				'redirect_url' => rawurlencode( $this->get_dashboard_uri( 2, true ) ),
			),
			$this->connect_url . $this->connect_page
		);

	}

	/**
	 * @param $step
	 * @param $is_method
	 *
	 * @return string
	 */
	public function get_dashboard_uri( $step = 1, $is_method = false ) {

		$args = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-setup-wizard',
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
	 * @return false|mixed|null
	 */
	public static function has_tried_connecting() {
		return get_option( 'uoa_setup_wiz_has_connected', false );
	}

	/**
	 * @param $bool
	 *
	 * @return bool
	 */
	public static function set_has_tried_connecting( $bool = false ) {
		update_option( 'uoa_setup_wiz_has_connected', $bool );

		return true;
	}
}
