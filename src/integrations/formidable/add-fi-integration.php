<?php

namespace Uncanny_Automator;

/**
 * Class Add_Fi_Integration
 * @package Uncanny_Automator
 */
class Add_Fi_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Fi_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'FI' );
		$this->set_name( 'Formidable' );
		$this->set_icon( 'formidable-forms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'formidable/formidable.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'FrmHooksController' );
	}
}
