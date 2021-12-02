<?php

namespace Uncanny_Automator;

/**
 * Class Add_Hf_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Hf_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Hf_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'HF' );
		$this->set_name( 'HappyForms' );
		$this->set_icon( 'happyforms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'happyforms/happyforms.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'HappyForms' );
	}
}
