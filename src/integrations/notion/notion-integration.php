<?php
/**
 * @package Uncanny_Automator\Notion\Integration
 *
 * @since 5.4
 */
namespace Uncanny_Automator\Integrations\Notion;

use Uncanny_Automator\App_Integrations\App_Integration;
use Uncanny_Automator\Integrations\Notion\Actions\Add_Row;
use Uncanny_Automator\Integrations\Notion\Actions\Update_Row;
use Uncanny_Automator\Integrations\Notion\Actions\Create_Page;
use Exception;

/**
 * @package Uncanny_Automator\Notion\Integration
 *
 * @version 1.0.0
 */
class Notion_Integration extends App_Integration {

	/**
	 * Get the integration configuration
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'NOTION',    // Integration code.
			'name'         => 'Notion',    // Integration name.
			'api_endpoint' => 'v2/notion', // Automator API server endpoint.
			'settings_id'  => 'notion',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Setups the Integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Notion_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/notion-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load integration-specific classes.
	 *
	 * @return void
	 */
	protected function load() {
		// Load actions.
		new Add_Row( $this->dependencies );
		new Update_Row( $this->dependencies );
		new Create_Page( $this->dependencies );

		// Load the settings page.
		new Notion_Settings( $this->dependencies, $this->get_settings_config() );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$creds = $this->helpers->get_credentials();
			return ! empty( $creds );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		add_action( 'wp_ajax_automator_notion_list_pages', array( $this->helpers, 'automator_notion_list_pages_handler' ) );
		add_action( 'wp_ajax_automator_notion_list_databases', array( $this->helpers, 'automator_notion_list_databases_handler' ) );
		add_action( 'wp_ajax_automator_notion_get_database', array( $this->helpers, 'automator_notion_get_database_handler' ) );
		add_action( 'wp_ajax_automator_notion_get_database_columns', array( $this->helpers, 'automator_notion_get_database_columns_handler' ) );
		add_action( 'wp_ajax_automator_notion_list_users', array( $this->helpers, 'automator_notion_list_users' ) );
	}
}
