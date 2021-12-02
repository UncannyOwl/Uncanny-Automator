<?php
namespace Uncanny_Automator;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Setup_Wizard {

	public $connect_url  = '';
	public $connect_page = '';

	public function __construct() {

		$this->connect_url  = AUTOMATOR_FREE_STORE_URL;
		$this->connect_page = AUTOMATOR_FREE_STORE_CONNECT_URL;

		add_action( 'admin_menu', array( $this, 'setup_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Redirect to proper step.
		add_action( 'admin_init', array( $this, 'redirect_if_connected' ), 20 );

	}

	public static function set_tried_connecting() {
		self::set_has_tried_connecting( true );
		die;
	}

	public function setup_menu_page() {
		add_submenu_page(
			null,
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

	public function get_view_path() {
		return UA_ABSPATH . 'src/core/admin/setup-wizard/src/views/';
	}

	public function setup_wizard_view() {
		include_once $this->get_view_path() . 'welcome.php';
	}

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

	public function get_step() {

		$step = absint( automator_filter_input( 'step' ) );

		if ( $step > 3 || $step < 1 ) {
			$step = 1;
		}

		return sprintf( 'step-%d', $step );

	}

	public function get_connect_button_uri() {

		return add_query_arg(
			array(
				'redirect_url' => rawurlencode( $this->get_dashboard_uri( 2, true ) ),
			),
			$this->connect_url . $this->connect_page
		);

	}

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

	public function get_checkout_uri() {
		return 'https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=setup_wizard&utm_content=upgrade_to_pro_btn';
	}

	public function is_user_connected() {
		return Admin_Menu::is_automator_connected();
	}

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

	public function get_automator_dashboard_uri() {
		return add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-dashboard',
			),
			admin_url( 'edit.php' )
		);
	}

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

	public static function has_tried_connecting() {
		return get_option( 'uoa_setup_wiz_has_connected', false );
	}

	public static function set_has_tried_connecting( $bool = false ) {
		update_option( 'uoa_setup_wiz_has_connected', $bool );
		return true;
	}
}
