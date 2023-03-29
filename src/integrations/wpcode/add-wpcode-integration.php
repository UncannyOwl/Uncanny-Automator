<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpcode_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpcode_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->set_integration( 'WPCODE_IHAF' );
		$this->set_name( 'WPCode' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_icon( 'wpcode-icon.svg' );
	}

	/**
	 * Method plugin_active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WPCode' );
	}

}
