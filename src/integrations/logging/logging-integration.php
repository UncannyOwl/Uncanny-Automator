<?php


namespace Uncanny_Automator\Integrations\Logging;

use Uncanny_Automator\Integration;

/**
 * Class Logging_Integration
 *
 * @package Uncanny_Automator
 */
class Logging_Integration extends Integration {

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->helpers = new Logging_Helpers();
		$this->set_integration( 'LOGGING' );
		$this->set_name( 'Logging' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/logging-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new WRITE_DATA_TO_LOG( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
