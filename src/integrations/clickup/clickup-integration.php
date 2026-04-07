<?php

namespace Uncanny_Automator\Integrations\ClickUp;

use Exception;

/**
 * ClickUp Integration
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 */
class ClickUp_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'CLICKUP',
			'name'         => 'ClickUp',
			'api_endpoint' => 'v2/clickup',
			'settings_id'  => 'clickup',
		);
	}

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new ClickUp_App_Helpers( self::get_config() );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/clickup-icon.svg' );
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load integration classes.
	 *
	 * @return void
	 */
	public function load() {
		// Settings.
		new ClickUp_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Actions.
		new Space_List_Task_Create( $this->dependencies );
		new Space_List_Task_Update( $this->dependencies );
		new Space_List_Task_Delete( $this->dependencies );
		new Space_List_Task_Comment_Create( $this->dependencies );
		new Space_List_Task_Tag_Add( $this->dependencies );
		new Space_List_Task_Tag_Remove( $this->dependencies );
		new Space_List_Create( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			return ! empty( $credentials['access_token'] );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// AJAX handlers for cascading field loading.
		add_action( 'wp_ajax_automator_clickup_fetch_teams', array( $this->helpers, 'fetch_teams_ajax' ) );
		add_action( 'wp_ajax_automator_clickup_fetch_spaces', array( $this->helpers, 'fetch_spaces_ajax' ) );
		add_action( 'wp_ajax_automator_clickup_fetch_folders', array( $this->helpers, 'fetch_folders_ajax' ) );
		add_action( 'wp_ajax_automator_clickup_fetch_lists', array( $this->helpers, 'fetch_lists_ajax' ) );
		add_action( 'wp_ajax_automator_clickup_fetch_assignees_list', array( $this->helpers, 'fetch_assignees_list_ajax' ) );
		add_action( 'wp_ajax_automator_clickup_fetch_statuses', array( $this->helpers, 'fetch_statuses_ajax' ) );
		add_action( 'wp_ajax_automator_clickup_fetch_tasks', array( $this->helpers, 'fetch_tasks_ajax' ) );
	}
}
