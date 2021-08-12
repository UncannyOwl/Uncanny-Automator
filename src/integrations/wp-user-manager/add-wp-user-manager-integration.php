<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wp_User_Manager_Integration
 * @package Uncanny_Automator
 */
class Add_Wp_User_Manager_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wp_User_Manager_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPUSERMANAGER' );
		$this->set_name( 'WP User Manager' );
		$this->set_icon( 'wp-user-manager-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'wp-user-manager/wp-user-manager.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WP_User_Manager' );
	}
}
