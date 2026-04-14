<?php

namespace Uncanny_Automator\Integrations\Trello;

use Exception;

/**
 * Class Trello_Integration
 *
 * @package Uncanny_Automator
 *
 * @property Trello_App_Helpers $helpers
 */
class Trello_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration configuration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'TRELLO',
			'name'         => 'Trello',
			'api_endpoint' => 'v2/trello',
			'settings_id'  => 'trello-api',
		);
	}

	/**
	 * Setup the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Trello_App_Helpers( self::get_config() );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/trello-icon.svg' );
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Check if the app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			return ! empty( $credentials );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Load integration components.
	 *
	 * @return void
	 */
	public function load() {
		new Trello_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		new TRELLO_CREATE_CARD( $this->dependencies );
		new TRELLO_UPDATE_CARD( $this->dependencies );
		new TRELLO_ADD_CARD_COMMENT( $this->dependencies );
		new TRELLO_ADD_CHECKLIST_ITEM( $this->dependencies );
		new TRELLO_ADD_CARD_LABEL( $this->dependencies );
		new TRELLO_ADD_CARD_MEMBER( $this->dependencies );
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_trello_get_board_options', array( $this->helpers, 'get_board_options_ajax' ) );
		add_action( 'wp_ajax_automator_trello_get_board_lists', array( $this->helpers, 'get_board_lists_ajax' ) );
		add_action( 'wp_ajax_automator_trello_get_board_members', array( $this->helpers, 'get_board_members_ajax' ) );
		add_action( 'wp_ajax_automator_trello_get_board_labels', array( $this->helpers, 'get_board_labels_ajax' ) );
		add_action( 'wp_ajax_automator_trello_get_list_cards', array( $this->helpers, 'get_list_cards_ajax' ) );
		add_action( 'wp_ajax_automator_trello_get_card_checklists', array( $this->helpers, 'get_card_checklists_ajax' ) );
		add_action( 'wp_ajax_automator_trello_get_custom_fields_repeater', array( $this->helpers, 'get_custom_fields_repeater_ajax' ) );
	}
}
