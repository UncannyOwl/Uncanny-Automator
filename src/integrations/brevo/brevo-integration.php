<?php

namespace Uncanny_Automator\Integrations\Brevo;

/**
 * Class Charitable_Integration
 *
 * @package Uncanny_Automator
 */
class Brevo_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Brevo_Helpers();

		$this->set_integration( 'BREVO' );
		$this->set_name( 'Brevo' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/brevo-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'brevo' ) );
		$this->register_hooks();

	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		new Brevo_Settings( $this->helpers );
		new BREVO_ADD_UPDATE_CONTACT( $this->helpers );
		new BREVO_DELETE_CONTACT( $this->helpers );
		new BREVO_ADD_CONTACT_TO_LIST( $this->helpers );
		new BREVO_REMOVE_CONTACT_FROM_LIST( $this->helpers );

	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_brevo_disconnect_account', array( $this->helpers, 'disconnect' ) );
		add_action( 'wp_ajax_automator_brevo_get_lists', array( $this->helpers, 'ajax_get_list_options' ) );
		add_action( 'wp_ajax_automator_brevo_get_templates', array( $this->helpers, 'ajax_get_templates' ) );
		add_action( 'wp_ajax_automator_brevo_sync_transient_data', array( $this->helpers, 'ajax_sync_transient_data' ) );

	}

}
