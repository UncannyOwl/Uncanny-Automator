<?php

namespace Uncanny_Automator;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Module_Admin_Menu {

	/*
	 * Setting Page Title
	 */
	public $settings_page_slug;
	/*
	 * All the information about a module
	 */
	public $modules_info = array();

	/*
	 * All the information about a module
	 */
	public $modules_categorized = false;

	/**
	 * The Rest-API route
	 *
	 * The v2 means we are using version 2 of the wp rest api
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $root_path = 'uap/v2/';

	/**
	 * class constructor
	 */
	function __construct() {

		// Setup Theme Options Page Menu in Admin
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_options_menu_page' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		}

		//register api class
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

	}

	/**
	 * Create Plugin options menu
	 */
	function register_options_menu_page() {

		$page_title = __( 'Uncanny Automator', 'uncanny-automator' );

		$capability = 'manage_options';

		$menu_title               = $page_title;
		$menu_slug                = 'uncanny-automator';
		$this->settings_page_slug = $menu_slug;
		$function                 = array( $this, 'options_menu_page_output' );

		$icon_url = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDU4MSA2NDAiIHZlcnNpb249IjEuMSIgdmlld0JveD0iMCAwIDU4MSA2NDAiIHhtbDpzcGFjZT0icHJlc2VydmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0ibTUyNi40IDM0LjFjMC42IDUgMSAxMC4xIDEuMyAxNS4xIDAuNSAxMC4zIDEuMiAyMC42IDAuOCAzMC45LTAuNSAxMS41LTEgMjMtMi4xIDM0LjQtMi42IDI2LjctNy44IDUzLjMtMTYuNSA3OC43LTcuMyAyMS4zLTE3LjEgNDEuOC0yOS45IDYwLjQtMTIgMTcuNS0yNi44IDMzLTQzLjggNDUuOS0xNy4yIDEzLTM2LjcgMjMtNTcuMSAyOS45LTI1LjEgOC41LTUxLjUgMTIuNy03Ny45IDEzLjggNzAuMyAyNS4zIDEwNi45IDEwMi44IDgxLjYgMTczLjEtMTguOSA1Mi42LTY4LjEgODguMS0xMjQgODkuNWgtNi4xYy0xMS4xLTAuMi0yMi4xLTEuOC0zMi45LTQuNy0yOS40LTcuOS01NS45LTI2LjMtNzMuNy01MC45LTI5LjItNDAuMi0zNC4xLTkzLjEtMTIuNi0xMzgtMjUgMjUuMS00NC41IDU1LjMtNTkuMSA4Ny40LTguOCAxOS43LTE2LjEgNDAuMS0yMC44IDYxLjEtMS4yLTE0LjMtMS4yLTI4LjYtMC42LTQyLjkgMS4zLTI2LjYgNS4xLTUzLjIgMTIuMi03OC45IDUuOC0yMS4yIDEzLjktNDEuOCAyNC43LTYwLjlzMjQuNC0zNi42IDQwLjYtNTEuM2MxNy4zLTE1LjcgMzcuMy0yOC4xIDU5LjEtMzYuOCAyNC41LTkuOSA1MC42LTE1LjIgNzYuOC0xNy4yIDEzLjMtMS4xIDI2LjctMC44IDQwLjEtMi4zIDI0LjUtMi40IDQ4LjgtOC40IDcxLjMtMTguMyAyMS05LjIgNDAuNC0yMS44IDU3LjUtMzcuMiAxNi41LTE0LjkgMzAuOC0zMi4xIDQyLjgtNTAuOCAxMy0yMC4yIDIzLjQtNDIuMSAzMS42LTY0LjcgNy42LTIxLjEgMTMuNC00Mi45IDE2LjctNjUuM3ptLTI3OS40IDMyOS41Yy0xOC42IDEuOC0zNi4yIDguOC01MC45IDIwLjQtMTcuMSAxMy40LTI5LjggMzIuMi0zNi4yIDUyLjktNy40IDIzLjktNi44IDQ5LjUgMS43IDczIDcuMSAxOS42IDE5LjkgMzcuMiAzNi44IDQ5LjYgMTQuMSAxMC41IDMwLjkgMTYuOSA0OC40IDE4LjZzMzUuMi0xLjYgNTEtOS40YzEzLjUtNi43IDI1LjQtMTYuMyAzNC44LTI4LjEgMTAuNi0xMy40IDE3LjktMjkgMjEuNS00NS43IDQuOC0yMi40IDIuOC00NS43LTUuOC02Ni45LTguMS0yMC0yMi4yLTM3LjYtNDAuMy00OS4zLTE4LTExLjctMzkuNS0xNy02MS0xNS4xeiIgZmlsbD0iIzgyODc4QyIvPjxwYXRoIGQ9Im0yNDIuNiA0MDIuNmM2LjItMS4zIDEyLjYtMS44IDE4LjktMS41LTExLjQgMTEuNC0xMi4yIDI5LjctMS44IDQyIDExLjIgMTMuMyAzMS4xIDE1LjEgNDQuNCAzLjkgNS4zLTQuNCA4LjktMTAuNCAxMC41LTE3LjEgMTIuNCAxNi44IDE2LjYgMzkuNCAxMSA1OS41LTUgMTguNS0xOCAzNC42LTM1IDQzLjUtMzQuNSAxOC4yLTc3LjMgNS4xLTk1LjUtMjkuNS0xLTItMi00LTIuOS02LjEtOC4xLTE5LjYtNi41LTQzIDQuMi02MS4zIDEwLTE3IDI2LjgtMjkuMiA0Ni4yLTMzLjR6IiBmaWxsPSIjODI4NzhDIi8+PC9zdmc+';

		$position = 81; // 81 - Above Settings Menu

		//add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );

	}

	function options_menu_page_output() {

		$this->setup_module_info();

		// All variables set at the class level can be used it the template file... ex. $this->modules_info
		require( Utilities::get_view( 'options-menu-page.php' ) );
	}

	/**
	 * @param $hook
	 */
	function scripts( $hook ) {

		if ( strpos( $hook, $this->settings_page_slug ) ) {

			$this->setup_module_info();

			// Admin JS
			wp_enqueue_script( 'what-input-js', Utilities::get_vendor_asset( 'what-input/js/what-input.js' ), array( 'jquery' ), Utilities::get_version(), true );
			wp_enqueue_script( 'foundation-min-js', Utilities::get_vendor_asset( 'foundation/js/foundation.js' ), array( 'jquery' ), Utilities::get_version(), true );

			// This is the JS that will run the page with localized data for rest api authentication
			wp_enqueue_script( 'uncanny-automator-admin-js', Utilities::get_js( 'admin/settings.js' ), array(
				'jquery',
				'foundation-min-js',
				'what-input-js'
			), Utilities::get_version(), true );

			// Setup group management JS with localized WP Rest API variables @see rest-api-end-points.php
			wp_register_script( 'uncanny-automator-js', Utilities::get_js( 'admin/settings.js' ), array(
				'jquery',
				'foundation-min-js',
				'what-input-js'
			), Utilities::get_version(), true );

			// API data
			$api_setup = array(
				'root'  => esc_url_raw( rest_url() . $this->root_path ),
				'nonce' => \wp_create_nonce( 'wp_rest' )
			);

			wp_localize_script( 'uncanny-automator-admin-js', 'ppbApiSetup', $api_setup );

			Utilities::enqueue_global_assets();

			wp_enqueue_script( 'uncanny-automator-admin-js' );


			// Admin CSS
			wp_enqueue_style( 'fontawesome-5', Utilities::get_vendor_asset( 'fontawesome/css/fontawesome-5.min.css' ), array(), Utilities::get_version() );
			wp_enqueue_style( 'foundation-css', Utilities::get_vendor_asset( 'foundation/css/foundation.css' ), array(), Utilities::get_version() );
			wp_enqueue_style( 'uncanny-automator-admin-css', Utilities::get_css( 'admin/settings.css' ), array(), Utilities::get_version() );

			// Load Native WP Color Picker
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}

	}

	function setup_module_info(){

		if( empty($this->modules_info) ){
			$initialized_classes = Utilities::get_all_class_instances();

			foreach ( $initialized_classes as $class_name => $class_instance ) {

				if ( method_exists( $class_instance, 'get_module_details' ) ) {
					$this->modules_info[ $class_name ] = $class_instance->get_module_details();
				}

			}

			// Let's sort the modules
			$sort_modules = apply_filters( 'module_admin_sort_modules', true, $this->modules_info, $this );

			// Define how the modules are to be sorted and by which key
			//$sort_modules_by = array( 'module_key' => 'title', 'sort_type' => SORT_NATURAL );
			$sort_modules_by = array( 'module_key' => 'order', 'sort_type' => SORT_NUMERIC );

			$sort_modules_by = apply_filters( 'module_admin_sort_modules_by', $sort_modules_by, $this->modules_info, $this );

			// Let's sort the categories
			$sort_categories = apply_filters( 'module_admin_sort_categories', true, $this->modules_info, $this );

			// Define how the categories are to be sorted
			$sort_categories_by = apply_filters( 'module_admin_sort_categories_by', SORT_NATURAL, $this->modules_info, $this );

			$this->arrange_modules( $sort_modules, $sort_modules_by, $sort_categories, $sort_categories_by );
		}
	}



	/**
	 * @param bool $sort_modules
	 * @param $sort_modules_by
	 * @param bool $sort_categories
	 * @param $sort_categories_by
	 */
	function arrange_modules( $sort_modules = false, $sort_modules_by = array(), $sort_categories = false, $sort_categories_by = false ) {

		// Sort the collection of modules
		if ( $sort_modules ) {

			// Collect the key values that need to b sorted on
			$sort_values = array();

			if ( isset( $sort_modules_by['module_key'] ) && isset( $sort_modules_by['sort_type'] ) ) {

				$module_key = $sort_modules_by['module_key'];

				$sort_type = $sort_modules_by['sort_type'];

				foreach ( $this->modules_info as $key => $module_details ) {
					$sort_values[ $key ] = $module_details[ $module_key ];
				}

				array_multisort( $sort_values, $sort_type, $this->modules_info );

			}

		}


		// Organize modules into there defined categories and sort of the category name
		if ( $sort_categories ) {

			$categorizes_modules = array();

			foreach ( $this->modules_info as $key => $module_details ) {

				$category = 'Default';

				if ( isset( $module_details['category'] ) ) {
					$category = $module_details['category'];
				}

				$categorizes_modules[ $category ][ $key ] = $module_details;
			}

			// SORT_REGULAR is defined by 0, we need to check only its not equal to false
			if ( false !== $sort_categories_by ) {
				ksort( $categorizes_modules, $sort_categories_by );
			}

			$this->modules_categorized = true;
			$this->modules_info        = $categorizes_modules;

		}

	}

	/**
	 * Rest API Custom Endpoints
	 *
	 * @since 1.0
	 */
	function register_routes() {

		register_rest_route( $this->root_path, '/switch/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'set_switch' ),
			'permission_callback' => array( $this, 'set_switch_permissions' )
		) );

		register_rest_route( $this->root_path, '/save-settings/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'save_settings' ),
			'permission_callback' => array( $this, 'save_settings_permissions' )
		) );
	}

	function set_switch( $request ) {

	if ( is_array( $_POST ) ) {

		$is_active = reset( $_POST );

		$class = key( $_POST );

		if ( ! $class || ! $is_active ) {
			$return['message'] = __( 'The data that was sent was malformed. Please reload the page and trying again.', 'uncanny-automator' );
			$return['success'] = false;

			$response = new \WP_REST_Response( $return, 200 );

			return $response;
		}

		$return = array();

		if ( 'on' === $is_active || 'off' === $is_active ) {
			update_option( $class, $is_active );
			$option = get_option( $class, $is_active );
		} else {
			$return['message'] = __( 'The data that was sent was malformed. Please reload the page and trying again.', 'uncanny-automator' );
			$return['success'] = false;

			$response = new \WP_REST_Response( $return, 200 );

			return $response;
		}

		if( 'on' === $option ){
			$return['message'] = __( 'Module is active.', 'uncanny-automator' );
			$return['success'] = true;
		}elseif( 'off' === $option ){
			$return['message'] = __( 'Module is inactive.', 'uncanny-automator' );
			$return['success'] = true;
		}else{
			$return['message'] = __( 'There was a WordPress error. Please reload the page and trying again.', 'uncanny-automator' );
			$return['success'] = false;
		}

		$response = new \WP_REST_Response( $return, 200 );

		return $response;



	}

	$return['message'] = __( 'The data that was sent was malformed. Please reload the page and trying again.', 'uncanny-automator' );
	$return['success'] = false;

	$response = new \WP_REST_Response( $return, 200 );

	return $response;

}

	/**
	 * This is our callback function that embeds our resource in a WP_REST_Response
	 */
	function set_switch_permissions() {

		$capability = apply_filters( 'set_module_switch', 'manage_options' );

		// Restrict endpoint to only users who have the edit_posts capability.
		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error( 'rest_forbidden', esc_html__( 'You do not have the capability to switch modules on or off.', 'uncanny-automator' ), array( 'status' => 401 ) );
		}

		// This is a black-listing approach. You could alternatively do this via white-listing, by returning false here and changing the permissions check.
		return true;
	}

	function save_settings( $request ) {

		$return = array();

		if ( is_array( $_POST ) ) {

			$class = false;

			if( isset($_POST['class']) ){
				$class = $_POST['class'];
			}

			if( ! $class ){
				$return['message'] = __( 'The data that was sent was malformed. Please reload the page and trying again.', 'uncanny-automator' );
				$return['success'] = false;

				$response = new \WP_REST_Response( $return, 200 );

				return $response;
			}

			// Remove multiple backslashes from class name
			$class = preg_replace("~\\\\+([\"\'\\x00\\\\])~", "$1", $class);


			$class_object = Utilities::get_class_instance( $class );

			if( ! $class_object ){

				$return['message'] = __( 'The data that was sent was malformed. Please reload the page and trying again.', 'uncanny-automator' );
				$return['success'] = false;

				$response = new \WP_REST_Response( $return, 200 );

				return $response;
			}

			$class_settings = $class_object->get_class_settings();

			foreach( $class_settings as $setting ){
				 if( isset( $setting['type'] ) ){
				 	if( isset( $setting['name'] ) && isset( $_POST[ $setting['name'] ] ) ){
					    $return['updated'][$class.'_'.$setting['name']] = $_POST[ $setting['name'] ];
					    update_option( $class.'>'.$setting['name'], $_POST[ $setting['name'] ] );
				    }

				 }
			}

			$return['message'] = __( 'Module settings are saved.', 'uncanny-automator' );
			$return['success'] = true;

			$response = new \WP_REST_Response( $return, 200 );

			return $response;

		}

		$return['message'] = __( 'The data that was sent was malformed. Please reload the page and trying again.', 'uncanny-automator' );
		$return['success'] = false;

		$response = new \WP_REST_Response( $return, 200 );

		return $response;

	}

	/**
	 * This is our callback function that embeds our resource in a WP_REST_Response
	 */
	function save_settings_permissions() {

		$capability = apply_filters( 'save_module_settings', 'manage_options' );

		// Restrict endpoint to only users who have the edit_posts capability.
		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error( 'rest_forbidden', esc_html__( 'You do not have the capability to save module settings.', 'uncanny-automator' ), array( 'status' => 401 ) );
		}

		// This is a black-listing approach. You could alternatively do this via white-listing, by returning false here and changing the permissions check.
		return true;
	}

}