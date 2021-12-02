<?php

namespace Uncanny_Automator;

/**
 * Class Add_Fr_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Fr_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Fr_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'FR' );
		$this->set_name( 'Forminator' );
		$this->set_icon( 'forminator-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'forminator/forminator.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Forminator' );
	}
}
