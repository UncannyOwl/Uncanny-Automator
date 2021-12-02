<?php

namespace Uncanny_Automator;

/**
 * Class Add_Em_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Em_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Em_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'EVENTSMANAGER' );
		$this->set_name( 'Events Manager' );
		$this->set_icon( 'events-manager-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'events-manager/events-manager.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'EM_Events' );
	}
}
