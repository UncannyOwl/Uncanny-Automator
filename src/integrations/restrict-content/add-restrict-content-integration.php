<?php

namespace Uncanny_Automator;

/**
 * Class Add_Uoa_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Restrict_Content_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Restrict_Content_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'RC' );
		$this->set_name( 'Restrict Content Pro' );
		$this->set_icon( 'restrict-content-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'restrict-content-pro/restrict-content-pro.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'rcp_get_membership_levels' );
	}
}
