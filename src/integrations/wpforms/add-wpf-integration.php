<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpf_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpf_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wpf_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPF' );
		$this->set_name( 'WPForms' );
		$this->set_icon( 'wpforms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );

		$this->set_plugin_file_path( 'wpforms-lite/wpforms.php' );
		$this->set_plugin_variations( array( 'wpforms/wpforms.php' ) );
		$this->set_developer_name( 'WPForms' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WPForms' );
	}
}
