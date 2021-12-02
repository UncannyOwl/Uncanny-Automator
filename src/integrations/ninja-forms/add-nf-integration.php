<?php

namespace Uncanny_Automator;

/**
 * Class Add_Nf_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Nf_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Nf_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'NF' );
		$this->set_name( 'Ninja Forms' );
		$this->set_icon( 'integration-ninjaforms@2x.png' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'ninja-forms/ninja-forms.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Ninja_Forms' );
	}
}
