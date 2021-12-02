<?php

namespace Uncanny_Automator;

/**
 * Class Add_Bdb_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Bdb_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Bdb_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'BDB' );
		$this->set_name( 'BuddyBoss' );
		$this->set_icon( 'buddyboss-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'buddyboss-platform/bp-loader.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'buddypress' ) && isset( buddypress()->buddyboss ) && buddypress()->buddyboss;
	}
}
