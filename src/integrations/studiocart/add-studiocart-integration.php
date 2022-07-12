<?php

namespace Uncanny_Automator;

/**
 * Add Studiocart Integration
 */
class Add_Studiocart_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Divi_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Set up integration
	 */
	protected function setup() {
		$this->set_integration( 'STUDIOCART' );
		$this->set_name( 'Studiocart' );
		$this->set_icon( 'studiocart-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( '' );
	}

	/**
	 * Check if Studiocart is active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'NCS_Cart' );
	}
}
