<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpwh_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpwh_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wpwh_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPWEBHOOKS' );
		$this->set_name( 'WP Webhooks' );
		$this->set_icon( 'wp-webhooks-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'wp-webhooks/wp-webhooks.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WP_Webhooks_Pro' );
	}
}
