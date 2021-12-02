<?php

namespace Uncanny_Automator;

/**
 * Class Add_Givewp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Give_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Give_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'GIVEWP' );
		$this->set_name( 'GiveWP' );
		$this->set_icon( 'givewp-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'give/give.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Give' );
	}
}
