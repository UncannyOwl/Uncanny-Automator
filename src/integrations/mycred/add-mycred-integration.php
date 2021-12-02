<?php

namespace Uncanny_Automator;

/**
 * Class Add_Mycred_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Mycred_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Mycred_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'MYCRED' );
		$this->set_name( 'myCred' );
		$this->set_icon( 'mycred-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'mycred/mycred.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'myCRED_Core' );
	}
}
