<?php

namespace Uncanny_Automator;

/**
 * Class Add_Uoa_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Uoa_Integration {
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
		$this->set_integration( 'UOA' );
		$this->set_name( 'Automator' );
		$this->set_icon( __DIR__ . '/img/automator-core-icon.svg' );
		$this->set_plugin_file_path( 'uncanny-automator/uncanny-automator.php' );
	}

	/**
	 * Explicitly return true because WordPress is always active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
