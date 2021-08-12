<?php

namespace Uncanny_Automator;

/**
 * Class Add_WPLMS_Integration
 * @package Uncanny_Automator
 */
class Add_WPLMS_Integration {

	use Recipe\Integrations;

	/**
	 * Add_WPLMS_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPLMS' );
		$this->set_name( 'WP LMS' );
		$this->set_icon( 'wplms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'wplms-front-end/wplms-front-end.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WPLMS_Front_End' );
	}
}
