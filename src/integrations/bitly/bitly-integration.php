<?php

namespace Uncanny_Automator\Integrations\Bitly;

/**
 * Class Bitly_Integration
 *
 * @package Uncanny_Automator
 */
class Bitly_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Bitly_Helpers();

		$this->set_integration( 'BITLY' );
		$this->set_name( 'Bitly' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/bitly-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'bitly' ) );
		// Register wp-ajax callbacks.
		$this->register_hooks();

	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {

		new Bitly_Settings( $this->helpers );
		new BITLY_SHORTEN_URL( $this->helpers );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Disconnect handler.
		add_action( 'wp_ajax_automator_bitly_disconnect_account', array( $this->helpers, 'disconnect' ) );
	}

}
