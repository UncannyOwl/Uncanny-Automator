<?php

namespace Uncanny_Automator;

/**
 *
 */
class Add_Ut_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Uc_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'UNCANNYTOOLKIT' );
		$this->set_name( 'Uncanny Toolkit' );
		$this->set_icon( 'uncanny-owl-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'uncanny-toolkit-pro/uncanny-toolkit-pro.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'UNCANNY_TOOLKIT_VERSION' );
	}
}
