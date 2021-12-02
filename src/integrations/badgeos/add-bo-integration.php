<?php

namespace Uncanny_Automator;

/**
 * Class Add_Bo_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Bo_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Bo_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'BO' );
		$this->set_name( 'BadgeOS' );
		$this->set_icon( 'badgeos-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'badgeos/badgeos.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'BadgeOS' );
	}
}
