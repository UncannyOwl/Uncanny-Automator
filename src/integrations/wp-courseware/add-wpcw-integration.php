<?php

namespace Uncanny_Automator;

/**
 * Class Add_WPCW_Integration
 * @package Uncanny_Automator
 */
class Add_WPCW_Integration {

	use Recipe\Integrations;

	/**
	 * Add_WPCW_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPCW' );
		$this->set_name( 'WP Courseware' );
		$this->set_icon( 'wp-courseware-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'wp-courseware/wp-courseware.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WPCW_Requirements' );
	}
}
