<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpcode_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpcode_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->set_integration( 'WPCODE_IHAF' );
		$this->set_name( 'WPCode' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_icon( 'wpcode-icon.svg' );

		$this->set_plugin_file_path( 'insert-headers-and-footers/ihaf.php' );
		$this->set_plugin_variations( array( 'wpcode-premium/wpcode.php' ) );
		$this->set_developer_name( 'WPCode' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Method plugin_active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WPCode' ) || class_exists( 'WPCode_Premium' );
	}

}
