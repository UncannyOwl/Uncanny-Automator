<?php

namespace Uncanny_Automator;

/**
 * Class Add_Lp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Lp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Lp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'LP' );
		$this->set_name( 'LearnPress' );
		$this->set_icon( 'learnpress-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'learnpress/learnpress.php' );

		$this->set_developer_name( 'ThimPress' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'LearnPress' );
	}
}
