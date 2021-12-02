<?php

namespace Uncanny_Automator;

/**
 * Class Add_Bp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Bp_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Bp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'BP' );
		$this->set_name( 'BuddyPress' );
		$this->set_icon( 'buddypress-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'buddypress/bp-loader.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'BuddyPress' );
	}
}
