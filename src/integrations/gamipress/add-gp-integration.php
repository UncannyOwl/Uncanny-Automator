<?php

namespace Uncanny_Automator;

/**
 * Class Add_GP_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Gp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Gp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'GP' );
		$this->set_name( 'GamiPress' );
		$this->set_icon( 'gamipress-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'gamipress/gamipress.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'GamiPress' );
	}
}
