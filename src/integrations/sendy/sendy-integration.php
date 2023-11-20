<?php

namespace Uncanny_Automator\Integrations\Sendy;

/**
 * Class Sendy_Integration
 *
 * @package Uncanny_Automator
 */
class Sendy_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Sendy_Helpers();

		$this->set_integration( 'SENDY' );
		$this->set_name( 'Sendy' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sendy-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'sendy' ) );
		$this->register_hooks();

	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// load settings, actions and triggers
		new Sendy_Settings( $this->helpers );
		new SENDY_ADD_UPDATE_LIST_CONTACT( $this->helpers );
		new SENDY_UNSUBSCRIBE_LIST_CONTACT( $this->helpers );
		new SENDY_DELETE_LIST_CONTACT( $this->helpers );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_sendy_disconnect_account', array( $this->helpers, 'disconnect' ) );
		add_action( 'wp_ajax_automator_sendy_sync_transient_data', array( $this->helpers, 'ajax_sync_transient_data' ) );
		add_action( 'wp_ajax_automator_sendy_get_lists', array( $this->helpers, 'ajax_get_list_options' ) );
	}

}
