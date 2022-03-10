<?php

namespace Uncanny_Automator;

/**
 * Class Add_Mpc_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Mpc_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Mpc_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'MPC' );
		$this->set_name( 'MemberPress Courses' );
		$this->set_icon( 'memberpress-courses.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'memberpress-courses/main.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {

		$pluginList = get_option( 'active_plugins' );
		$plugin     = 'memberpress-courses/main.php';

		if ( in_array( $plugin, $pluginList ) && class_exists( 'MeprCtrlFactory' ) ) {
			return true;
		}

		return false;
	}
}
