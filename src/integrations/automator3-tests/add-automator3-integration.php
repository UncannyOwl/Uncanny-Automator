<?php

namespace Uncanny_Automator;

/**
 * Class Add_Uoa_Integration
 * @package Uncanny_Automator
 */
class Add_Automator3_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'AUTOMATOR3' );
		$this->set_name( 'Automator 3.x' );
		$this->set_icon( __DIR__ . '/img/automator-core-icon.svg' );
		$this->set_plugin_file_path( 'uncanny-automator/uncanny-automator.php' );
	}

	/**
	 * Explicitly return true because WordPress is always active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		// Check if woocommerce is active.
		return true;
	}
}
