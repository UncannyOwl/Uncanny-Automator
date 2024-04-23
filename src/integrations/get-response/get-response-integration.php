<?php

namespace Uncanny_Automator\Integrations\Get_Response;

/**
 * Class Get_Response_Integration
 *
 * @package Uncanny_Automator
 */
class Get_Response_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Get_Response_Helpers();

		$this->set_integration( 'GETRESPONSE' );
		$this->set_name( 'GetResponse' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/getresponse-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'getresponse' ) );
		$this->register_hooks();

	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		new Get_Response_Settings( $this->helpers );
		new GET_RESPONSE_ADD_UPDATE_CONTACT( $this->helpers );
		new GET_RESPONSE_DELETE_CONTACT( $this->helpers );

	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {

		// Settings page Ajax hooks.
		add_action( 'wp_ajax_automator_getresponse_disconnect_account', array( $this->helpers, 'disconnect' ) );
		add_action( 'wp_ajax_automator_getresponse_sync_transient_data', array( $this->helpers, 'ajax_sync_transient_data' ) );

		// Recipe action hooks.
		add_action( 'wp_ajax_automator_getresponse_get_lists', array( $this->helpers, 'ajax_get_list_options' ) );

	}

}
