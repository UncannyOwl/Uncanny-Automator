<?php

namespace Uncanny_Automator;

/**
 * Class Add_Lf_Integration
 * @package Uncanny_Automator
 */
class Add_Lf_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Lf_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'LF' );
		$this->set_name( 'LifterLMS' );
		$this->set_icon( 'lifterlms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'lifterlms/lifterlms.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'LifterLMS' );
	}
}
