<?php

namespace Uncanny_Automator;

/**
 * Class Add_Et_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Et_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Et_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'EC' );
		$this->set_name( 'The Events Calendar' );
		$this->set_icon( 'the-events-calendar-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'event-tickets/event-tickets.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Tribe__Tickets__Main' );
	}
}
