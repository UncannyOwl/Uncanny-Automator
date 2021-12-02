<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpjm_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpjm_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wpjm_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPJM' );
		$this->set_name( 'WP Job Manager' );
		$this->set_icon( 'wp-job-manager-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'wp-job-manager/wp-job-manager.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WP_Job_Manager' );
	}
}
