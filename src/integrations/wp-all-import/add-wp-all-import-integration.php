<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wp_All_Import_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wp_All_Import_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Edd_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPAI' );
		$this->set_name( 'WP All Import' );
		$this->set_icon( 'wp-all-import-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );

		$this->set_plugin_file_path( 'wp-all-import/plugin.php' );
		$this->set_plugin_variations( array( 'wp-all-import-pro/wp-all-import-pro.php' ) );
		$this->set_developer_name( 'Soflyy' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'PMXI_Plugin' );
	}

}
