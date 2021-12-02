<?php

namespace Uncanny_Automator;

/**
 * Class Add_H5P_Integration
 *
 * @package Uncanny_Automator
 */
class Add_H5P_Integration {

	use Recipe\Integrations;

	/**
	 * Add_H5P_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'H5P' );
		$this->set_name( 'H5P' );
		$this->set_icon( 'h5p-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'h5p/h5p.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'H5PCore' );
	}
}
