<?php

namespace Uncanny_Automator;

/**
 * Class Add_Uc_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Uc_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Uc_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'UNCANNYCODE' );
		$this->set_name( 'Uncanny Codes' );
		$this->set_icon( 'uncanny-owl-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'uncanny-learndash-codes/uncanny-learndash-codes.php' );
	}
}
