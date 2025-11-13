<?php

namespace Uncanny_Automator\Integrations\Threads;

use Exception;

/**
 * Class Threads_Integration
 *
 * @package Uncanny_Automator
 */
class Threads_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get configuration array for this integration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'THREADS',
			'name'         => 'Threads',
			'api_endpoint' => 'v2/threads',
			'settings_id'  => 'threads',
		);
	}

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Threads_App_Helpers( self::get_config() );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/threads-icon.svg' );
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		new Threads_Settings( $this->dependencies, $this->get_settings_config() );
		new THREADS_CREATE_POST( $this->dependencies );
	}

	/**
	 * Check if the app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$this->helpers->get_credentials();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}
}
