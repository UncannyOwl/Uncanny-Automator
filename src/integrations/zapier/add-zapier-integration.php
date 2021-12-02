<?php

namespace Uncanny_Automator;

/**
 * Class Add_Zapier_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Zapier_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Zapier_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'ZAPIER' );
		$this->set_name( 'Zapier' );
		$this->set_icon( 'zapier-icon.svg' );
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
