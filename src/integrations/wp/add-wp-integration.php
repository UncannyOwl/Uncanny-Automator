<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wp_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Wp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Setup Integration
	 */
	protected function setup() {
		$this->set_integration( 'WP' );
		$this->set_name( 'WordPress' );
		$this->set_icon( __DIR__ . '/img/wordpress-icon.svg' );
		$this->set_plugin_file_path( '' );
	}

	/**
	 * Explicitly return true because WordPress is always active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
