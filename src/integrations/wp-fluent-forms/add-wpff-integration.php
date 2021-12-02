<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpff_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpff_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wpff_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPFF' );
		$this->set_name( 'Fluent Forms' );
		$this->set_icon( 'wp-fluent-forms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'fluentform/fluentform.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'FLUENTFORM' );
	}
}
