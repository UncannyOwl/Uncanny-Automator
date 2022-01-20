<?php

namespace Uncanny_Automator;

/**
 * Class Add_Integrately_Integration
 */
class Add_Integrately_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Integrately_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'INTEGRATELY' );
		$this->set_name( 'Integrately' );
		$this->set_icon( 'integrately-icon.svg' );
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
