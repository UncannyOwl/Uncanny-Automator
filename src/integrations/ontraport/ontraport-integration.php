<?php

namespace Uncanny_Automator\Integrations\Ontraport;

/**
 * Class Ontraport_Integration
 *
 * @package Uncanny_Automator
 */
class Ontraport_Integration extends \Uncanny_Automator\Integration {

	/**
	 * @var Ontraport_Helpers
	 */
	protected $helpers = null;

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Ontraport_Helpers();

		$this->set_integration( 'ONTRAPORT' );
		$this->set_name( 'Ontraport' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/ontraport-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'ontraport' ) );

		// Register wp-ajax callbacks.
		$this->register_hooks();

	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {

		new Ontraport_Settings( $this->helpers );
		new Ontraport_Upsert_Contact( $this->helpers );
		new Ontraport_Create_Tag( $this->helpers );
		new Ontraport_Delete_Contact( $this->helpers );
		new Ontraport_Add_Contact_Tag( $this->helpers );

		# Disabled - Needs clarification. new Ontraport_Subscribe_Contact_Campaign( $this->helpers );

	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Authorization handler.
		add_action( 'wp_ajax_automator_handle_authorization', array( $this->helpers, 'authenticate' ) );
		// Disconnect handler.
		add_action( 'wp_ajax_automator_ontraport_disconnect_account', array( $this->helpers, 'disconnect' ) );
		// List tags handler.
		add_action( 'wp_ajax_automator_ontraport_list_tags', array( $this->helpers, 'list_tags_handler' ) );
		// List campaign handler.
		add_action( 'wp_ajax_automator_ontraport_list_campaigns', array( $this->helpers, 'list_campaigns_handler' ) );
	}

}
