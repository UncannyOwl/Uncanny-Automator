<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpsp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpsp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wpsp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPSIMPLEPAY' );
		$this->set_name( 'WP Simple Pay' );
		$this->set_icon( 'wp-simple-pay-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( '' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'SIMPLE_PAY_VERSION' );
	}
}
