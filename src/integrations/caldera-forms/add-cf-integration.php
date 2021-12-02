<?php

namespace Uncanny_Automator;

/**
 * Class Add_Cf_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Cf_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Cf_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'CF' );
		$this->set_name( 'Caldera Forms' );
		$this->set_icon( 'caldera-forms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'caldera-forms/caldera-core.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Caldera_Forms' );
	}
}
