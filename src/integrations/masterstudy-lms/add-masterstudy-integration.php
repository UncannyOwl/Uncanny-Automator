<?php

namespace Uncanny_Automator;

/**
 * Class Add_Masterstudy_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Masterstudy_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Masterstudy_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'MSLMS' );
		$this->set_name( 'MasterStudy LMS' );
		$this->set_icon( 'masterstudy-lms.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'masterstudy-lms-learning-management-system/masterstudy-lms-learning-management-system.php' );

		$this->set_plugin_variations( array( 'masterstudy-lms-learning-management-system-pro/masterstudy-lms-learning-management-system-pro.php' ) );
		$this->set_developer_name( 'StylemixThemes' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'STM_LMS_FILE' );
	}
}
