<?php

namespace Uncanny_Automator;

/**
 * Class Add_Mp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Mp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Mp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'MP' );
		$this->set_name( 'MemberPress' );
		$this->set_icon( 'memberpress-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'memberpress/memberpress.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'MeprCtrlFactory' );
	}
}
