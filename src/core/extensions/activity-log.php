<?php

namespace Uncanny_Automator;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Activity_Log
 *
 * @package uncanny_automator
 */
class Activity_Log {

	/**
	 * Activity Page title
	 * @var $settings_page_slug
	 */
	public $settings_page_slug;

	/*
	 * Activity Log Data
	 */
	public $log_data = array();

	/**
	 *  Class constructor
	 */
	public function __construct() {

		//add_action( 'admin_menu', array( $this, 'register_options_menu_page' ) );
		add_action( 'wp_ajax_recipe-triggers', array( $this, 'load_recipe_triggers' ), 50 );
		add_action( 'wp_ajax_nopriv_recipe-triggers', array( $this, 'load_recipe_triggers' ), 50 );
		add_action( 'wp_ajax_recipe-actions', array( $this, 'load_recipe_actions' ), 50 );
		add_action( 'wp_ajax_nopriv_recipe-actions', array( $this, 'load_recipe_actions' ), 50 );
		$logs_class = Utilities::get_include( 'logs-list-table.php' );
		include_once( $logs_class );
	}


	/**
	 * Create Plugin options menu
	 */
	/*public function register_options_menu_page() {

		$page_title               = __( 'Uncanny Automator', 'uncanny-automator' );
		$capability               = 'manage_options';
		$menu_title               = $page_title;
		$menu_slug                = 'uncanny-activities';
		$this->settings_page_slug = $menu_slug;
		$function                 = array( $this, 'options_menu_page_output' );
		$icon_url                 = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDU4MSA2NDAiIHZlcnNpb249IjEuMSIgdmlld0JveD0iMCAwIDU4MSA2NDAiIHhtbDpzcGFjZT0icHJlc2VydmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0ibTUyNi40IDM0LjFjMC42IDUgMSAxMC4xIDEuMyAxNS4xIDAuNSAxMC4zIDEuMiAyMC42IDAuOCAzMC45LTAuNSAxMS41LTEgMjMtMi4xIDM0LjQtMi42IDI2LjctNy44IDUzLjMtMTYuNSA3OC43LTcuMyAyMS4zLTE3LjEgNDEuOC0yOS45IDYwLjQtMTIgMTcuNS0yNi44IDMzLTQzLjggNDUuOS0xNy4yIDEzLTM2LjcgMjMtNTcuMSAyOS45LTI1LjEgOC41LTUxLjUgMTIuNy03Ny45IDEzLjggNzAuMyAyNS4zIDEwNi45IDEwMi44IDgxLjYgMTczLjEtMTguOSA1Mi42LTY4LjEgODguMS0xMjQgODkuNWgtNi4xYy0xMS4xLTAuMi0yMi4xLTEuOC0zMi45LTQuNy0yOS40LTcuOS01NS45LTI2LjMtNzMuNy01MC45LTI5LjItNDAuMi0zNC4xLTkzLjEtMTIuNi0xMzgtMjUgMjUuMS00NC41IDU1LjMtNTkuMSA4Ny40LTguOCAxOS43LTE2LjEgNDAuMS0yMC44IDYxLjEtMS4yLTE0LjMtMS4yLTI4LjYtMC42LTQyLjkgMS4zLTI2LjYgNS4xLTUzLjIgMTIuMi03OC45IDUuOC0yMS4yIDEzLjktNDEuOCAyNC43LTYwLjlzMjQuNC0zNi42IDQwLjYtNTEuM2MxNy4zLTE1LjcgMzcuMy0yOC4xIDU5LjEtMzYuOCAyNC41LTkuOSA1MC42LTE1LjIgNzYuOC0xNy4yIDEzLjMtMS4xIDI2LjctMC44IDQwLjEtMi4zIDI0LjUtMi40IDQ4LjgtOC40IDcxLjMtMTguMyAyMS05LjIgNDAuNC0yMS44IDU3LjUtMzcuMiAxNi41LTE0LjkgMzAuOC0zMi4xIDQyLjgtNTAuOCAxMy0yMC4yIDIzLjQtNDIuMSAzMS42LTY0LjcgNy42LTIxLjEgMTMuNC00Mi45IDE2LjctNjUuM3ptLTI3OS40IDMyOS41Yy0xOC42IDEuOC0zNi4yIDguOC01MC45IDIwLjQtMTcuMSAxMy40LTI5LjggMzIuMi0zNi4yIDUyLjktNy40IDIzLjktNi44IDQ5LjUgMS43IDczIDcuMSAxOS42IDE5LjkgMzcuMiAzNi44IDQ5LjYgMTQuMSAxMC41IDMwLjkgMTYuOSA0OC40IDE4LjZzMzUuMi0xLjYgNTEtOS40YzEzLjUtNi43IDI1LjQtMTYuMyAzNC44LTI4LjEgMTAuNi0xMy40IDE3LjktMjkgMjEuNS00NS43IDQuOC0yMi40IDIuOC00NS43LTUuOC02Ni45LTguMS0yMC0yMi4yLTM3LjYtNDAuMy00OS4zLTE4LTExLjctMzkuNS0xNy02MS0xNS4xeiIgZmlsbD0iIzgyODc4QyIvPjxwYXRoIGQ9Im0yNDIuNiA0MDIuNmM2LjItMS4zIDEyLjYtMS44IDE4LjktMS41LTExLjQgMTEuNC0xMi4yIDI5LjctMS44IDQyIDExLjIgMTMuMyAzMS4xIDE1LjEgNDQuNCAzLjkgNS4zLTQuNCA4LjktMTAuNCAxMC41LTE3LjEgMTIuNCAxNi44IDE2LjYgMzkuNCAxMSA1OS41LTUgMTguNS0xOCAzNC42LTM1IDQzLjUtMzQuNSAxOC4yLTc3LjMgNS4xLTk1LjUtMjkuNS0xLTItMi00LTIuOS02LjEtOC4xLTE5LjYtNi41LTQzIDQuMi02MS4zIDEwLTE3IDI2LjgtMjkuMiA0Ni4yLTMzLjR6IiBmaWxsPSIjODI4NzhDIi8+PC9zdmc+';
		$position                 = 81; // 81 - Above Settings Menu
		add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
	}*/


	/**
	 * Ajax load triggers for recipe
	 *
	 */
	public function load_recipe_triggers() {
		global $wpdb;
		check_ajax_referer( 'load-recipes-ref', 'ajax_nonce' );

		$recipe_id     = absint( $_REQUEST['recipe_id'] );
		$return_data   = array();
		$return_data[] = array(
			'id'   => '',
			'text' => 'All triggers',
		);

		if ( ! $recipe_id ) {
			wp_send_json( $return_data );
		}

		$triggers = $wpdb->get_results(
			"select distinct(r.automator_trigger_id) as id,p.post_title as trigger_title from {$wpdb->prefix}uap_trigger_log r join {$wpdb->posts} p on p.ID = r.automator_trigger_id WHERE r.automator_recipe_id = '{$recipe_id}'  order by trigger_title asc", ARRAY_A );

		if ( $triggers ) {
			foreach ( $triggers as $trigger ) {
				$return_data[] = array(
					'id'   => $trigger['id'],
					'text' => $trigger['trigger_title'],
				);
			}
		}

		wp_send_json( $return_data );
	}

	/**
	 * Ajax load triggers for recipe
	 *
	 */
	public function load_recipe_actions() {
		global $wpdb;
		check_ajax_referer( 'load-recipes-ref', 'ajax_nonce' );

		$recipe_id     = absint( $_REQUEST['recipe_id'] );
		$return_data   = array();
		$return_data[] = array(
			'id'   => '',
			'text' => 'All actions',
		);

		if ( ! $recipe_id ) {
			wp_send_json( $return_data );
		}
		$actions = $wpdb->get_results(
			"select distinct(r.automator_action_id) as id,p.post_title as action_title from {$wpdb->prefix}uap_action_log r join {$wpdb->posts} p on p.ID = r.automator_action_id WHERE r.automator_recipe_id = '{$recipe_id}' order by action_title asc"
			, ARRAY_A );

		if ( $actions ) {
			foreach ( $actions as $action ) {
				$return_data[] = array(
					'id'   => $action['id'],
					'text' => $action['action_title'],
				);
			}
		}

		wp_send_json( $return_data );
	}
}
