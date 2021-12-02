<?php

namespace Uncanny_Automator;

/**
 * Class Add_Gf_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Gf_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Gf_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'GF' );
		$this->set_name( 'Gravity Forms' );
		$this->set_icon( 'gravity-forms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'gravityforms/gravityforms.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'GFFormsModel' );
	}
}
