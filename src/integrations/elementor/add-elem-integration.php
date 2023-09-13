<?php

namespace Uncanny_Automator;

/**
 * Class Add_Elem_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Elem_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Elem_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'ELEM' );
		$this->set_name( 'Elementor' );
		$this->set_icon( 'elementor-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'elementor-pro/elementor-pro.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'ELEMENTOR_PRO_PATH' );
	}
}
