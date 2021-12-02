<?php

namespace Uncanny_Automator;

/**
 * Class Add_Integromat_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Integromat_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Integromat_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'INTEGROMAT' );
		$this->set_name( 'Integromat' );
		$this->set_icon( 'integromat-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( '' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
