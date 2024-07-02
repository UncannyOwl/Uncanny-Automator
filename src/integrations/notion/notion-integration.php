<?php
/**
 * @package Uncanny_Automator\Notion\Integration
 *
 * @since 5.4
 */
namespace Uncanny_Automator\Integrations\Notion;

use Uncanny_Automator\Integrations\Notion\Actions\Add_Row;
use Uncanny_Automator\Integrations\Notion\Actions\Update_Row;
use Uncanny_Automator\Integrations\Notion\Actions\Create_Page;

/**
 * @package Uncanny_Automator\Notion\Integration
 *
 * @version 1.0.0
 */
class Notion_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setups the Integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Notion_Helpers();

		$this->load_hooks( $this->helpers );

		$this->set_integration( 'NOTION' );
		$this->set_name( 'Notion' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/notion-icon.svg' );

		$this->set_connected( ! empty( $this->helpers->get_credentials() ) );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'notion' ) );

	}

	/**
	 * @param Notion_Helpers $helper
	 * @return void
	 */
	public function load_hooks( Notion_Helpers $helper ) {
		add_action( 'wp_ajax_notion_authorization', array( $helper, 'authorize_handler' ) );
		add_action( 'wp_ajax_notion_disconnect', array( $helper, 'disconnect_handler' ) );
		add_action( 'wp_ajax_automator_notion_list_pages', array( $helper, 'automator_notion_list_pages_handler' ) );
		add_action( 'wp_ajax_automator_notion_list_databases', array( $helper, 'automator_notion_list_databases_handler' ) );
		add_action( 'wp_ajax_automator_notion_get_database', array( $helper, 'automator_notion_get_database_handler' ) );
		add_action( 'wp_ajax_automator_notion_get_database_columns', array( $helper, 'automator_notion_get_database_columns_handler' ) );
		add_action( 'wp_ajax_automator_notion_list_users', array( $helper, 'automator_notion_list_users' ) );
	}

	/**
	 * Loads actions and settings.
	 *
	 * @return void
	 */
	public function load() {
		new Add_Row( $this->helpers );
		new Update_Row( $this->helpers );
		new Create_Page( $this->helpers );
		new Settings( $this->helpers );
	}


}
