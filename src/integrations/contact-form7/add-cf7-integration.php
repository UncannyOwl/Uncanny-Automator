<?php

namespace Uncanny_Automator;

/**
 * Class Add_Cf7_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Cf7_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Cf7_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'CF7' );
		$this->set_name( 'Contact Form 7' );
		$this->set_icon( 'contact-form-7-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'contact-form-7/wp-contact-form-7.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WPCF7' );
	}
}
