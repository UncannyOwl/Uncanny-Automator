<?php

namespace Uncanny_Automator\Integrations\Threads;

/**
 * Class Threads_Integration
 *
 * @package Uncanny_Automator
 */
class Threads_Integration extends \Uncanny_Automator\Integration {

	/**
	 * @var Threads_Helpers
	 */
	protected $helpers;

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Threads_Helpers();

		$this->set_integration( 'THREADS' );
		$this->set_name( 'Threads' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/threads-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'threads' ) );
		// Register wp-ajax callbacks.
		$this->register_hooks();

	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		new Threads_Settings( $this->helpers );
		new THREADS_CREATE_POST( $this->helpers );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {

		// Authorization handler.
		add_action( 'wp_ajax_automator_threads_authorization', array( $this->helpers, 'authenticate' ) );
		// Disconnect handler.
		add_action( 'wp_ajax_automator_threads_disconnect_account', array( $this->helpers, 'disconnect' ) );
		// List of accounts.
		add_action( 'wp_ajax_automator_threads_accounts_fetch', array( $this->helpers, 'accounts_fetch' ) );
		// List all 'Lists'.
		add_action( 'wp_ajax_automator_threads_list_fetch', array( $this->helpers, 'lists_fetch' ) );
		// Fetch custom fields.
		add_action( 'wp_ajax_automator_threads_custom_fields_fetch', array( $this->helpers, 'custom_fields_fetch' ) );

	}

}
