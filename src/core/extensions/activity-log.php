<?php

namespace Uncanny_Automator;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Activity_Log
 *
 * @package Uncanny_Automator
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
