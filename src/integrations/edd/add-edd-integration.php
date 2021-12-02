<?php

namespace Uncanny_Automator;

/**
 * Class Add_Edd_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Edd_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Edd_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'EDD' );
		$this->set_name( 'Easy Digital Downloads' );
		$this->set_icon( 'easy-digital-downloads-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'easy-digital-downloads/easy-digital-downloads.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'EDD' );
	}
}
