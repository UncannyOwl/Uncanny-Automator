<?php

namespace Uncanny_Automator;

/**
 * Class Add_Ifttt_Integration
 */
class Add_Ifttt_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Ifttt_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'IFTTT' );
		$this->set_name( 'IFTTT' );
		$this->set_icon( 'ifttt-icon.svg' );
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
