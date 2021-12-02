<?php

namespace Uncanny_Automator;

/**
 * Class Add_BB_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Bb_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Bb_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'BB' );
		$this->set_name( 'bbPress' );
		$this->set_icon( 'bbpress-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'bbpress/bbpress.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'bbPress' );
	}
}
